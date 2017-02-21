<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\EngineManager;
use Laravel\Scout\ModelObserver;
use Lingxi\AliOpenSearch\Jobs\MakeSearchable;
use Lingxi\AliOpenSearch\Jobs\RemoveSearchable;
use Lingxi\AliOpenSearch\Jobs\UpdateSearchable;
use Laravel\Scout\Searchable as ScoutSearchable;
use Illuminate\Support\Collection as BaseCollection;

trait Searchable
{
    use ScoutSearchable;

    use SearchableMethods;

    abstract public function toSearchableDocCallbacks($actions = ['update', 'delete']);

    public static function bootSearchable()
    {
        static::addGlobalScope(new SearchableScope);

        static::observe(new ModelObserver);

        (new static)->registerSearchableMacros();
    }

    public function registerSearchableMacros()
    {
        $self = $this;

        BaseCollection::macro('searchable', function () use ($self) {
            $self->queueMakeSearchable($this);
        });

        BaseCollection::macro('unsearchable', function () use ($self) {
            $self->queueRemoveFromSearch($this);
        });

        BaseCollection::macro('updateSearchable', function () use ($self) {
            $self->queueUpdateSearchable($this);
        });
    }

    public function getSearchableFields()
    {
        return 'id';
    }

    /**
     * Dispatch the job to make the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function queueMakeSearchable($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if (! config('scout.queue')) {
            return $models->first()->searchableUsing()->add($models);
        }

        dispatch((new MakeSearchable($models))
            ->onQueue($models->first()->syncWithSearchUsingQueue())
            ->onConnection($models->first()->syncWithSearchUsing()));
    }

    /**
     * Dispatch the job to update the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function queueUpdateSearchable($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if (! config('scout.queue')) {
            return $models->first()->searchableUsing()->update($models);
        }

        dispatch((new UpdateSearchable($models))
            ->onQueue($models->first()->syncWithSearchUsingQueue())
            ->onConnection($models->first()->syncWithSearchUsing()));
    }

    /**
     * Dispatch the job to remove the given models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function queueRemoveFromSearch($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        if (! config('scout.queue')) {
            return $models->first()->searchableUsing()->remove($models);
        }

        dispatch((new RemoveSearchable($models))
            ->onQueue($models->first()->syncWithSearchUsingQueue())
            ->onConnection($models->first()->syncWithSearchUsing()));
    }

    /**
     * Get the Scout engine for the model.
     * @fixme [Scout2.0] \Laravel\Scout\Searchable 里没有传参数，应该是个 bug
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
        return new ExtendedBuilder(new static, $query, $callback);
    }
}
