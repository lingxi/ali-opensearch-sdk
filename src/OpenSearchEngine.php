<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Lingxi\AliOpenSearch\Sdk\CloudsearchSearch;

class OpenSearchEngine extends Engine
{
    /**
     * The OpenSearch client.
     *
     * @var OpenSearchClient
     */
    protected $opensearch;

    /**
     * The OpenSearch client.
     *
     * @var \Lingxi\AliOpenSearch\Sdk\CloudsearchSearch
     */
    protected $cloudsearchSearch;

    /**
     * Create a new engine instance.
     *
     * @param  \Lingxi\AliOpenSearch\OpenSearchClient $opensearch
     * @return void
     */
    public function __construct(OpenSearchClient $opensearch)
    {
        $this->opensearch = $opensearch;

        $this->cloudsearchSearch = $opensearch->getCloudSearchSearch();
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @throws \AlgoliaSearch\AlgoliaException
     * @return void
     */
    public function update($models)
    {
        // Get opensearch index client.
        $doc = $this->opensearch->getCloudSearchDoc($models->first()->searchableAs());
        // 获取应用的部表名
        $tableNames = array_keys($models->first()->toSearchableDocCallbacks());
        /*
        |--------------------------------------------------------------------------
        | 构造数据，分两块
        |--------------------------------------------------------------------------
        |
        | 在 opensearch 中，添加(add)和更新(update)是一样的操作，都是存在即更新，不存在则创建，且作为客户端我们不需要知道这条文档是被添加了或删除了
        | 那么最稳健的做法是，如果数据 id 是数据主键（或者其他稳定数据），那么只需要在直接更新就可以了
        | 碰到自己构造的 id 情况，就需要手动处理找出数据源 id 之后，手动删除了
        |
        */
        $data = [];
        foreach ($tableNames as $name) {
            $data[$name]['delete'] = [];
            $data[$name]['update'] = [];
        }

        foreach ($models as $model) {
            $callbacks = $model->toSearchableDocCallbacks($doc);

            foreach ($callbacks as $name => $callback) {
                if (! empty($callback)) {
                    // Update 数据必须是有的。
                    $data[$name]['update'] = array_merge($data[$name]['update'], call_user_func($callback['update']));
                    // 当有特殊的删除逻辑
                    if (isset($callback['delete'])) {
                        $data[$name]['delete'] = array_merge($data[$name]['delete'], call_user_func($callback['delete']));
                    }
                }
            }
        }

        foreach ($data as $name => $value) {
            foreach ($value as $method => $items) {
                if (! empty($items)) {
                    $doc->$method($items, $name);
                }
            }
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {

    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($this->buildLaravelBuilderIntoOpensearch($builder));
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $builder = $this->buildLaravelBuilderIntoOpensearch($builder);
        $builder->setStartHit($perPage * ($page - 1));

        return $this->performSearch($builder);
    }

    protected function buildLaravelBuilderIntoOpensearch($builder)
    {
        return (new QueryBuilder($this->cloudsearchSearch))->build($builder);
    }

    protected function performSearch(CloudsearchSearch $search)
    {
        return json_decode($search->search(), true);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\Collection
     */
    public function map($results, $model)
    {
        // 只返回主键，外面去查数据库，爱干嘛干嘛
        return $this->mapIds($results);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['result']['items'])->pluck('id')->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['result']['total'];
    }
}
