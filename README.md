应用层，基于 laravel scout 实现：https://laravel.com/docs/5.4/scout#custom-engines

scout 默认引擎是 algolia：https://www.algolia.com

开源的，大家常用 Elasticsearch：https://laracasts.com/discuss/channels/general-discussion/looking-for-a-search-engine-for-my-laravel-app?page=1

阿里云有开放搜索服务：https://help.aliyun.com/document_detail/29104.html?spm=5176.doc35261.6.539.qrzcjR

看文档相比自己搭 Elasticsearch 有以下优势：

- 不用买服务器、搭环境、主从、容灾、维护...这是最重要的原因（实际上看似开放搜索要收费，当时算上人工和服务器成本，自己搭建基础服务贵太多了）
- 开放搜索可以从 RDS 自动同步数据，这样就不用在应用里做数据同步了（实际上，对灵析来说，因为既有 laravel 又有 tp，要做好数据同步非常麻烦）
- 据称，开放搜索比 ElasticSearch 开源系统的QPS高4倍，查询延迟低4倍

##
### Usage In Thinkphp

```php
<?php

use Laravel\Scout\EngineManager;
use Lingxi\AliOpenSearch\OpenSearchClient;
use Lingxi\AliOpenSearch\OpenSearchEngine;
use Illuminate\Support\Facades\Facade;

$app->singleton(EngineManager::class, function ($app) {
    return (new EngineManager($app))->extend('opensearch', function () {
        return new OpenSearchEngine(new OpenSearchClient(C('scout.opensearch')));
    });
});

function app($class)
{
    return Facade::getFacadeApplication()->make($class);
}
```

## 使用

请先阅读：https://laravel.com/docs/5.3/scout

在 Model 里添加 Searchable Trait

```php
$result = Contact::search(['name' => '科忠']) // 数组
            ->searchRaw('email=xxxxx') // 也可以手写复杂表达式
            ->within(['contact', 'form']) // 指定搜索的应用，如果不指定，默认是 model 里指定的
            ->where('mobile', '15609008651')
            ->where('id', ['>', '10000'])
            ->whereIn('id', ['4720898', '4687028'])
            ->orderBy('id', 'desc')
            ->paginate(15);
```

将得到：
```
CloudsearchSearch {#96 ▼
  -client: CloudsearchClient {#97 ▶}
  -indexes: array:2 [▼
    0 => "contact"
    1 => "form"
  ]
  -summary: []
  -clauseConfig: ""
  -format: "json"
  -start: 0
  -hits: 20
  -sort: array:1 [▼
    "id" => "-"
  ]
  -filter: "mobile15 AND id>10000 AND (id=4720898 OR id=4687028)"
  -aggregate: []
  -distinct: []
  -fetches: []
  -rerankSize: 200
  -query: "name:科忠 AND email=xxxxx"
  -formulaName: ""
  -firstFormulaName: ""
  -kvpair: ""
  -QPName: []
  -functions: []
  -customParams: []
  -scrollId: null
  -searchType: ""
  -scroll: null
  -path: "/search"
}
```
