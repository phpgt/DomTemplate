<?php
namespace GT\DomTemplate;

class BindFilterNotRegisteredException extends BindFilterException {
	public function __construct(string $filterName, string $expression) {
		parent::__construct($filterName, $expression,
			"No BindFilter instance is registered for expression \"$expression\". "
			. "Call Binder::registerBindFilter() before binding filtered values.",
		);
	}
}
