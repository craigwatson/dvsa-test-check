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
    $seen      = readData($json_file, true);
    $new       = parseDates($available, $seen, $data['earliest_date'], $data['latest_date'], $data['ideal_day']);

    // Take action if new dates have been seen
    if (count($new) > 0) {
        if (is_array($data['email_to'])) {
            foreach ($data['email_to'] as $address) {
                sendTestCancellationMail($new, $address);
            }
        } elseif ($data['email_to'] != '') {
            sendTestCancellationMail($new, $data['email_to']);
        }

        saveData($new, $json_file);
    }
}

/**
 * Main function to check licence data and parse returned data
 *
 * @param array $data The data to use for the check
 *
 * @return void
 */
function runLicenceCheck($data)
{

    global $out_dir;

    $html           = new simple_html_dom();
    $login_url      = "https://www.viewdrivingrecord.service.gov.uk/driving-record/licence-number";
    $cookie_file    = "$out_dir/dvsa_" . $data['licence_number'] . "_licence_cookies.txt";
    $out_file       = "$out_dir/dvsa_" . $data['licence_number'] . "_licence_status.json";
    $licence_data   = array();
    $data_changed   = false;
    $cookies        = array('naturalSubmit' => 'true');
    $extract_fields = array('licence-status', 'licence-valid-from', 'licence-valid-to', 'issue-number');

    // Make inital request for cookie
    $init  = pageRequest("$login_url/", $cookie_file, array(), 10);

    // Error if we don't get a 200 (can happen if we overstep rate-limiting)
    if ($init['http_code'] !== 200) {
        logger('Initial page load failed. Exiting.');
        cookieClean($cookie_file);
        exit();
    }

    // Find the hidden "pesel" form field value
    foreach ($html->load($init['html'])->find('input[id=pesel]') as $input) {
        $pesel = $input->value;
    }

    // Set the fields for the form, stripping spaces
    $login_fields = array (
      'applicantPassportNumber' => '',
      'pesel'                   => $pesel,
      'dln'                     => str_replace(' ', '', $data['licence_number']),
      'nino'                    => str_replace(' ', '', $data['ni_number']),
      'postcode'                => str_replace(' ', '', $data['postcode']),
      'dwpPermission'           => '1'
    );

    // Run the login request
    $login = pageRequest($login_url, $cookie_file, $login_fields, 0, $cookies);

    // Get licence data
    $dom = $html->load($login['html']);
    foreach ($extract_fields as $field) {
        foreach ( $dom->find("dd[class=$field-field]") as $dd) {
            $licence_data[] = $dd->innertext;
        }
    }

    // Read old data and parse for differences
    $old_data = readData($out_file);
    if (count($old_data) > 0) {
        $c = 0;
        foreach ($licence_data as $value) {
            if ($value != $old_data[$c]) {
                $data_changed = true;
            }
            $c++;
        }
    }

    // Send email and store new data if it has changed
    if ($data_changed === true) {
        logger("License status has changed.");
        sendLicenceStatusEmail($old_data, $licence_data, $data['email_to']);
        saveData($licence_data, $out_file);
    }

    // Clean cookies
    cookieClean($cookie_file);

}

/**
 * Reads data from JSON file and returns an array of key/value pairs
 *
 * @param string  $json_file   Filename to read
 * @param boolean $format_date Whether to format the log output
 *
 * @return array
 */
function readData($json_file, $format_date = false)
{

    if (is_file($json_file)) {
        // Read JSON file for previous data
        $data = json_decode(file_get_contents($json_file), true);
        logger("Imported " . count($data) . " values:");
        foreach ($data as $item) {
            if ($format_date) {
                $output = date("l d F H:i", $item);
            } else {
                $output = $item;
            }
            logger("... " . $output);
        }
    } else {
        // Use new array
        $data = array();
    }
    return $data;
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
    $date_change = pageRequest($site_prefix . $date_url, $cookie_file, $fields);

    // Get and load slot picker URL
    foreach ($html->load($date_change['html'])->find('form') as $form) {
        $slot_url = htmlspecialchars_decode($form->action);
    }
    $slot_picker = pageRequest($site_prefix . $slot_url, $cookie_file, array('testChoice' => 'ASAP'));

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
 * @param integer $sleep      The number of seconds to sleep/pause *after* the request
 * @param string  $cookies    Array of cookies to set in-line
 * @param boolean $verbose    Latest date to treat as 'new'
 *
 * @return array
 */
function pageRequest($url, $cookie_jar = '',  $post = array(), $sleep = 2, $cookies = array(), $verbose = false)
{

    global $proxy;
    global $user_agent;

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

    if ($proxy['host'] != '') {
        logger("... Using proxy: http://" . $proxy['host']);
        curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
        if ($proxy['auth'] != '') {
            logger("... Using proxy auth");
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['auth']);
        }
    }

    if (count($cookies) > 0) {
        $cookie_string = '';
        foreach ($cookies as $key => $val) {
            logger("... Setting cookie: $key=$val");
            $cookie_string .= " $key=\"$val\";";
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
    }

    // Submit POST fields
    if (count($post) > 0) {
        curl_setopt($ch, CURLOPT_POST, true);
        foreach ($post as $key => $value) {
            logger("... Sending $key: '".urlEncode($value)."'");
            $fields .= $key.'='.urlEncode($value).'&';
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
    if ($sleep > 0) {
        logger("... Sleeping for $sleep seconds");
        sleep($sleep);
    }

    // Return
    return $out;
}

/**
 * Sends email for test cancellations
 *
 * @param array  $dates    Available dates
 * @param string $email_to Email address
 *
 * @return void
 */
function sendTestCancellationMail($dates, $email_to)
{

    global $email_subject;
    global $email_from;

    $mail_text  = "This email has been sent from DVSA Practical Test software running on " . gethostname() . ".\n";
    $mail_text .= "\nThe sofware has found the following dates that match your search:\n";

    foreach ($dates as $date) {
        $mail_text .= date("l d F, H:i", $date) . "\n";
    }

    $mail_text .= "\nPlease check the DVSA website as soon as possible to see if this slot is still available.\n";

    mail($email_to, $email_subject, $mail_text, "From: $email_from\r\n");
    logger("Email sent to $email_to");
}

/**
 * Sends email for licence status changes
 *
 * @param array  $old_data Previous licence data
 * @param array  $new_data New licence data
 * @param string $email_to Email address
 *
 * @return void
 */
function sendLicenceStatusEmail($old_data, $new_data, $email_to)
{
    global $email_subject;
    global $email_from;

    $mail_text  = "This email has been sent from DVLA Licence software running on " . gethostname() . ".\n";
    $mail_text .= "\nThe sofware has found that your licence data has changed:\n\n";
    $c = 0;

    foreach ($new_data as $value) {
        $mail_text .= "$value";
        if (array_key_exists($c, $old_data)) {
            $mail_text .= " (was: '" . $old_data[$c] . "')";
        }
        $mail_text .= "\n";
        $c++;
    }

    mail($email_to, $email_subject, $mail_text, "From: $email_from\r\n");
    logger("Email sent to $email_to");

}
/**
 * Saves data to file
 *
 * @param array  $data Data to save
 * @param string $file File name to write to
 *
 * @return void
 */
function saveData($data, $file)
{
    file_put_contents($file, json_encode($data));
    logger("Data saved to $file");
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
 * Removed a file, but only if it exists
 *
 * @param string $file Filename to remove
 *
 * @return void
 */
function cookieClean($file)
{
    if (is_file($file)) {
        logger("Clearing cookies from $file");
        unlink($file);
    }
}

?>
