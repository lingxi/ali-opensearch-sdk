# ali-opensearch-sdk

应用层，基于 laravel scout 实现：https://laravel.com/docs/5.4/scout#custom-engines

scout 默认引擎是 algolia：https://www.algolia.com

开源的，大家常用 Elasticsearch：https://laracasts.com/discuss/channels/general-discussion/looking-for-a-search-engine-for-my-laravel-app?page=1

阿里云有开放搜索服务：https://help.aliyun.com/document_detail/29104.html?spm=5176.doc35261.6.539.qrzcjR

看文档相比自己搭 Elasticsearch 有以下优势：

- 不用买服务器、搭环境、主从、容灾、维护...这是最重要的原因（实际上看似开放搜索要收费，当时算上人工和服务器成本，自己搭建基础服务贵太多了）
- 开放搜索可以从 RDS 自动同步数据，这样就不用在应用里做数据同步了（实际上，对灵析来说，因为既有 laravel 又有 tp，要做好数据同步非常麻烦）
- 据称，开放搜索比 ElasticSearch 开源系统的QPS高4倍，查询延迟低4倍

---

## 初始化

```shell
composer require lingxi/ali-opensearch-sdk
```

## 配置

```php
'scout' => [
    'driver' => 'opensearch',
    'prefix' => '',
],
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

use Lingxi\AliOpenSearch\Searchable;

class FormFill extends BaseModel
{
    use Searchable;

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        // 也可以在使用时通过 Contact::within(['contacts', 'forms']) 指定
        return 'contacts';
    }

    public function toSearchableDocCallbacks($actions = ['update', 'delete'])
    {
        $callbacks = [
            'form_fills' => []
        ];

        if (is_array($this->data)) {
            if (in_array('update', $actions)) {
                $callbacks['form_fills']['update'] = function () {
                    $result = [];

                    foreach ($this->data as $key => $value) {
                        $value = $this->unserialize($value);
                        $result[] = [
                            'cmd' => 'update',
                            'fields' => [
                                'id' => bin2hex(md5($this->id . $this->team_id . $key)),
                                'team_id' => $this->team_id,
                                'form_id' => $this->form_id,
                                'formfill_id' => $this->id,
                                'contact_id' => $this->contact_id,
                                'broadcast_channel_id' => $this->broadcast_channel_id,
                                'create_time' => $this->create_time ? $this->create_time->timestamp : 0,
                                'update_time' => $this->update_time ? $this->update_time->timestamp : 0,
                                'key' => $key,
                                'value' => is_array($value) ? join(' ', $value) : $value,
                            ],
                        ];
                    }

                    return $result;
                };
            }

            if (in_array('delete', $actions)) {
                $callbacks['form_fills']['delete'] = function () {
                    return self::search(['formfill_id' => $this->id])
                        ->get()
                        ->map(function ($item) {
                            return [
                                'cmd' => 'delete',
                                'fields' => [
                                    'id' => $item['id'],
                                ],
                            ];
                        })
                        ->toArray();
                };
            }
        }

        return $callbacks;
    }

    public function getSearchableFields()
    {
        return ['id', 'formfill_id'];
    }
}
```

开始使用：

```php
<?php

// $result 是主键数组，搜到主键数组后，再去数据库里 whereIn 查真正想要的东西
$result = Contact::search(['name' => '科忠']) // 数组
            ->searchRaw('email=xxxxx') // 也可以手写复杂表达式
            ->within(['contact', 'form']) // 指定搜索的应用，如果不指定，默认是 model 里指定的
            ->where('mobile', '15609008651')
            ->where('id', ['>', '10000'])
            ->whereIn('id', ['4720898', '4687028'])
            ->orderBy('id', 'desc')
            ->paginate(15);
```

builder 过程中将会构造出：
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
  -filter: "mobile=15609008651 AND id>10000 AND (id=4720898 OR id=4687028)"
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

## 扩展

在 `Lingxi\AliOpenSearch\ExtendedBuilder` 扩展 scout 的 builder，别忘了在 `Lingxi\AliOpenSearch\QueryBuilder` 将你扩展的转化为 opensearch 需要的参数
