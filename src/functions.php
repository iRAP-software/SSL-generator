<?php
/**
 * collection of procedural usage functions for this app.
 */

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

/**
 * Function to apply the authorizeDomain function to each item in an array
 * @param  array $domains array of domain names
 * @return bool          return
 */
function authorizeDomains($domains) : boolean
{
    if(is_array($domains)) {
        return array_walk($domains,'authorizeDomain');
    }
    else if(is_string($domains)){
        return authorizeDomain($domains);
    }
}


/**
 * Function to apply a TXT record to a DNS server for checking.
 * @param  string $domain This should be a fully qualified domain name
 * @return bool         return
 */
function authorizeDomain($domain) : boolean
{
    $txtValue = getDnsTxtValueForDomain($domain);
    $recordHostname = "_acme-challenge." . $domain;

    print "TXT value: " . $txtValue . PHP_EOL;
    print "Record Hostname: " . $recordHostname . PHP_EOL;

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
            print "Found record! " . $output . PHP_EOL;
            break;
        }

        sleep(1);
    }

    return true;
}


/**
 * Function to apply the checkDomain function to each item in an array
 * @param  array $domains array of domain names
 * @return bool          return
 */
function checkDomains($domains) : boolean
{
    if(is_array($domains)) {
        return array_walk($domains, 'checkDomain');
    }
    else if(is_string($domains)){
        return checkDomain($domains);
    }
}

/**
 * Function to use AcmePHP to check the TXT record applied to the supplied domain name
 * @param  string $domain a fully qualified domain name
 * @return book         return
 */
function checkDomain($domain) : boolean
{
    // get acmephp to check
    print "Requesting letsencrypt run the check..." . PHP_EOL;
    $checkCommand = ACMEPHP_COMMAND . " check -s dns {$domain}";

    while (true)
    {
        $output = shell_exec($checkCommand);

        if (strpos($output, "The authorization check was successful!") !== FALSE)
        {
            print "Found record! " . $output . PHP_EOL;
            break;
        }

        sleep(3);
    }

    return true;
}