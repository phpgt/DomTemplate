<?php
namespace GT\DomTemplate;

class BindFilterNotFoundException extends BindFilterException {
	public function __construct(string $filterName, string $expression) {
		$methodName = "filter" . ucfirst($filterName);
		parent::__construct($filterName, $expression,
			"Bind filter \"$filterName\" could not be found for expression \"$expression\". "
			. "Define a public instance method named $methodName on the registered BindFilter.",
		);
	}
}
