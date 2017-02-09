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
        $this->opensearch = $opensearch;
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
        return $this->opensearch->search('lingxi', 'name:科忠', ['limit' => $builder->limit]);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {

    }

    public function map($results, $model)
    {

    }
}
