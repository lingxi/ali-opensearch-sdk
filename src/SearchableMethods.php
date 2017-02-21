<?php

namespace Lingxi\AliOpenSearch;

use Illuminate\Database\Eloquent\Collection;

trait SearchableMethods
{
    public function makeSearchable()
    {
        $this->queueMakeSearchable(new Collection([$this]));
    }

    public function updateSearchable()
    {
        $this->queueUpdateSearchable(new Collection([$this]));
    }

    public function removeSearchable()
    {
        $this->queueRemoveFromSearch(new Collection([$this]));
    }
}
