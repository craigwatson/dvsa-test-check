# DVSA Test Cancellation Checker

[![Travis CI Status](https://travis-ci.org/craigwatson/dvsa-test-check.svg?branch=master)](https://travis-ci.org/craigwatson/dvsa-test-check)

This is a small PHP application designed to send notifications when
cancellations become available for a practical driving test scheduled with the
UK DVSA.

Credit for the [original script](https://github.com/clarkdave/DSACancellationChecker)
written in Python goes to [Dave Clark](https://github.com/clarkdave).

#### Table of Contents

1. [Requirements](#requirements)
1. [Getting Started](#getting-started)
1. [Limitations](#limitations)
1. [Advanced Functionality](#advanced-functionality)
1. [Contributing](#contributing)
1. [Licensing](#licensing)

## Requirements

To run the application, you will need:

* A server with PHP installed (ideally Linux)
* The PHP `curl` module - this is used to make requests to the DVSA website
* A _booked_ driving test, complete with application reference number
  (found on your test confirmation email)

### PHP Support

This application has been tested with PHP 5.4, 5.5, 5.6 and 7.0, as well as HHVM
and Nightly PHP builds, via TravisCI.

## Getting Started

To get started, all you need to do is copy/rename `secrets.sample.php` to
`secrets.php` and fill in your data. For example:

```
$check_data = array (
  'craig' => array (
    'licence_number' => 'SMITH27712AE1OW',
    'application_id' => '1233493',
    'latest_date'    => '2016-07-26',
    'earliest_date'  => '2016-06-18',
    'ideal_day'      => 'Friday',
    'email_to'       => 'me@example.com',
  )
);
```

You can optionally configure the address that the script uses to send email, and
also an HTTP proxy with optional authentication.

## Limitations

### DVSA Site Changes

From time-to-time, the DVSA may make changes to their website which may break
the script. As I have now passed my practical test, I'm not able to continually
test if the script is working.

If you encounter an issue with the script, you are welcome to test it using your
own license number and application reference and submit a pull request with your
changes.

### Email SMTP settings

Also note that sending email via external SMTP servers is currently not
possible, all email is sent via PHP's internal `mail` command. If you would like
to fix this, see the [Contributing](#contributing) section below.

## Advanced Functionality

### Scheduled Job via Cron

To check for cancellations regularly, you can use the Linux cron utility. The
below example will run the script every fifteen minutes, between the hours of
7am and 11pm - the DVSA website is taken offline outside of these hours.

The below assumes that the application is installed in `/opt/dvsa-test-check`.

```
*/15 7-23  *   *   *  /usr/bin/php /opt/dvsa-test-check/test_check.php >> /var/tmp/test_check.log 2>&1
```

### Checking for Changes in License Status

Once you have taken your test and (hopefully) passed it, you can use the
`license_check.php` script to check when your license has changed from a
provisional to a full license.

The script will email you with any changes to your license data, including your
issue number, valid from, valid to and license status.

The data format in `secrets.php` follows the same format, with the addition of
your National Insurance number and post code.

## Contributing

Contributions and testing reports are extremely welcome. Please submit a pull
request or issue on [GitHub](https://github.com/craigwatson/dvsa-test-check),
and make sure that your code conforms to the PEAR PHP coding standards (Travis
CI will test your pull request when it's sent).

I accept tips via Bitcoin to 1BympojWkXErUpEVE6HXm3vWNj9W6wVP2Z - if you would
like to buy me a beer or a coffee, please do!

## Licensing

* Copyright (C) 2016 [Craig Watson](https://cwatson.org)
* [Original Python script](https://github.com/clarkdave/DSACancellationChecker)
  (C) [Dave Clark](https://github.com/clarkdave) and Josh Palmer, used under the
  terms of the MIT License
* Distributed under the terms of the [Apache License v2.0](http://www.apache.org/licenses/LICENSE-2.0)
  - see [LICENSE file](https://github.com/craigwatson/dvsa-test-check/blob/master/LICENSE)
  for details.
