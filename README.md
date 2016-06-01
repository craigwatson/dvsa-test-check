# DVSA Test Cancellation Checker

[![Travis CI Status](https://travis-ci.org/craigwatson/dvsa-test-check.svg?branch=master)](https://travis-ci.org/craigwatson/dvsa-test-check)

This is a small PHP application designed to send notifications when cancellations become available for a driving test scheduled with the UK DVSA.

#### Table of Contents

1. [Requirements](#requirements)
1. [Getting Started](#getting-started)
1. [Contributing](#contributing)
1. [Advanced Configuration Options](#advanced-configuration-options)
1. [Licensing](#licensing)

## Requirements

To run the application, you will need:

  * A server with PHP installed (ideally Linux)
  * The PHP `curl` module - this is used to make requests to the DVSA website
  * A _booked_ driving test, complete with booking confirmation number

### PHP Support

This application has been tested with PHP 5.4, 5.5, 5.6 and 7.0, as well as HHVM and Nightly PHP builds, via TravisCI.

## Getting Started

To get started, all you need to do is copy/rename `secrets.sample.php` to `secrets.php` and fill in your data. For example:

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

You can optionally configure the address that the script uses to send email (note that external SMTP servers are currently not configured, all email is sent via PHP's internal `mail` command), and also an HTTP proxy with optional authentication.

## Scheduled Job via Cron

To check for cancellations regularly, you can use the Linux cron utility. The below example will run the script every fifteen minutes, between the hours of 7am and 11pm - the DVSA website is taken offline outside of these hours.

The below assumes that the application is installed in `/opt/dvsa-test-check`.

```
*/15 7-23  *   *   *  /usr/bin/php /opt/dvsa-test-checktest_check.php >> /var/tmp/test_check.log 2>&1
```

## Contributing

[![Buy me a beer!](https://cdn.changetip.com/img/graphics/Beer_Graphic.png)](https://www.changetip.com/tipme/craigwatson1987)

Contributions and testing reports are extremely welcome. Please submit a pull request or issue on [GitHub](https://github.com/craigwatson/dvsa-test-check), and make sure
that your code conforms to the PEAR PHP coding standards (Travis CI will test your pull request when it's sent).

I accept tips via [ChangeTip](https://www.changetip.com/tipme/craigwatson1987) in any currency - if you would like to buy me a beer, please do!

## Licensing

* Copyright (C) 2016 [Craig Watson](http://www.cwatson.org)
* Distributed under the terms of the [Apache License v2.0](http://www.apache.org/licenses/LICENSE-2.0) - see [LICENSE file](https://github.com/craigwatson/dvsa-test-check/blob/master/LICENSE) for details.
