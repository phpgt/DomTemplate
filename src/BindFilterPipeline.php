<?php
namespace GT\DomTemplate;

use Closure;

class BindFilterPipeline {
	/** @var array<class-string<BindFilter>, BindFilter> */
	private array $filters = [];

	public function register(BindFilter $filter):void {
		unset($this->filters[$filter::class]);
		$this->filters[$filter::class] = $filter;
	}

	/** @return array{0:string, 1:mixed} */
	public function apply(string $expression, mixed $value):array {
		$parts = explode(" ", $expression, 2);
		if(isset($parts[1]) && str_starts_with(ltrim($parts[1]), "|")) {
			throw new InvalidBindFilterExpressionException("", $expression);
		}
		$binding = explode("=", $parts[0], 2);
		$chain = explode("|", $binding[0]);
		$bindKey = array_shift($chain);
		if($value instanceof BindableValue) {
			$value = $chain ? $value->rawValue : $value->getBindingValue();
		}
		$callbacks = [];
		foreach($chain as $filterName) {
			if(!preg_match('/^[a-z][a-z0-9_]*$/iD', $filterName)) {
				throw new InvalidBindFilterExpressionException($filterName, $expression);
			}
			if(!$this->filters) {
				throw new BindFilterNotRegisteredException($filterName, $expression);
			}
			$callback = $this->getFilter($filterName);
			if(!$callback) {
				throw new BindFilterNotFoundException($filterName, $expression);
			}
			$callbacks[] = $callback;
		}

		foreach($callbacks as $callback) {
			$value = $callback($value);
		}

		$unfilteredExpression = $bindKey;
		if(isset($binding[1])) {
			$unfilteredExpression .= "=" . $binding[1];
		}
		if(isset($parts[1])) {
			$unfilteredExpression .= " " . $parts[1];
		}

		return [$unfilteredExpression, $value];
	}

	private function getFilter(string $name):?Closure {
		foreach(array_reverse($this->filters) as $filter) {
			if($callback = $filter->getFilter($name)) {
				return $callback;
			}
		}

		return null;
	}
}
