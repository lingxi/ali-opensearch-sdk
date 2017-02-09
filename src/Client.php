<?php

namespace Lingxi\AliOpenSearch;

use Lingxi\AliOpenSearch\Sdk\CloudsearchClient;
use Lingxi\AliOpenSearch\Sdk\CloudsearchSearch;

/**
 * aliyun opensearch sdk 封装
 * @see https://help.aliyun.com/document_detail/29175.html
 */
class Client
{
    /**
     * 根据自己的应用区域选择API
     * 杭州公网API地址：http://opensearch-cn-hangzhou.aliyuncs.com
     * 杭州内网API地址：http://intranet.opensearch-cn-hangzhou.aliyuncs.com
     * 北京公网API地址：http://opensearch-cn-beijing.aliyuncs.com
     * @var string
     */
    protected $host = 'http://opensearch-cn-hangzhou.aliyuncs.com';

    /**
     * aliyun opensearch client
     * @var Lingxi\AliOpenSearch\Sdk\CloudsearchClient
     */
    protected $cloudSearchClient = null;

    public function __construct($config)
    {
        $host  = isset($config['host']) && $config['host'] ? $config['host'] : $this->host;
        $debug = isset($config['debug']) ? $config['debug'] : false;

        $this->cloudSearchClient = new CloudsearchClient(
            $config['access_key_id'],
            $config['access_key_secret'],
            [
                'host'  => $host,
                'debug' => $debug,
            ],
            'aliyun'
        );
    }

    /**
     * 使用自己的 accesskey 和 secret 实例化一个 aliyun opensearch client
     * @return Lingxi\AliOpenSearch\Sdk\CloudsearchClient
     */
    public function getCloudSearchClient()
    {
        return $this->cloudSearchClient;
    }

    /**
     * 实例化一个搜索类
     * @return Lingxi\AliOpenSearch\Sdk\CloudsearchSearch
     */
    public function getCloudSearchSearch()
    {
        return new CloudsearchSearch($this->getCloudSearchClient());
    }

    public function search($index, $queryString)
    {
        $client = $this->getCloudSearchSearch();

        // 指定一个应用用于搜索
        $client->addIndex($index);
        // 指定搜索关键词
        $client->setQueryString($queryString);
        // 指定返回的搜索结果的格式为 json
        $client->setFormat('json');

        return json_decode($client->search(), true);
    }

    public function suggest()
    {

    }
}
