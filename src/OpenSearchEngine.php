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
     * Add the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function add($models)
    {
        // Get opensearch index client.
        $doc = $this->getCloudSearchDoc($models);

        foreach ($this->getSearchableData($models, ['update']) as $name => $value) {
            if (! empty($value['update'])) {
                $doc->add($value['update'], $name);
            }
        }
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models)
    {
        // Get opensearch index client.
        $doc = $this->getCloudSearchDoc($models);

        foreach ($this->getSearchableData($models) as $name => $value) {
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
        $doc = $this->getCloudSearchDoc($models);

        /*
        |----------------------------------------------------------------------
        | 有删除逻辑的走删除逻辑，没有删除逻辑的直接走 id 删除
        |----------------------------------------------------------------------
        |
        | 同时， opensearch 中的应用多表结构，适用于水平分库，或者提取字段专门建立的福鼠表
        | 所以对于数据的删除，只是直接删除 id.
        |
        */
        foreach ($this->getSearchableData($models, ['delete']) as $name => $value) {
            if (! empty($value['delete'])) {
                $toBeDeleteData = $value['delete'];
            } else {
                $toBeDeleteData = $models->map(function ($model) {
                    return [
                        'cmd' => 'delete',
                        'fields' => [
                            'id' => $model->id,
                        ]
                    ];
                });
            }

            if (! empty($toBeDeleteData)) {
                $doc->delete($toBeDeleteData, $name);
            }
        }
    }

    /**
     * Equals remove
     */
    public function remove($models)
    {
        return $this->delete($models);
    }

    /**
     * 获取模型的操作数据
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @param array $actions
     * @return array
     */
    protected function getSearchableData($models, array $actions = ['delete', 'update'])
    {
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
        | 插入数据，一般来说是第一次向 opensearch 添加数据时会使用，　所以，过滤掉 delete 操作
        |
        */
        $data = [];
        foreach ($tableNames as $name) {
            // Delete 就是需要在 update 前面
            foreach ($actions as $action) {
                $data[$name][$action] = [];
            }
        }

        foreach ($models as $model) {
            $callbacks = $model->toSearchableDocCallbacks($actions);

            foreach ($callbacks as $name => $callback) {
                if (! empty($callback)) {
                    foreach ($actions as $action) {
                        if (isset($callback[$action])) {
                            $data[$name][$action] = array_merge($data[$name][$action], call_user_func($callback[$action]));
                        }
                    }
                }
            }
        }

        return $data;
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
        $fields = $model->getSearchableFields();

        if (is_array($fields)) {
            return collect(array_map(function ($item) use ($fields) {
                $result = [];
                foreach ($fields as $field) {
                    $result[$field] = $item[$field];
                }
                return $result;
            }, $results['result']['items']));
        }

        return $this->mapIds($results, $field);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @param  string  $field
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results, $field = 'id')
    {
        return collect($results['result']['items'])->pluck($field)->values();
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

    /**
     * @param $models
     * @return Sdk\CloudsearchDoc
     */
    private function getCloudSearchDoc($models)
    {
        return $this->opensearch->getCloudSearchDoc($models->first()->searchableAs());
    }
}
