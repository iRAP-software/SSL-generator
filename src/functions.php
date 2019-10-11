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

/**
 * Function to perform the certificate request from the issuing authority,
 * takes a request string in a form that acme-php will honour.
 * @param  [type] $request [description]
 * @return [type]          [description]
 */
function requestCertificateFromAuthority($request) : boolean
{
    $requestCommand = ACMEPHP_COMMAND . " request {$request}";
    $output = shell_exec($requestCommand);

    // the user may or may not have been asked a series of questions for that cert, depending on whether
    // it is their first time or not. This actually still works.

    print $output . PHP_EOL;

}





/**
 * Function to copy the generated certificate files from the acmephp location
 * to a local folder (local to where the script is being called) for easier
 * access.
 * @param  string $foldername the desired name for the new folder
 * @return bool             return
 */
function copyCertificatesToDomainFolder($foldername) : boolean
{
    mkdir($foldername);
    mkdir("{$foldername}/nginx");
    mkdir("{$foldername}/apache");

    $certsPath = getenv('HOME') . '/.acmephp/master/certs/' . $foldername;
    $privateKeyPath = getenv('HOME') . '/.acmephp/master/private/' . $foldername;

    $chainfile = $certsPath . '/chain.pem';
    $nginxCombinedFile = $certsPath . '/fullchain.pem';
    $siteCert = $certsPath . '/cert.pem';
    $privateKey = $privateKeyPath . '/private.pem';

    copy($chainfile, "{$foldername}/apache/ca_bundle.crt");
    copy($siteCert, "{$foldername}/apache/{$foldername}.crt");
    copy($privateKey, "{$foldername}/apache/{$foldername}.key");

    copy($nginxCombinedFile, "{$foldername}/nginx/{$foldername}.crt");
    copy($privateKey, "{$foldername}/nginx/{$foldername}.key");

    return true;
}


/**
 * Function to run an acmephp status check to confirm the certificate is valid
 * and display the dates.
 * @return boolean return
 */
function checkCertificateStatus() : boolean
{
    $statusCommand = ACMEPHP_COMMAND . "status";
    $output = shell_exec($statusCommand);

    print $output . PHP_EOL;

    return true;
}