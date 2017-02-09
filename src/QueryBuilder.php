<?php

namespace Lingxi\AliOpenSearch;

/**
 * laravel eloquent builder scheme to opensearch scheme
 */
class QueryBuilder
{
    protected $opensearch;

    public function __construct(OpenSearchClient $opensearch)
    {
        $this->opensearch = $opensearch;
    }

    public function build($builder)
    {
        $this->index($builder->index ?: $builder->model->searchableAs());
        $this->query($builder->query);
        $this->filters($builder->wheres);
        $this->limit($builder->limit ?: 20);

        $this->opensearch->setFormat('json');

        return $this->opensearch;
    }

    protected function index($index)
    {
        $this->opensearch->addIndex($index);
    }

    /**
     * 过滤filter子句
     * @see https://help.aliyun.com/document_detail/29158.html
     * @param  arraty $wheres
     * @return null
     */
    protected function filters($wheres)
    {
        foreach ($wheres as $key => $value) {
            $operator = $value[0];
            $value = $value[1];
            if (!is_numeric($value) && is_string($value)) {
                // literal类型的字段值必须要加双引号，支持所有的关系运算，不支持算术运算
                $value = '"' . $value . '"';
            }
            $this->opensearch->addFilter($key . $operator . $value, 'AND');
        }
    }

    /**
     * 查询query子句
     * @see https://help.aliyun.com/document_detail/29157.html
     * @param  array|string $query
     * @return null
     */
    protected function query($query)
    {
        if (!is_string($query)) {
            $query = collect($query)->map(function ($value, $key) {
                return $key . ':' . $value;
            })->implode(' AND ');
        }

        $this->opensearch->setQueryString($query);
    }

    protected function limit($limit)
    {
        $this->opensearch->setHits($limit);
    }
}
