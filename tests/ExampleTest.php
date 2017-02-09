<?php

use Lingxi\AliOpenSearch\OpenSearchClient;

class ExampleTest extends PHPUnit_Framework_TestCase
{
    protected $opensearchClient = null;

    public function setUp()
    {
        $this->opensearchClient = new OpenSearchClient([
            'access_key_id'     => 'XtdfTYARVUEyWzH5',
            'access_key_secret' => 'OXttuQgTOxT8kDEYvRsWkj0nxf9iIn',
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
