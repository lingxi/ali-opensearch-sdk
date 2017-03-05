<?php

namespace Lingxi\AliOpenSearch\Query;

/**
 * @todo 对于整个 opensearch 的查询语法来说，本身应该存在这么一套完整的语法构建，目前仅仅只处理了 query 部分
 */
class Grammar
{
    protected $selectComponents = [
        'wheres',
    ];

	public function compileSelect(QueryStructureBuilder $query)
    {
        $sql = trim($this->concatenate($this->compileComponents($query)));

        return $sql;
    }

    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    protected function compileComponents(QueryStructureBuilder $query)
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (! is_null($query->$component)) {
                $method = 'compile'.ucfirst($component);

                $sql[$component] = $this->$method($query, $query->$component);
            }
        }

        return $sql;
    }

    public function compileWheres(QueryStructureBuilder $query)
    {
        $sql = [];

        if (is_null($query->wheres)) {
            return '';
        }

        foreach ($query->wheres as $where) {
            $method = "where{$where['type']}";

            $sql[] = strtoupper($where['boolean']).' '.$this->$method($query, $where);
        }

        if (count($sql) > 0) {
            $sql = implode(' ', $sql);

            return $this->removeLeadingBoolean($sql);
        }

        return '';
    }

    protected function whereBasic(QueryStructureBuilder $query, $where)
    {
        $value = '\''.$where['value'].'\'';

        return $where['column'].$where['operator'].$value;
    }

    protected function whereNested(QueryStructureBuilder $query, $where)
    {
        $nested = $where['query'];

        return '('.$this->compileWheres($nested).')';
    }

    protected function removeLeadingBoolean($value)
    {
        return preg_replace('/AND |OR /i', '', $value, 1);
    }
}
