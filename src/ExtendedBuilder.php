<?php

namespace Lingxi\AliOpenSearch;

class ExtendedBuilder extends \Laravel\Scout\Builder
{
    public $rawWheres = [];
    public $rawQuerys = [];

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
        $this->wheres[$field] = ['=', $value];

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

    public function whereRaw(string $rawWhere)
    {
        $this->rawWheres[] = $rawWhere;

        return $this;
    }

    public function searchRaw(string $rawQuery)
    {
        $this->rawQuerys[] = $rawQuery;

        return $this;
    }
}
