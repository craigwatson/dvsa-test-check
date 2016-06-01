<?php

// Script set-up
date_default_timezone_set('Europe/London');
$include_dir = dirname(__FILE__);
$root_dir   = "/var/tmp";

// Include DOM parser + script functions
require_once "$include_dir/simple_html_dom.php";
require_once "$include_dir/functions.php";

// Error if secrets file not found
if (!is_file("$include_dir/secrets.php")) {
  die(logger("Secrets file not found at $include_dir/secrets.php", "ERROR"));
} else {
  require_once "$include_dir/secrets.php";
}

// System Variables
$email_subject = "Driving Test Cancellations";
$user_agent    = 'Mozilla/5.0';
$sleep         = '2';

// Loop through checks to make
foreach ($check_data as $name => $data) {
  logger("========= Running Check for: $name");
  run_test($data);
}

?>
