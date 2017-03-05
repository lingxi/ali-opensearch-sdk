<?php

use Lingxi\AliOpenSearch\OpenSearchClient;

class SearchTest extends PHPUnit_Framework_TestCase
{
    protected $opensearchClient = null;

    public function setUp()
    {
        $this->opensearchClient = new OpenSearchClient([
            'access_key_id'     => '',
            'access_key_secret' => '',
            'debug'             => true,
        ]);
    }

    public function test_something_work()
    {
        $this->assertTrue(true);
    }
}
