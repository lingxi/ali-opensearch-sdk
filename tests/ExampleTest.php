<?php

use Lingxi\AliOpenSearch\Client;

class ExampleTest extends PHPUnit_Framework_TestCase
{
    public function test_something()
    {
        $client = new Client([
            'access_key_id' => '',
            'access_key_secret' => '',
            'debug' => true,
        ]);
        var_dump($client->search('lingxi', 'name:科忠'));
        var_dump($client->getCloudSearchClient()->getRequest());
        $this->assertTrue(true);
    }
}
