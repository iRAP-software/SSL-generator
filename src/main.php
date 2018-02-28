<?php

require_once(__DIR__ . '/Settings.php');
require_once(__DIR__ . '/vendor/autoload.php');
$autoloader = new \iRAP\Autoloader\Autoloader(array(__DIR__));

if (!isset($argv[1]))
{
    print "What is the full domain do you want a certificate for?" . PHP_EOL;
    $FQDN = readline();
}
else
{
    $FQDN = $argv[1];
}

$FQDN = strtolower($FQDN); // Caps would likely just mess everything up and not needed.



function getDnsTxtValueForDomain(string $domain) : string
{
    $cmd = ACMEPHP_COMMAND . " authorize --solver dns {$domain}";
    $output = shell_exec($cmd);
    $lines = explode(PHP_EOL, $output);

    foreach ($lines as $line)
    {
        if (strpos($line, 'TXT value') !== false) 
        {
            $txtValueLine = trim($line);
            $txtValue = str_replace("TXT value:", "", $txtValueLine);
            $txtValue = trim($txtValue);
        }
    }
    
    return $txtValue;
}


$txtValue = getDnsTxtValueForDomain($FQDN);
$recordHostname = "_acme-challenge." . $FQDN;
        
//print "TXT value: " . $txtValue . PHP_EOL;
//print "Record Hostname: " . $recordHostname . PHP_EOL;


/* @var $driver AcmeDnsDriverInterface */
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

# finally, make the request for the certificates.        
$requestCommand = ACMEPHP_COMMAND . " request {$FQDN}";
$output = shell_exec($requestCommand);

// the user may or may not have been asked a series of questions for that cert, depending on whether
// it is their first time or not. This actually still works.

print $output . PHP_EOL;


// Copy the certificates to wherever this script is being called from:
mkdir($FQDN);
mkdir("{$FQDN}/nginx");
mkdir("{$FQDN}/apache");

$certsPath = getenv('HOME') . '/.acmephp/master/certs/' . $FQDN;
$privateKeyPath = getenv('HOME') . '/.acmephp/master/private/' . $FQDN;

$chainfile = $certsPath . '/chain.pem';
$nginxCombinedFile = $certsPath . '/fullchain.pem';
$siteCert = $certsPath . '/cert.pem';
$privateKey = $privateKeyPath . '/private.pem';

copy($chainfile, "{$FQDN}/apache/ca_bundle.crt");
copy($siteCert, "{$FQDN}/apache/{$FQDN}.crt");
copy($privateKey, "{$FQDN}/apache/{$FQDN}.key");

copy($nginxCombinedFile, "{$FQDN}/nginx/{$FQDN}.crt");
copy($privateKey, "{$FQDN}/nginx/{$FQDN}.key");

























