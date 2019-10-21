<?php

require_once(__DIR__ . '/Settings.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/functions.php');

$autoloader = new \iRAP\Autoloader\Autoloader(array(__DIR__));

// use Commando CLI class
$commando = new Commando\Command();

$commando->setHelp("    SSL-Generator script to automatically run Acme-PHP commands which will:
    > Authorize a domain for SSL checking, by adding a TXT record to the zone file,
    > Check the domain's TXT record,
    > Then request an SSL Certificate from Let's Encrypt,
    > Copy the generated Certificate files into a local folder to easy access,
    > Before finally outputting the status check for that domain's SSL certificate.

    This script can also request Certificates for multiple domains.
    (where a website sits behind more than one domain name and needs a certificate for each).

    \nArguments: ");

// default option - the domain name that is required
// the domain MUST return an IP address: gethostbyname will return the IP, or the passed value on failure.
// A certificate cannot be applied for if the domain does not exist and does not respond correctly
$commando->option()
    ->aka('d')
    ->aka('domain')
    ->aka('primary')
    ->require()
    ->describedAs("A fully qualified and valid, in existence domain name (or sub-domain thereof)")
    ->must( function($value) {
        if(gethostbyname($value) == $value) {
            return false;
        }
        return true;
    });

// testing option for reviewing commands
$commando->flag('t')
    ->aka('test')
    ->aka('testing')
    ->describedAs("Testing flag, use to preview commands being sent to Acme-PHP")
    ->boolean();

// option to provide a different location to save the certificates into.
$commando->option('l')
    ->aka('location')
    ->aka('folder')
    ->describedAs("A folder name or url to store the generated certificates into.
        The folder must exist and be accessible by PHP.")
    ->must( function($value) {
        if(is_dir($value) && is_writable($value)) {
            return true;
        }
        else {
            return false;
        }
    });

// alternate or multiple domains option
// each element (if multiple) MUST return an IP address: gethostbyname will return the IP, or the passed value on failure.
// A certificate cannot be applied for if the domain does not exist and does not respond correctly
$commando->option('a')
    ->needs(['d','domain','primary'])
    ->aka('m')
    ->aka('alternate')
    ->aka('multiple')
    ->describedAs("Alternate, or multiple domains to be added to a single SSL Certificate request\nTo apply multiple alternates, separate the domains with a single space\neg:\"alternate1.com alternate2.com\"")
    ->must( function($value) {
        $arr = explode(' ',$value);
        $ret = true;
        array_walk($arr,function($v) use (&$ret) {
            if(!empty($v) && gethostbyname($v) == $v) {
                $ret = false;
            }
        });
        return $ret;
    });

// status flag: will only run the status method and is used for previewing the current state of registered certificates.
$commando->flag('s')
    ->aka('status')
    ->describedAs("Output the status of all registered certificates for review.")
    ->boolean();



// Run the status check if requested and then end.
if($commando['s']) {
    checkCertificateStatus();
    die();
}

// set our initial variable values
$primary = $commando[0];
$alternates = explode(' ',$commando['a']);
$location = ($commando['l']) ? $commando['l'] . '/' : null;
define('TESTING', ($commando['t']) ? true : false );





// Manipulate the provided parameters for use within script

// Merge the primary and alternates into 1 array for authorizing and processing
if(isset($alternates) && !empty($alternates)){
    $domains = array_merge((array)$primary, (array) $alternates);
}
else {
    $domains = (array)$primary;
}

// Create the commandline string that will be appended to the acme-php request command later on.
$requestString = $primary;
if(isset($alternates) && !empty($alternates)) {
    $requestString .= ' -a ' . implode(' -a ',$alternates);
}



if(TESTING) {
    print "***------------ TEST MODE ------------***" . PHP_EOL;
    print "Testing:                     " . ((TESTING) ? 'yes' : 'no') . PHP_EOL;
    print "Arguments:                   " . print_r($commando->getArgumentValues(),true) . PHP_EOL;
    print "Primary:                     " . $primary . PHP_EOL;
    print "Alternates:                  " . print_r($alternates,true) . PHP_EOL;
    print "Domains:                     " . print_r($domains,true) . PHP_EOL;
    print "Acme-PHP Request String:     " . $requestString . PHP_EOL;
    print "Location:                    " . (($location) ? $location : 'NOT-SPECIFIED') . PHP_EOL;
    print  PHP_EOL . "Commands that will be used:" . PHP_EOL;
}

// output the details of the request
print PHP_EOL . PHP_EOL . "***------------ SSL Certificate Generator ------------***" . PHP_EOL . PHP_EOL;
print "*** Domain(s):       " . print_r($domains,true) . PHP_EOL . PHP_EOL;



// Authorize the domain(s) by adding a txt record to each domain supplied
authorizeDomains($domains);

// run the domain checks
checkDomains($domains);

// finally, make the request for the certificates.
requestCertificateFromAuthority($requestString);

// Copy the certificates to wherever this script is being called from:
// Use the primary domain name
copyCertificatesToDomainFolder($location . $primary);

// Check the certificate and output the status report
checkCertificateStatus();


