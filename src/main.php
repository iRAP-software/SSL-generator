<?php

require_once(__DIR__ . '/Settings.php');
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/functions.php');

$autoloader = new \iRAP\Autoloader\Autoloader(array(__DIR__));

// commandline options
// short options
// usage example
// $php main.php -d domain1.com -a domain2.com -a domain3.com -t

$shortopts = "d::";  // domain name - optional;
$shortopts .= "a::"; // alternative domain name (for multiple domains) - optional
$shortopts .= "t"; // test: prevents actual operation, but will output commands that would be executed normally - no value

//long options
// usage example
// $php main.php --domain=domain.com --alternate domain2.com --test
$longopts = array(
    'domain::', // domain name - optional;
    'alternate::', // alternate domain name - optional
    'test' //
);

$params = getopt($shortopts,$longopts);

// set the testing constant
if(isset($params['t']) || isset($params['test'])) {
    define('TESTING',true);
}
else {
    define('TESTING',false);
}

// set the primary value
if(isset($params['d']) || isset($params['domain'])) {
    $primary = $params['domain'];
}
else {
    print "What is the full domain(s) that you want a certificate for?" . PHP_EOL;
    $primary = strtolower(readline());
}

// set the alternates value
if(isset($params['a'] || isset($params['alternate'])) {
    $alternates = $params['alternate'];
}

// merge the primary and alternates into 1 array for authorizing and processing
$domains = array_merge((array)$primary, (array) $alternates);

// create the commandline string that will be appended to the acme-php request command later on.
$requestString = $primary . implode(' -a '.$alternates);




/* old parameter retrieval
if (isset($argv[1]))
{
    $supplied_domain_value = $argv[1];
}
else
{
    print "What is the full domain(s) that you want a certificate for?" . PHP_EOL . "(For multiple domains, use the -a parameter key: eg enter as: domain1.com -a domain2.com -a domain3.com etc)" . PHP_EOL;
    $supplied_domain_value = readline();
}
*/

$supplied_domain_value = strtolower($supplied_domain_value); // Caps would likely just mess everything up and not needed.


// at this point, check that if we have been supplied 1 or more domains.
if(strpos($supplied_domain_value,' ')){
    $domains = explode(' -a', $supplied_domain_value);
    $primary = $domains[0];
}
else {
    $primary = $supplied_domain_value;
    $domains = array($primary);
}

// Authorize the domain(s) by adding a txt record to each domain supplied
authorizeDomains($domains);

/* moved to authorizeDomain();
$txtValue = getDnsTxtValueForDomain($FQDN);
$recordHostname = "_acme-challenge." . $FQDN;

//print "TXT value: " . $txtValue . PHP_EOL;
//print "Record Hostname: " . $recordHostname . PHP_EOL;


// /* @var $driver AcmeDnsDriverInterface
$driver = Settings::getDnsDriver();
$driver->addTxtRecord($recordHostname, $txtValue);


print "Waiting for DNS propagation. This may take a while depending on your DNS provider..." . PHP_EOL;
$hostCheckCommand = "/usr/bin/host -t TXT " . $recordHostname;

while (true)
{
    $output = shell_exec($hostCheckCommand);

    if (strpos($output, $txtValue) !== FALSE)
    {
        print "found record! " . $output . PHP_EOL;
        break;
    }

    sleep(1);
}
*/

checkDomains($domains);

/* Moved to checkDomain()
// get acmephp to check
print "Requesting letsencrypt run the check..." . PHP_EOL;
$checkCommand = ACMEPHP_COMMAND . " check -s dns {$FQDN}";

while (true)
{
    $output = shell_exec($checkCommand);

    if (strpos($output, "The authorization check was successful!") !== FALSE)
    {
        print "found record! " . $output . PHP_EOL;
        break;
    }

    sleep(3);
}
*/


// finally, make the request for the certificates.
$requestCommand = ACMEPHP_COMMAND . " request {$supplied_domain_value}";
$output = shell_exec($requestCommand);

// the user may or may not have been asked a series of questions for that cert, depending on whether
// it is their first time or not. This actually still works.

print $output . PHP_EOL;


// Copy the certificates to wherever this script is being called from:
// Use the primary domain name
mkdir($primary);
mkdir("{$primary}/nginx");
mkdir("{$primary}/apache");

$certsPath = getenv('HOME') . '/.acmephp/master/certs/' . $primary;
$privateKeyPath = getenv('HOME') . '/.acmephp/master/private/' . $primary;

$chainfile = $certsPath . '/chain.pem';
$nginxCombinedFile = $certsPath . '/fullchain.pem';
$siteCert = $certsPath . '/cert.pem';
$privateKey = $privateKeyPath . '/private.pem';

copy($chainfile, "{$primary}/apache/ca_bundle.crt");
copy($siteCert, "{$primary}/apache/{$primary}.crt");
copy($privateKey, "{$primary}/apache/{$primary}.key");

copy($nginxCombinedFile, "{$primary}/nginx/{$primary}.crt");
copy($privateKey, "{$primary}/nginx/{$primary}.key");

























