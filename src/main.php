<?php

require_once(__DIR__ . '/Settings.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/functions.php');

$autoloader = new \iRAP\Autoloader\Autoloader(array(__DIR__));

// Commandline parameter options

// Short options
// usage example:
// $php main.php -d domain1.com -a domain2.com -a domain3.com -t

$shortopts = "d::";  // domain name - optional;
$shortopts .= "a::"; // alternative domain name (for multiple domains) - optional
$shortopts .= "t"; // test: prevents actual operation, but will output commands that would be executed normally - no value

// Long options
// usage example:
// $php main.php --domain=domain.com --alternate domain2.com --test
$longopts = array(
    'domain::', // domain name - optional;
    'alternate::', // alternate domain name - optional
    'test' //
);

// Retrieve the parameters if sent
$params = getopt($shortopts,$longopts);

// Set the testing constant
if(isset($params['t']) || isset($params['test'])) {
    define('TESTING',true);
}
else {
    define('TESTING',false);
}

// Set the alternates value
if(isset($params['a']) || isset($params['alternate'])) {
    $alternates = $params['alternate'];
}

// Set the primary value
if(isset($params['d']) || isset($params['domain'])) {
    $primary = $params['domain'];
}

// If no explicit parameters passed, fallback to checking the implicit arguments
if(isset($argv[1])) {
    $supplied_domain_value = $argv[1];
}

// If no implicit arguments passed, fallback to an interactive request
if(empty($params) && !isset($supplied_domain_value)) {
    print "What is the full domain(s) that you want a certificate for?" . PHP_EOL . "For multiple domains, use the -a parameter key: eg enter as:" . PHP_EOL . "primarydomain.com -a firstalternatedomain.com -a secondalternatedomain.com ... etc" . PHP_EOL;
    $supplied_domain_value = strtolower( readline() );
}

// If dealing with the interactive or implicit argument, clean it up and set
// the relevant variables
if(!isset($primary) && isset($supplied_domain_value)) {

    // at this point, check that if we have been supplied 1 or more domains
    // by checking initially for a space in the request string
    if(strpos($supplied_domain_value,' ')){
        // check for the presence of the testing flag
        if(strpos($supplied_domain_value,' -t ')) {
            define('TESTING',true);
        }
        // if we have spaces, but no -a key, error out
        if(!strpos($supplied_domain_value,' -a ')) {
            die("Multiple domains provided in incorrect format. Please try again.".PHP_EOL);
        }
        // if
        if(strpos($supplied_domain_value,' -a ')) {
            $domains = explode(' -a ', $supplied_domain_value);
            $primary = $domains[0];
        }
    }
    else {
        $primary = $supplied_domain_value;
        $domains = array($primary);
    }
}

// Parameter Validation Drop Outs

// Drop out if a primary domain has not been specified
if($primary == '') {
    die("No domain name supplied, closing down." . PHP_EOL);
}

// If a space slips into the primary domain.
if(strpos($primary,' ')) {
    die("Supplied domain name is badly formatted: '" . $primary . "'." . PHP_EOL);
}









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
if(isset($alternatives) && !empty($alternates)) {
    $requestString .= implode(' -a '.$alternates);
}



print "--------------- testing ---------------".PHP_EOL;
print "Argv:                    " . print_r($_SERVER['argv'],true) . PHP_EOL;
print "Params:                  " . print_r($params,true) . PHP_EOL;
print "Supplied Domain Value:   " . $supplied_domain_value . PHP_EOL;
print "Primary:                 " . $primary . PHP_EOL;
print "Request String:          " . $requestString . PHP_EOL;
print "Testing:                 ". TESTING . PHP_EOL;


die();


// Authorize the domain(s) by adding a txt record to each domain supplied
authorizeDomains($domains);

// run the domain checks
checkDomains($domains);

// finally, make the request for the certificates.
requestCertificateFromAuthority($requestString);

// Copy the certificates to wherever this script is being called from:
// Use the primary domain name
copyCertificatesToDomainFolder($primary);

// Check the certificate and output the status report
checkCertificateStatus();
