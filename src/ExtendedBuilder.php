<?php

namespace Lingxi\AliOpenSearch;

class ExtendedBuilder extends \Laravel\Scout\Builder
{
    public $rawWheres = [];

    public $rawQuerys = [];

    public $fields = [];

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
        parent::__construct($model, $query, $callback);

        $this->select();
    }

    /**
     * @todo 这里先只是处理 = 的情况，需求来了就补上
     *
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return $this
     */
    public function where($field, $value)
    {
        if (! is_array($value)) {
            $value = ['=', $value];
        }

        $this->wheres[$field] = $value;

        return $this;
    }

    /**
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  array  $values
     * @return $this
     */
    public function whereIn($field, array $values = [])
    {
        $this->rawWheres[] = '(' . collect($values)->map(function($item) use ($field) {
            $item = !is_numeric($item) && is_string($item) ? '"' . $item . '"' : $item;
            return $field . '=' . $item;
        })->implode(' OR ') . ')';

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

        $this->fields = array_merge($this->fields, $fields);

        return $this;
    }

    public function whereRaw($rawWhere)
    {
        $this->rawWheres[] = $rawWhere;

        return $this;
    }

    public function searchRaw($rawQuery)
    {
        $this->rawQuerys[] = $rawQuery;

        return $this;
    }
}
