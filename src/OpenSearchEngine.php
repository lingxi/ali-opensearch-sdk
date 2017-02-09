<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class OpenSearchEngine extends Engine
{
    /**
     * The OpenSearch client.
     *
     * @var \Lingxi\AliOpenSearch\OpenSearchClient
     */
    protected $opensearch;

    /**
     * Create a new engine instance.
     *
     * @param  \Lingxi\AliOpenSearch\OpenSearchClient $opensearch
     * @return void
     */
    public function __construct(OpenSearchClient $opensearch)
    {
        $this->opensearch = $opensearch->getCloudSearchSearch();
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
        return (new QueryBuilder($this->opensearch))->build($builder);
    }

    protected function performSearch(Builder $builder)
    {
        return json_decode($builder->search(), true);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map($results, $model)
    {
        // 只返回主键，外面去查数据库，爱干嘛干嘛
        return $this->mapIds($results);

        // return $model->whereIn(
        //     $model->getQualifiedKeyName(), $this->mapIds($results)
        // )->get()->keyBy($model->getKeyName());
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
        return $results['result']['items']['total'];
    }
}
