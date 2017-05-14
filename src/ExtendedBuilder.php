<?php

namespace Lingxi\AliOpenSearch;

use Illuminate\Pagination\Paginator;
use Lingxi\AliOpenSearch\Helper\Whenable;
use Laravel\Scout\Builder as ScoutBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExtendedBuilder extends ScoutBuilder
{
    use Whenable;

    /**
     * The model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The query expression.
     *
     * @var mixed
     */
    public $query;

    /**
     * Optional callback before search execution.
     *
     * @var string
     */
    public $callback;

    /**
     * The custom index specified for the search.
     *
     * @var string
     */
    public $index;

    /**
     * The "where" constraints added to the query.
     *
     * @var array
     */
    public $filters = [];

    /**
     * The "order" that should be applied to the search.
     *
     * @var array
     */
    public $orders = [];

    /**
     * The "limit" that should be applied to the search.
     *
     * @var int
     */
    public $limit;

    /**
     * The current page. "start" in open search query.
     *
     * @var int
     */
    public $page;

    /**
     * Custom filter strings.
     *
     * @var array
     */
    public $rawFilters = [];

    /**
     * Custom query strings.
     *
     * @var array
     */
    public $rawQuerys = [];

    /**
     * Fetching fields from opensearch.
     *
     * @var array
     */
    public $fields = [];

    /**
     * Distinct.
     *
     * @var array
     */
    public $distincts = [];

    /**
     * Aggregates.
     *
     * @var array
     */
    public $aggregates = [];

    /**
     * Pair
     *
     * @var string
     */
    public $pair;

    /**
     * Create a new search builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $query
     * @param  Closure  $callback
     * @return void
     */
    public function __construct($model, $query, $callback = null)
    {
        $this->model = $model;
        $this->query = $query;
        $this->callback = $callback;

        $this->select();
    }

    /**
     * Specify a custom index to perform this search on.
     *
     * @param  string  $index
     * @return $this
     */
    public function within($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Add a constraint to the search filter.
     *
     * @param  mixed  $field
     * @param  mixed  $value
     * @return $this
     */
    public function filter($field, $value = null)
    {
        if (is_array($field)) {
            $this->filters[] = $field;
        } else {
            if (! is_array($value)) {
                $value = [$field, '=', $value];
            } else {
                array_unshift($value, $field);
            }

            $this->filters[] = $value;
        }

        return $this;
    }

    /**
     * Set the "limit" for the search query.
     *
     * @param  int  $limit
     * @return $this
     */
    public function take($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function forPage($page, $perPage = 20)
    {
        $this->page = $page;
        $this->limit = $perPage;

        return $this;
    }

    /**
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  array  $values
     * @return $this
     */
    public function filterIn($field, array $values = [])
    {
        $this->rawFilters[] = '(' . collect($values)->map(function($item) use ($field) {
            $item = !is_numeric($item) && is_string($item) ? '"' . $item . '"' : $item;
            return $field . '=' . $item;
        })->implode(' OR ') . ')';

        return $this;
    }

    /**
     * Add an "order" for the search query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Add an default rank to order.
     *
     * @param  string  $direction
     * @return $this
     */
    public function orderByRank($direction = 'desc')
    {
        $this->orders[] = [
            'column' => 'RANK',
            'direction' => strtolower($direction) == 'desc' ? 'desc' : 'asc',
        ];

        return $this;
    }

    public function select($fields = null)
    {
        if (empty($fields)) {
            $fields = $this->model->getSearchableFields();

            if (! is_array($fields)) {
                $fields = explode(',', $fields);
            }
        }

        $this->fields = $fields;

        return $this;
    }

    public function filterRaw($rawFilter)
    {
        $this->rawFilters[] = $rawFilter;

        return $this;
    }

    public function searchRaw($rawQuery)
    {
        $this->rawQuerys[] = $rawQuery;

        return $this;
    }

    public function addDistinct()
    {
        $this->distincts[] = func_get_args();

        return $this;
    }

    public function addAggregate()
    {
        $this->aggregates[] = func_get_args();

        return $this;
    }

    public function setPair($pair)
    {
        $this->pair = $pair;

        return $this;
    }

    /**
     * Get the keys of search results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function keys()
    {
        return $this->engine()->keys($this);
    }

    /**
     * Get the first result from the search.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function first()
    {
        return $this->get()->first();
    }

    /**
     * Get the results of the search.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get()
    {
        return $this->engine()->get($this);
    }

    /**
     * Get the facet from aggregate.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function facet($key)
    {
        return $this->engine()->facet($key, $this);
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $this->forPage($page, $perPage);

        $results = Collection::make($engine->map(
            $rawResults = $engine->paginate($this, $perPage, $page), $this->model
        ));

        $paginator = (new LengthAwarePaginator($results, $engine->getTotalCount($rawResults), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]));

        return $paginator->appends('query', $this->query);
    }

    /**
     * Get the engine that should handle the query.
     *
     * @return mixed
     */
    protected function engine()
    {
        return $this->model->searchableUsing();
    }
}
