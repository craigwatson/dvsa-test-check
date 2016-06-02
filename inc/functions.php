<?php
/**
 * DVSA Test Cancellation Check
 *
 * @category File
 * @package  DVSATestCheck
 * @author   Craig Watson <craig@cwatson.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @link     https://github.com/craigwatson/dvsa-test-check
 */

 /**
  * Main function to run the check and parse returned data
  *
  * @param array $data Personal data to use
  *
  * @return void
  */
function runTest($data)
{

    global $out_dir;

    $json_file = "$out_dir/dvsa_" . $data['licence_number'] . "_dates.json";
    $available = checkDates($data['licence_number'], $data['application_id']);
    $seen      = readDates($json_file);
    $new       = parseDates($available, $seen, $data['earliest_date'], $data['latest_date'], $data['ideal_day']);

    // Take action if new dates have been seen
    if (count($new) > 0) {
        if (is_array($data['email_to'])) {
            foreach ($data['email_to'] as $address) {
                sendMail($new, $address);
            }
        } elseif ($data['email_to'] != '') {
            sendMail($new, $data['email_to']);
        }

        saveData($new, $json_file);
    }
}

/**
 * Reads dates from JSON file and returns an array
 *
 * @param string $json_file Filename to read
 *
 * @return array
 */
function readDates($json_file)
{

    if (is_file($json_file)) {
        // Read JSON file for previous data
        $dates = json_decode(file_get_contents($json_file), true);
        logger("Imported " . count($dates) . " dates:");
        foreach ($dates as $date) {
            logger("... " . date("l d F H:i", $date));
        }
    } else {
        // Use new array
        $dates = array();
    }
    return $dates;
}

/**
 * Main function to run the check and parse returned data
 *
 * @param string $licence_number Licnce Number for student
 * @param string $application_id ID of the application to find
 *
 * @return array
 */
function checkDates($licence_number, $application_id)
{

    global $out_dir;

    $html        = new simple_html_dom();
    $site_prefix = 'https://driverpracticaltest.direct.gov.uk';
    $cookie_file = "$out_dir/dvsa_" . $licence_number . "_cookies.txt";
    $date_url    = '';
    $slot_url    = '';
    $found       = array();
    $fields      = array('username' => $licence_number, 'password' => $application_id);

    // Make initial request to get cookie, then log in
    $init  = pageRequest("$site_prefix/login", $cookie_file);
    $login = pageRequest("$site_prefix/login", $cookie_file, $fields);

    // Get and load date change URL
    foreach ($html->load($login['html'])->find('a[id=date-time-change]') as $link) {
        $date_url = htmlspecialchars_decode($link->href);
    }
    $date_change = pageRequest($site_prefix . $date_url, $cookie_file, $fields, false);

    // Get and load slot picker URL
    foreach ($html->load($date_change['html'])->find('form') as $form) {
        $slot_url = htmlspecialchars_decode($form->action);
    }
    $slot_picker = pageRequest($site_prefix . $slot_url, $cookie_file, array('testChoice' => 'ASAP'), false);

    // Get available slots
    foreach ($html->load($slot_picker['html'])->find('span[class=slotDateTime]') as $slot) {
        $tmp = date_create_from_format("l d F Y g:ia", $slot->innertext);
        $found[] = $tmp->getTimestamp();
    }

    // Remove cookie jar file
    cookieClean($cookie_file);
    return $found;
}

/**
 * Diff two arrays of dates, return new ones
 *
 * @param array    $available     Available dates
 * @param array    $seen          Seen dates
 * @param datetime $earliest_date Earliest date to treat as 'new'
 * @param datetime $latest_date   Latest date to treat as 'new'
 * @param string   $ideal_day     Day of the week to treat as 'ideal'
 *
 * @return array
 */
function parseDates($available, $seen, $earliest_date, $latest_date, $ideal_day)
{

    $new = array();

    logger("Parsing " . count($available) . " dates");
    foreach ($available as $date) {
        if ($date > strtotime($latest_date)) {
            $class = "LATE";
        } elseif ($date < strtotime($earliest_date)) {
            $class = "EARLY";
        } elseif (strcmp(date("l", $date), $ideal_day) == 0) {
            $class = "IDEAL";
            $new[] = $date;
        } elseif (array_search($date, $dates['seen']) !== false) {
            $class = "SEEN";
        } else {
            $class = "NEW";
            $new[] = $date;
        }

        logger("... [$class] " . date("l d F H:i", $date));
    }

    return $new;
}

/**
 * Makes a page request with CURL
 *
 * @param string  $url        URL to request
 * @param string  $cookie_jar File to use for cookies
 * @param array   $post       Array of values to use for HTTP POST request
 * @param boolean $verbose    Latest date to treat as 'new'
 *
 * @return array
 */
function pageRequest($url, $cookie_jar = '', $post = array(), $verbose = false)
{

    global $proxy;
    global $user_agent;
    global $sleep;

    // Setup request
    logger("Requesting $url");
    $ch     = curl_init();
    $fields = '';
    $return = array();
    $capcha = false;

    // Set curl options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    if ($verbose) {
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
    }

    // Set Proxy
    if ($proxy['host'] != '') {
        logger("... Using proxy: http://" . $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        if ($proxy['auth'] != '') {
            logger("... Using proxy auth");
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
        }
    }

    // Submit POST fields
    if (count($post) > 0) {
        curl_setopt($ch, CURLOPT_POST, true);
        foreach ($post as $key => $value) {
            logger("... Sending $key: $value");
            $fields .= $key.'='.$value.'&';
        }
        $fields = rtrim($fields, '&');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }

    // Execute + extract data to array
    $curl_output      = curl_exec($ch);
    $out['headers']   = explode("\n", substr($curl_output, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));
    $out['html']      = substr($curl_output, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $out['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $out['last_url']  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // Log return code
    logger("... Got HTTP " . $out['http_code']);

    // Check for captcha
    $dom = new simple_html_dom();
    foreach ($dom->load($out['html'])->find('div[id=recaptcha-check]') as $div) {
        $capcha = true;
    }

    if ($capcha) {
        cookieClean($cookie_jar);
        logger("... ERROR: Capcha present.");
        exit();
    }

    // Verbose print
    if ($verbose) {
        print_r($out);
    }

    // Sleep
    logger("... Sleeping for $sleep seconds");
    sleep($sleep);

    // Return
    return $out;
}

/**
 * Sends email
 *
 * @param array  $dates    Available dates
 * @param string $email_to Email address
 *
 * @return void
 */
function sendMail($dates, $email_to)
{

    global $email_subject;
    global $email_from;

    $mail_text = "";
    foreach ($dates as $date) {
        $mail_text .= date("l d F, H:i", $date) . "\n";
    }

    mail($email_to, $email_subject, $mail_text, "From: $email_from\r\n");
    logger("Email sent to $email_to");
}

/**
 * Saves data to file
 *
 * @param array  $dates Available dates
 * @param string $file  File name to write to
 *
 * @return void
 */
function saveData($dates, $file)
{
    file_put_contents($file, json_encode($dates));
    logger("Dates saved to $file");
}

/**
 * Diff two arrays of dates, return new ones
 *
 * @param string $message Message to log
 * @param string $level   Log level
 *
 * @return void
 */
function logger($message, $level = "INFO")
{
    echo date("Y-m-d H:i:s") . " -- $level : $message\n";
}

/**
 * Diff two arrays of dates, return new ones
 *
 * @param string $file Filename to remove
 *
 * @return void
 */
function cookieClean($file)
{
    if (is_file($file)) {
        unlink($file);
    }
}

?>
