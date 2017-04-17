# ali-opensearch-sdk

应用层，基于 laravel scout 实现：https://laravel.com/docs/5.4/scout#custom-engines

scout 默认引擎是 algolia：https://www.algolia.com

开源的，大家常用 Elasticsearch：https://laracasts.com/discuss/channels/general-discussion/looking-for-a-search-engine-for-my-laravel-app?page=1

阿里云有开放搜索服务：https://help.aliyun.com/document_detail/29104.html?spm=5176.doc35261.6.539.qrzcjR

看文档相比自己搭 Elasticsearch 有以下优势：

- 不用买服务器、搭环境、主从、容灾、维护...这是最重要的原因（实际上看似开放搜索要收费，当时算上人工和服务器成本，自己搭建基础服务贵太多了）
- 开放搜索可以从 RDS 自动同步数据，这样就不用在应用里做数据同步了（实际上，对灵析来说，因为既有 laravel 又有 tp，要做好数据同步非常麻烦）
- 据称，开放搜索比 ElasticSearch 开源系统的QPS高4倍，查询延迟低4倍

## 安装

```shell
composer require lingxi/ali-opensearch-sdk
```

## 配置

在你的 scout.php

```php
<?php

return [
    'driver' => 'opensearch',

    'prefix' => '', // 应用前缀

    'queue' => true, // 是否开启队列同步数据

    'opensearch' => [

        'access_key_id'     => env('OPENSEARCH_ACCESS_KEY'),

        'access_key_secret' => env('OPENSEARCH_ACCESS_SECRET'),

        'debug'             => env('OPENSEARCH_DEBUG'),

    ],

    'count' => [

        'unsearchable' => 20, // 一次性删除文档的 Model 数量

        'searchable' => 20, // 一次性同步文档的 Model 数量

        'updateSearchable' => 20, // 一次性更新(先删除，再更新)文档的 Model 数量

    ],
]
```

## 注册服务

```php
Laravel\Scout\ScoutServiceProvider::class,
Lingxi\AliOpenSearch\OpenSearchServiceProvider::class,
```

---

## 使用

请先阅读：https://laravel.com/docs/5.3/scout

在 Model 里添加 Searchable Trait：

```php
<?php

namespace App\Models;

use Lingxi\AliOpenSearch\Searchable;

class User extends Model
{
    use Searchable;

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'user';
    }

    public function toSearchableDocCallbacks($actions = ['update', 'delete'])
    {
        throw new Exception('这个应用不需要手动维护数据');
    }

    public function getSearchableFields()
    {
        return 'id';
    }
}
```

开始使用：

简单搜索

```php
<?php

$result = User::search(['name' => 'lingxi'])
    ->select([
        'id',
        'name',
        'age',
    ])
    ->filter(['age', '<', '30'])
    ->filter(['age', '>', '18'])
    ->orderBy('id', 'desc')
    ->paginate(15);
```

更为复杂的情况就是对搜索添加的构造，仿照 laravel model/builder 的思想写了一个对 Opensearch 的 查询构造器.

> 根据条件动态的搜索, 基本和 eloquent 提供的数据库查询保持一致.

```php
<?php

use Lingxi\AliOpenSearch\Query\QueryStructureBuilder as Query;

$q = $_GET['query'];

$query = Query::make()
    ->where(function ($query) use ($q) {
            return $query->where('name', $q)
        ->when(strpos($q, "@") !== false && $q != "@", function ($query) use ($q) {
            return $query->orWhere('email', $q);
        })
        ->when(is_numeric($q), function ($query) use ($q) {
            return $query->orWhere('mobile', $q);
        });
    });

$users = User::search($query)
    ->filter('age', 18)
    ->take(5)
    ->get();
```

### 数据的维护

有很多情况可能无法直接使用 opensearch 直接同步 RDS 的数据，那么就需要在应用用去手动维护。

这个时候实现 toSearchableDocCallbacks 这个方法，向 opensearch 提供删除，修改的数据。

使用可以先阅读源码，有详细的注释，这边还没有想出最佳实践。

