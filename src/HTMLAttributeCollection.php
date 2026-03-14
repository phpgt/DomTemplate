<?php
namespace Gt\DomTemplate;

use Gt\Dom\Element;
use Gt\Dom\XPathResult;

class HTMLAttributeCollection {
	public function find(Element $context):XPathResult {
		return $context->ownerDocument->evaluate(
			"descendant-or-self::*"
			. "[@*[starts-with(name(), 'data-bind')] "
			. "or (@data-element and @data-element != '')]",
			$context
		);
	}
}
