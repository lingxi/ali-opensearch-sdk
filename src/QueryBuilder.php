<?php

namespace Lingxi\AliOpenSearch;

use Lingxi\AliOpenSearch\Sdk\CloudsearchSearch;
use Illuminate\Support\Facades\Config;

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
        $this->query($builder->query, $builder->rawQuerys);
        $this->filters($builder->wheres, $builder->rawWheres);
        $this->hit($builder->limit ?: 20);
        $this->sort($builder->orders);

        $this->opensearch->setFormat('json');

        return $this->opensearch;
    }

    /**
     * 搜索的应用
     * @param  array|string $index
     * @return null
     */
    protected function index($index)
    {
        $prefix = Config::get('scout.prefix');

        if (is_array($index)) {
            foreach ($index as $key => $value) {
                $this->opensearch->addIndex($prefix . $value);
            }
        } else {
            $this->opensearch->addIndex($prefix . $index);
        }
    }

    /**
     * 过滤filter子句
     * @see https://help.aliyun.com/document_detail/29158.html
     * @param  array $wheres
     * @return null
     */
    protected function filters(array $wheres, array $rawWheres)
    {
        foreach ($wheres as $key => $value) {
            $operator = $value[0];
            $value    = $value[1];
            if (!is_numeric($value) && is_string($value)) {
                // literal类型的字段值必须要加双引号，支持所有的关系运算，不支持算术运算
                $value = '"' . $value . '"';
            }

            $this->opensearch->addFilter($key . $operator . $value, 'AND');
        }

        foreach ($rawWheres as $key => $value) {
            $this->opensearch->addFilter($value, 'AND');
        }
    }

    /**
     * 查询query子句
     * @see https://help.aliyun.com/document_detail/29157.html
     * @param  array|string $query
     * @return null
     */
    protected function query($query, $rawQuerys)
    {
        if (!is_string($query)) {
            $query = collect($query)
                ->map(function ($value, $key) {
                    return $key . ':' . $value;
                })
                ->implode(' AND ');
        }

        $query = $rawQuerys ? $query . ' AND ' . implode($rawQuerys, ' AND ') : $query;

        $this->opensearch->setQueryString($query);
    }

    /**
     * 返回文档的最大数量
     * @see https://help.aliyun.com/document_detail/29156.html
     * @param  integer $limit
     * @return null
     */
    protected function hit(int $limit)
    {
        $this->opensearch->setHits($limit);
    }

    /**
     * 排序sort子句
     * @see https://help.aliyun.com/document_detail/29159.html
     * @param  array $orders
     * @return null
     */
    protected function sort(array $orders)
    {
        foreach ($orders as $key => $value) {
            $this->opensearch->addSort($value['column'], $value['column'] == 'asc' ? CloudsearchSearch::SORT_INCREASE : CloudsearchSearch::SORT_DECREASE);
        }
    }
}
