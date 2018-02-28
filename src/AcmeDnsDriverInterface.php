<?php

/* 
 * An interface that drivers need to implement in order to be swappable in the ACME php wrapper.
 * This way others can add drivers for any service, such as GoDaddy, Digitalocean etc.
 */

interface AcmeDnsDriverInterface
{
    /**
     * Add a TXT record using Route53
     * @param string $name - the TXT record FQDN. E.g. "test.mydomin.org"
     * @param string $value - the value for the TXT record.
     * @return void - throw exception if anything goes wrong.
     */
    public function addTxtRecord(string $name, string $value);
}
