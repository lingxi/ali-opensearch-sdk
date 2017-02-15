<?php

namespace Lingxi\AliOpenSearch;

use Illuminate\Support\Facades\Config;
use Laravel\Scout\Events\ModelsImported;
use Laravel\Scout\SearchableScope as ScoutSearchableScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class SearchableScope extends ScoutSearchableScope
{
    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(EloquentBuilder $builder)
    {
        $builder->macro('searchable', function (EloquentBuilder $builder) {
            $builder->chunk(Config::get('scout.unsearchable.count', 100), function ($models) use ($builder) {
                $models->searchable();

                event(new ModelsImported($models));
            });
        });

        $builder->macro('unsearchable', function (EloquentBuilder $builder) {
            $builder->chunk(Config::get('scout.unsearchable.count', 100), function ($models) use ($builder) {
                $models->unsearchable();
            });
        });
    }
}
