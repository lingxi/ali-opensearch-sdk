应用层，基于 laravel scout 实现：https://laravel.com/docs/5.4/scout#custom-engines

scout 默认引擎是 algolia：https://www.algolia.com

开源的，大家常用 Elasticsearch：https://laracasts.com/discuss/channels/general-discussion/looking-for-a-search-engine-for-my-laravel-app?page=1

阿里云有开放搜索服务：https://help.aliyun.com/document_detail/29104.html?spm=5176.doc35261.6.539.qrzcjR

看文档相比自己搭 Elasticsearch 有以下优势：

- 不用买服务器、搭环境、主从、容灾、维护...这是最重要的原因（实际上看似开放搜索要收费，当时算上人工和服务器成本，自己搭建基础服务贵太多了）
- 开放搜索可以从 RDS 自动同步数据，这样就不用在应用里做数据同步了（实际上，对灵析来说，因为既有 laravel 又有 tp，要做好数据同步非常麻烦）
- 据称，开放搜索比 ElasticSearch 开源系统的QPS高4倍，查询延迟低4倍
