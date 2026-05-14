<?php
namespace GT\DomTemplate;

use GT\Dom\Element;
use GT\Dom\XPathResult;

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
