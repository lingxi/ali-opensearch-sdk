<?php

namespace Lingxi\AliOpenSearch\Query;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lingxi\AliOpenSearch\Helper\Whenable;

class QueryStructureBuilder
{
    use Whenable;

	public $wheres = [];

    protected $grammar;

	protected $bindings = [
		'where' => [],
	];

	protected $operators = [':'];

	protected function __construct(Grammar $grammar = null)
	{
		$this->grammar = $grammar ?: new Grammar;
	}

	public static function make()
	{
		return new static;
	}

	public function toSql()
	{
		return $this->grammar->compileSelect($this);
	}

	public function where($column, $operator = null, $value = null, $boolean = 'and')
	{
		if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // 暂时把所有的操作都转化为 =，因为对于 opensearch 来说只有这么一个.
        if (! in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = [$operator, ':'];
        }

        // 处理普通情况
        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        $this->addBinding($value, 'where');

        return $this;
	}

	public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

	protected function addArrayOfWheres($column, $boolean, $method = 'where')
    {
        return $this->whereNested(function ($query) use ($column, $method) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    call_user_func_array([$query, $method], $value);
                } else {
                    $query->$method($key, ':', $value);
                }
            }
        }, $boolean);
    }

    protected function whereNested(Closure $callback, $boolean = 'and')
    {
        $query = $this->forNestedWhere();

        call_user_func($callback, $query);

        return $this->addNestedWhereQuery($query, $boolean);
    }

    protected function forNestedWhere()
    {
        return static::make();
    }

    protected function addNestedWhereQuery($query, $boolean = 'and')
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getBindings(), 'where');
        }

        return $this;
    }

    protected function addBinding($value, $type = 'where')
    {
        if (! array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    protected function getBindings()
    {
        return Arr::flatten($this->bindings);
    }

    protected function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, ':'];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Opensearch 只支持索引等于这一个操作
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return is_null($value) && $isOperator && ! in_array($operator, ['=']);
    }
}
