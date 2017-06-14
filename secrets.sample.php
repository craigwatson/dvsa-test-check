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

// == Personal data
$check_data = array (
  'person' => array (
    'licence_number' => '',
    'application_id' => '',
    'latest_date'    => '', # Must be YYYY-MM-DD
    'earliest_date'  => '', # Must be YYYY-MM-DD
    'ideal_day'      => '', # Must be one of MON, TUE, WED, THU, FRI, SAT or SUN
    'email_to'       => '',
  )
);

// == Proxy
$proxy = array (
  'host' => '',
  'auth' => ''
);

// == Email from address
$email_from    = "myscript@example.com";

// == Script output directory
$out_dir   = "/var/tmp";

// == License Check Personal data
$licence_data = array (
  'person' => array(
    'licence_number' => '',
    'ni_number'      => '',
    'postcode'       => '',
    'email_to'       => '',
  )
);

?>
