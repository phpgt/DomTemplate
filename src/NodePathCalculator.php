<?php
namespace Gt\DomTemplate;

use Gt\Dom\Element;
use Gt\Dom\Node;
use Stringable;

class NodePathCalculator implements Stringable {
	public function __construct(
		private readonly Node|Element $element
	) {
	}

	public function __toString():string {
		$path = "";
		/** @var Element $context */
		$context = $this->element;

		do {
			$contextPath = strtolower($context->tagName);

			$attrPath = "";
			if($dataTemplateParent = $context->getAttribute(ListElement::ATTRIBUTE_LIST_PARENT)) {
				$attrPath .= "@"
					. ListElement::ATTRIBUTE_LIST_PARENT
					. "='$dataTemplateParent'";
			}

			if($elementId = $context->id) {
				if($attrPath) {
					$attrPath .= " and ";
				}
				$attrPath .= "@id='$elementId'";
			}

			foreach($context->classList as $class) {
				if($attrPath) {
					$attrPath .= " and ";
				}

				$attrPath .= "contains(concat(' ',normalize-space(@class),' '),' $class ')";
			}

			if($attrPath) {
				$contextPath .= "[$attrPath]";
			}

			$path = "/" . $contextPath . $path;
			$context = $context->parentElement;
		}
		while($context instanceof Element);

		return $path;
	}
}
