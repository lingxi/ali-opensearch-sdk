<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\Engines\Engine;
use Illuminate\Support\Facades\Event;
use Lingxi\AliOpenSearch\Query\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Database\Eloquent\Collection;
use Lingxi\AliOpenSearch\Events\DocSyncEvent;
use Lingxi\AliOpenSearch\Sdk\CloudsearchSearch;
use Lingxi\AliOpenSearch\Exception\OpensearchException;

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
     * The search result.
     *
     * @var array
     */
    protected $searchResult = [];

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
                try {
                    $this->waitASecond();
                    $doc->add($value['update'], $name);
                    $this->waitASecond();

                    Event::fire(new DocSyncEvent($models->first()->searchableAs(), $name, $value, 'add', true));
                } catch (OpensearchException $e) {
                    throw $e;
                }
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
                    try {
                        $this->waitASecond();
                        $doc->$method($items, $name);
                        $this->waitASecond();

                        Event::fire(new DocSyncEvent($models->first()->searchableAs(), $name, $value, $method, true));
                    } catch (OpensearchException $e) {
                        throw $e;
                    }
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
            if (array_key_exists('delete', $value)) {
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
                try {
                    $this->waitASecond();
                    $doc->delete($toBeDeleteData, $name);
                    $this->waitASecond();

                    Event::fire(new DocSyncEvent($models->first()->searchableAs(), $name, $value, 'delete', true));
                } catch (OpensearchException $e) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Sleep 100ms to avoid request frequently.
     *
     * 经过测试 200ms 比较稳定, 在请求前后分别停止 100ms
     *
     * @param  integer $microSeconds
     * @return null
     */
    protected function waitASecond($microSeconds = 100000)
    {
        usleep($microSeconds);
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
        // 获取应用的全部表名
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
            $data[$name] = [];
        }

        foreach ($models as $model) {
            $callbacks = $model->toSearchableDocCallbacks($actions);

            // delete 就是需要在 update 前面
            ksort($callbacks);

            foreach ($callbacks as $name => $callback) {
                if (! empty($callback)) {
                    foreach ($actions as $action) {
                        if (isset($callback[$action])) {
                            $data[$name][$action] = array_merge(
                                isset($data[$name][$action]) ? $data[$name][$action] : [],
                                call_user_func($callback[$action])
                            );
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
    public function search(ScoutBuilder $builder)
    {
        $searchKey = serialize($builder);

        if (! isset($this->searchResult[$searchKey])) {
            $this->searchResult[$searchKey] = $this->performSearch($this->buildLaravelBuilderIntoOpensearch($builder));
        }

        return $this->searchResult[$searchKey];
    }

    public function paginate(ScoutBuilder $builder, $perPage, $page)
    {
        $cloudSearchSearch = $this->buildLaravelBuilderIntoOpensearch($builder);

        return $this->performSearch($cloudSearchSearch);
    }

    protected function buildLaravelBuilderIntoOpensearch($builder)
    {
        return (new Builder($this->cloudsearchSearch))->build($builder);
    }

    protected function performSearch(CloudsearchSearch $search)
    {
        return $search->search();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed $results
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\Collection
     * @throws OpensearchException
     */
    public function map($results, $model)
    {
        $fields = $this->opensearch->getCloudSearchSearch()->getFetchFields();

        if (empty($fields)) {
            throw new OpensearchException('搜索字段不能为空');
        }

        if (count($fields) != 1) {
            return collect(array_map(function ($item) use ($fields) {
                $result = [];
                foreach ($fields as $field) {
                    $result[$field] = $item[$field];
                }
                return $result;
            }, $results['result']['items']));
        } else {
            $fields = $fields[0];
        }

        return $this->mapIds($results, $fields);
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

    /**
     * Get the results of the given query mapped onto models.
     *
     * @param  \Lingxi\AliOpenSearch\ScoutBuilder  $builder
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get(ScoutBuilder $builder)
    {
        return Collection::make($this->map(
            $this->search($builder), $builder->model
        ));
    }

    /**
     * Get the facet from search results.
     *
     * @param  string  $key
     * @param  ExtendedBuilder $builder
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function facet($key, ExtendedBuilder $builder)
    {
        return Collection::make($this->mapFacet($key, $this->search($builder)));
    }

    protected function mapFacet($key, $results)
    {
        $facets = $results['result']['facet'];

        foreach ($facets as $facet) {
            if ($facet['key'] == $key) {
                return $facet['items'];
            }
        }

        return [];
    }
}
