<?php

// == Run Test
function run_test($data){

  global $root_dir;

  $json_file = "$root_dir/_" . $data['licence_number'] . "dates.json";
  $available = check_dates($data['licence_number'], $data['application_id']);
  $seen      = read_dates($json_file);
  $new       = parse_dates($available, $seen, $data['earliest_date'], $data['latest_date'], $data['ideal_day']);

  // Take action if new dates have been seen
  if (count($new) > 0) {
    if (is_array($data['email_to'])) {
      foreach ($data['email_to'] as $address) {
        send_mail($new, $address);
      }
    } elseif ($data['email_to'] != '') {
      send_mail($new, $data['email_to']);
    }

    save_data($new, $json_file);
  }
}

// == Function to read in seen dates
function read_dates($json_file) {

  if (is_file($json_file)) {
    // Read JSON file for previous data
    $dates = json_decode(file_get_contents($json_file), true);
    logger("Imported " . count($dates) . " dates:");
    foreach ($dates as $date) {
      logger("... " . date("l d F H:i",$date));
    }
  } else {
    // Use new array
    $dates = array();
  }
  return $dates;
}

// == Master function to check dates
function check_dates($licence_number, $application_id){

  global $root_dir;

  $html        = new simple_html_dom();
  $site_prefix = 'https://driverpracticaltest.direct.gov.uk';
  $cookie_file = "$root_dir/$licence_number" . "_cookies.txt";
  $fields      = array('username' => $licence_number, 'password' => $application_id);

  // Make initial request to get cookie, then log in
  $init  = page_request("$site_prefix/login", $cookie_file);
  $login = page_request("$site_prefix/login", $cookie_file, $fields);

  // Get and load date change URL
  foreach($html->load($login['html'])->find('a[id=date-time-change]') as $link) {
    $date_url = htmlspecialchars_decode($link->href);
  }
  $date_change = page_request($site_prefix . $date_url, $cookie_file, $fields, false);

  // Get and load slot picker URL
  foreach($html->load($date_change['html'])->find('form') as $form) {
    $slot_url = htmlspecialchars_decode($form->action);
  }
  $slot_picker = page_request($site_prefix . $slot_url, $cookie_file, array('testChoice' => 'ASAP'), false);

  // Get available slots
  foreach($html->load($slot_picker['html'])->find('span[class=slotDateTime]') as $slot){
    $tmp = date_create_from_format("l d F Y g:ia",$slot->innertext);
    $return[] = $tmp->getTimestamp();
  }

  // Remove cookie jar file
  cookie_clean($cookie_file);
  return $return;
}

// == Diff two arrays of dates, return new ones
function parse_dates($available, $seen, $earliest_date, $latest_date, $ideal_day) {

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
    } elseif (array_search($date, $dates['seen']) !== FALSE) {
      $class = "SEEN";
    } else {
      $class = "NEW";
      $new[] = $date;
    }

    logger("... [$class] " . date("l d F H:i", $date));
  }

  return $new;
}

// == Function to make a curl page request
function page_request($url, $cookie_jar = '', $post = array(), $verbose = false){

  global $proxy;
  global $user_agent;
  global $sleep;

  // Setup request
  logger("Requesting $url");
  $ch     = curl_init();
  $fields = '';
  $return = array();
  $capcha = FALSE;

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
  if (count($post) > 0){
    curl_setopt($ch, CURLOPT_POST, true);
    foreach ($post as $key => $value) {
      logger("... Sending $key: $value");
      $fields .= $key.'='.$value.'&';
    }
    $fields = rtrim($fields, '&');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  }

  // Execute + extract data to array
  $curl_output      = curl_exec ($ch);
  $out['headers']   = explode("\n",substr($curl_output, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)));
  $out['html']      = substr($curl_output, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
  $out['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $out['last_url']  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);

  // Check for captcha
  $dom = new simple_html_dom();
  foreach($dom->load($out['html'])->find('div[id=recaptcha-check]') as $div) {
    $capcha = TRUE;
  }

  if ($capcha) {
    cookie_clean($cookie_jar);
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

// == Send Email
function send_mail($dates, $email_to) {

  global $email_subject;
  global $email_from;

  $mail_text = "";
  foreach($dates as $date){
    $mail_text .= date("l d F, H:i", $date) . "\n";
  }

  mail($email_to, $email_subject, $mail_text, "From: $email_from\r\n");
  logger("Email sent to $email_to");
}

// == Save data
function save_data($dates, $file) {
  file_put_contents($file, json_encode($dates));
  logger("Dates saved to $file");
}

// == Logging function
function logger($message, $level = "INFO") {
  echo date("Y-m-d H:i:s") . " -- $level : $message\n";
}

// == Helper function to clean up
function cookie_clean($file){
  if(is_file($file)){
    unlink($file);
  }
}

?>
