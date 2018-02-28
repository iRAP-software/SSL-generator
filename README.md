SSL Generator
=============

This tool wraps around ACME PHP in order to automagically generate SSL certificates.
This does it through interfacing with your DNS provider in order to create the necessary TXT record.

## Getting Started

* [Install ACME PHP](https://blog.programster.org/acme-php-installation) to your machine.
* Clone this repository.
* Go into the `/src` folder.
* Fill in the `Settings.php.tmpl` file.
    * Right now there is only one supported driver ([Route53](https://aws.amazon.com/route53/)), so you just need to plug in the path to your ACME PHP installation, and plug in your AWS key and secret.
* Rename the `Settings.php.tmpl` file to `Settings.php`
* Execute the `main.php` file directly

It would be advisable to create an alias for executing that script.
That way you don't have go to that folder when you want to run the tool, or type a long path in every time.
E.g. `alias ssl-generator="/usr/bin/php $HOME/SSL-generator/src/main.php"`

## Contributing
It would be great to get some drivers for other DNS providers. Its as easy as writing a class that implements the `AcmeDnsDriverInterface` which has just requires one method:

```
/**
 * Add a TXT record
 * @param string $name - the TXT record FQDN. E.g. "test.mydomin.org"
 * @param string $value - the value for the TXT record.
 * @return void - throw exception if anything goes wrong.
 */
public function addTxtRecord(string $name, string $value);
```
