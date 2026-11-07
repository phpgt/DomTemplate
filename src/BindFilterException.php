<?php
namespace GT\DomTemplate;

class BindFilterException extends DomTemplateException {
	public function __construct(
		public readonly string $filterName,
		public readonly string $expression,
		string $message,
	) {
		parent::__construct($message);
	}
}
