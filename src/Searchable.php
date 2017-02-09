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
}
