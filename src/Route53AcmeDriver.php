<?php

/* 
 * A driver that can be used to to interface with the Route53 service.
 */

class Route53AcmeDriver implements AcmeDnsDriverInterface
{
    private $m_route53;
    
    
    /**
     * Create the driver.
     * @param string $apiKey - the key for write access to AWS Route 53
     * @param string $awsSecret - the secret for write access to AWS Route 53
     */
    public function __construct(string $apiKey, string $awsSecret)
    {
        $this->m_route53 = new iRAP\Route53Wrapper\Route53Client(
            $apiKey, 
            $awsSecret, 
            iRAP\Route53Wrapper\Objects\AwsRegion::create_EU_W1()
        );
    }
    
    
    /**
     * Add a TXT record using Route53
     * @param string $name - the TXT record FQDN. E.g. "test.mydomin.org"
     * @param string $value - the value for the TXT record.
     * @return void - throw exception if anything goes wrong.
     */
    public function addTxtRecord(string $name, string $value)
    {
        $domain = $this->getDomainFromFQDN($name);
        
        /* @var $hostedZone iRAP\Route53Wrapper\Objects\HostedZone */
        $hostedZone = $this->getHostedZoneForDomain($this->m_route53, $domain);
        
        $subdomain = $this->getSubdomainForFQDN($name);
        
        $hostedZone->addTxtRecord(
            $this->m_route53, 
            $subdomain,
            $value, 
            $ttl=60, 
            TRUE
        );
    }
    
    
    private function getDomainFromFQDN($FQDN)
    {
        $parts = explode(".", $FQDN);
        $numParts = count($parts);

        $domain = $parts[$numParts - 2] . '.' . $parts[$numParts - 1];
        return $domain;
    }
    
    
    private function getSubdomainForFQDN($FQDN)
    {
        $parts = explode(".", $FQDN);
        $numParts = count($parts);
        
        // remove the last two elements which are the domain.
        array_pop($parts);
        array_pop($parts);
        
        $subdomain = implode(".", $parts);
        
        return $subdomain;
    }
    
    
    /**
     * Get the hosted zone for the provided domain.
     * @param iRAP\Route53Wrapper\Route53Client $route53
     * @param string $domain
     * @return \iRAP\Route53Wrapper\Objects\HostedZone
     * @throws Exception
     */
    private function getHostedZoneForDomain(iRAP\Route53Wrapper\Route53Client $route53, string $domain) : iRAP\Route53Wrapper\Objects\HostedZone
    {
        $matchedHostedZone = null;
        $hostedZones = $route53->getHostedZones();

        foreach ($hostedZones as $hostedZone)
        {
            /* @var $hostedZone iRAP\Route53Wrapper\Objects\HostedZone */
            if ($hostedZone->get_name() === "{$domain}.")
            {
                print "found hosted zone with domain. " . print_r($hostedZone, true) . PHP_EOL;
                $matchedHostedZone = $hostedZone;
                break;
            }
        }

        if ($matchedHostedZone === null)
        {
            throw new Exception("Failed to find hosted zone for domain: " . $domain);
        }

        return $matchedHostedZone;
    }
}
