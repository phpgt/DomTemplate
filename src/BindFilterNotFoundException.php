<?php
namespace GT\DomTemplate;

class BindFilterNotFoundException extends BindFilterException {
	public function __construct(string $filterName, string $expression) {
		parent::__construct($filterName, $expression,
			"Bind filter \"$filterName\" could not be found for expression \"$expression\". "
			. "Define a public instance method named $filterName on a registered BindFilter class.",
		);
	}
}
