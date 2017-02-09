<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\EngineManager;

trait Searchable
{
    use \Laravel\Scout\Searchable;

    /**
     * Get the Scout engine for the model.
     *
     * @return mixed
     */
    public function searchableUsing()
    {
        return app(EngineManager::class)->engine('opensearch');
    }

    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  Closure  $callback
     * @return Lingxi\AliOpenSearch\ExtendBuilder
     */
    public static function search($query, $callback = null)
    {
        return new ExtendBuilder(new static, $query, $callback);
    }
}
