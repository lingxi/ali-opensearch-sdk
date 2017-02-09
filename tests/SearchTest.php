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

    public function test_search()
    {
        $result = $this->opensearchClient->search('lingxi', 'name:科忠', ['limit' => 1]);

        $this->assertEquals($result['status'], 'OK');
    }

    public function test_suggest()
    {

    }
}
