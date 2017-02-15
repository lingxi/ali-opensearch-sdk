<?php

namespace Lingxi\AliOpenSearch;

use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;
use Lingxi\AliOpenSearch\Console\FlushCommand;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    public function register()
    {
        $this->app->singleton(OpenSearchClient::class, function () {
            return new OpenSearchClient($this->app['config']->get('scout.opensearch'));
        });

        $this->app[EngineManager::class]->extend('opensearch', function () {
            return new OpenSearchEngine($this->app[OpenSearchClient::class]);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushCommand::class,
            ]);
        }
    }
}
