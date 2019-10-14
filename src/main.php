<?php

require_once(__DIR__ . '/Settings.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/functions.php');

$autoloader = new \iRAP\Autoloader\Autoloader(array(__DIR__));


// use Commando CLI class
$commando = new Commando\Command();

$commando->setHelp("SSL-Generator script to automatically run Acme-PHP commands which will:
    > Authorize a domain for SSL checking, by adding a TXT record to the zone file,
    > Check the domain's TXT record,
    > Then request an SSL Certificate from Let's Encrypt,
    > Copy the generated Certificate files into a local folder to easy access,
    > Before finally outputting the status check for that domain's SSL certificate.
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
    ->must(function($value){
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

// alternate or multiple domains option
// each element (if multiple) MUST return an IP address: gethostbyname will return the IP, or the passed value on failure.
// A certificate cannot be applied for if the domain does not exist and does not respond correctly
$commando->option('a')
    ->needs(['d','domain','primary'])
    ->aka('m')
    ->aka('alternate')
    ->aka('multiple')
    ->describedAs("Alternate, or multiple domains to be added to a single SSL Certificate request\nTo apply multiple alternates, separate the domains with a single space\neg:\"alternate1.com alternate2.com\"")
    ->must(function($value){
        $arr = explode(' ',$value);
        $ret = true;
        array_walk($arr,function($v) use (&$ret) {
            if(!empty($v) && gethostbyname($v) == $v) {
                $ret = false;
            }
        });
        return $ret;
    });


// set our initial variable values

$primary = $commando[0];
$alternates = explode(' ',$commando['a']);
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
print "--------------- testing ---------------".PHP_EOL;
print "Testing:                     " . ((TESTING) ? 'yes' : 'no') . PHP_EOL;
print "Arguments:                   " . print_r($commando->getArgumentValues(),true) . PHP_EOL;
print "Primary:                     " . $primary . PHP_EOL;
print "Alternates:                  " . print_r($alternates,true) . PHP_EOL;
print "Domains:                     " . print_r($domains,true) . PHP_EOL;
print "Acme-PHP Request String:     " . $requestString . PHP_EOL;

die();
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
copyCertificatesToDomainFolder($primary);

// Check the certificate and output the status report
checkCertificateStatus();



/*


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




*/