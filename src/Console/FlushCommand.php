<?php

namespace Lingxi\AliOpenSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Lingxi\AliOpenSearch\Events\ModelsDeleted;

class FlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:flush {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush all of the model's records from the index";

    public function handle(Dispatcher $events)
    {
        $class = $this->argument('model');

        $model = new $class;

        $events->listen(ModelsDeleted::class, function ($event) use ($class) {
            $key = $event->models->last()->getKey();

            $this->line('<comment>Deleted ['.$class.'] models up to ID:</comment> '.$key);
        });

        $model::removeAllFromSearch();

        $this->info('All ['.$class.'] records have been flushed.');
    }
}
