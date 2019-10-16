<?php
/**
 * collection of procedural usage functions for this app.
 */

/**
 * Function to retrieve a txt string from the acme-php scripts
 * for use within the TXT record within the DNS zone file.
 * @param  string $domain the domain in question
 * @return bool         return
 */
function getDnsTxtValueForDomain(string $domain) : string
{
    $cmd = ACMEPHP_COMMAND . " authorize --solver dns {$domain}";

    // output the command for reference if testing mode is live
    if(TESTING) {
        print $cmd . PHP_EOL;
        return "TXT-VALUE";
    }

    // execute the command
    $output = shell_exec($cmd);

    // parse the return from the command to find the txt value
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

    print "*** TXT value (" . $domain . "): " . $txtValue . " ***" . PHP_EOL;

    return $txtValue;
}

/**
 * Function to apply the authorizeDomain function to each item in an array
 * @param  array $domains array of domain names
 * @return bool          return
 */
function authorizeDomains($domains) : bool
{
    print PHP_EOL . "***------------ Authorizing Domain(s) ------------***" . PHP_EOL;
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
function authorizeDomain($domain) : bool
{
    $txtValue = getDnsTxtValueForDomain($domain);
    $recordHostname = "_acme-challenge." . $domain;

    print "*** Record Hostname: " . $recordHostname . " ***" . PHP_EOL;

    $hostCheckCommand = "/usr/bin/host -t TXT " . $recordHostname;

    // output the command for reference if testing mode is live
    if(TESTING) {
        print $hostCheckCommand . PHP_EOL;
        return true;
    }

    // apply the txt string to the DNS Zone file as a TXT record
    /* @var $driver AcmeDnsDriverInterface */
    $driver = Settings::getDnsDriver();
    $driver->addTxtRecord($recordHostname, $txtValue);


    print "*** Waiting for DNS propagation. This may take a while depending on your DNS provider..." . PHP_EOL;

    // Use Acme-PHP to check for the TXT record in the DNS Zone file
    while (true)
    {
        $output = shell_exec($hostCheckCommand);

        if (strpos($output, $txtValue) !== FALSE)
        {
            print "*** Found record! " . $output . PHP_EOL;
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
function checkDomains($domains) : bool
{
    print PHP_EOL . "***------------ Checking Domain(s) ------------***" . PHP_EOL;
    if(is_array($domains)) {
        return array_walk($domains, 'checkDomain');
    }
    else if(is_string($domains)){
        return checkDomain($domains);
    }
}

/**
 * Function to use AcmePHP to send a request to the Certificate Authority to
 * check the TXT record applied to the supplied domain name
 * @param  string $domain a fully qualified domain name
 * @return book         return
 */
function checkDomain($domain) : bool
{
    // get acmephp to check
    print "*** Requesting letsencrypt run the check on " . $domain . "..." . PHP_EOL;
    $checkCommand = ACMEPHP_COMMAND . " check -s dns {$domain}";

    // output the command for reference if testing mode is live
    if(TESTING) {
        print $checkCommand . PHP_EOL;
        return true;
    }


    $i = 0;
    while ($i < 5)
    {
        $i++;
        $output = shell_exec($checkCommand);

        if (strpos($output, "The authorization check was successful!") !== FALSE)
        {
            print "*** Found record for " . $domain . "! " . $output . PHP_EOL;
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
function requestCertificateFromAuthority($request) : bool
{
    print PHP_EOL . "***------------ Requesting Certificate ------------***" . PHP_EOL;
    $requestCommand = ACMEPHP_COMMAND . " request {$request}";

    // output the command for reference if testing mode is live
    if(TESTING) {
        print $requestCommand . PHP_EOL;
        return true;
    }

    $output = shell_exec($requestCommand);

    // the user may or may not have been asked a series of questions for that cert, depending on whether
    // it is their first time or not. This actually still works.

    print $output . PHP_EOL;

    return true;

}





/**
 * Function to copy the generated certificate files from the acmephp location
 * to a local folder (local to where the script is being called) for easier
 * access.
 * @param  string $foldername the desired name for the new folder
 * @return bool             return
 */
function copyCertificatesToDomainFolder($foldername) : bool
{
    print PHP_EOL . "***------------ Copying Certificates to {$foldername} ------------***" . PHP_EOL;

    $acmeFolder = getenv('HOME') . '/.acmephp/master/';

    if(TESTING) {
        print "Foldername files would be copied to: " . $foldername . PHP_EOL;
        print "And copies placed in: " . $acmeFolder . PHP_EOL;
        return true;
    }

    mkdir( $foldername );
    mkdir("{$foldername}/nginx");
    mkdir("{$foldername}/apache");

    $certsPath = $acmeFolder . 'certs/' . $foldername;
    $privateKeyPath = $acmeFolder . 'private/' . $foldername;

    $chainfile = $certsPath . '/chain.pem';
    $nginxCombinedFile = $certsPath . '/fullchain.pem';
    $siteCert = $certsPath . '/cert.pem';
    $privateKey = $privateKeyPath . '/private.pem';

    copy($chainfile, "{$foldername}/apache/ca_bundle.crt");
    copy($siteCert, "{$foldername}/apache/{$foldername}.crt");
    copy($privateKey, "{$foldername}/apache/{$foldername}.key");

    copy($nginxCombinedFile, "{$foldername}/nginx/{$foldername}.crt");
    copy($privateKey, "{$foldername}/nginx/{$foldername}.key");

    print PHP_EOL . "***------------ Certificates Copied ------------***" . PHP_EOL;

    return true;
}


/**
 * Function to run an acmephp status check to confirm the certificate is valid
 * and display the dates.
 * @return boolean return
 */
function checkCertificateStatus() : bool
{
    print PHP_EOL . "***------------ Certificate Status ------------***" . PHP_EOL;

    $statusCommand = ACMEPHP_COMMAND . " status";

    // output the command for reference if testing mode is live
    if(TESTING) {
        print $statusCommand . PHP_EOL;
        return true;
    }


    $output = shell_exec($statusCommand);

    print $output . PHP_EOL;

    return true;
}