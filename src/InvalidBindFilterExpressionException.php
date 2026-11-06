<?php
namespace GT\DomTemplate;

class InvalidBindFilterExpressionException extends BindFilterException {
	public function __construct(string $filterName, string $expression) {
		parent::__construct($filterName, $expression,
			"Invalid filter name \"$filterName\" in expression \"$expression\". "
			. "Each pipe must be followed by a filter name starting with a letter, "
			. "containing only letters, numbers or underscores.",
		);
	}
}
