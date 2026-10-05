<?php
namespace GT\DomTemplate;

use GT\Dom\Document;
use GT\Dom\Element;
use Throwable;

class ListElementCollection {
	/** @var array<string, ListElement> */
	private array $elementKVP;

	public function __construct(
		Document $document
	) {
		$this->elementKVP = [];
		$this->extractTemplates($document);
	}

	public function get(
		Element|Document $context,
		?string $templateName = null
	):ListElement {
		if($context instanceof Document) {
			$context = $context->documentElement;
		}

		if(!is_null($templateName) && $templateName !== "") {
			return $this->findNamedMatch($context, $templateName);
		}

		return $this->findMatch($context);
	}

	private function findNamedMatch(Element $context, string $templateName):ListElement {
		$match = null;
		foreach($this->elementKVP as $element) {
			if($element->getListItemName() !== $templateName) {
				continue;
			}

			try {
				$parent = $element->getListItemParent();
			}
			catch(Throwable) {
				continue;
			}

			if($parent !== $context && !$context->contains($parent)) {
				continue;
			}

			if($match) {
				throw new DuplicateListElementNameException(
					"More than one list element with name \"$templateName\" "
					. "exists within the context $context->tagName element."
				);
			}
			$match = $element;
		}

		if($match) {
			return $match;
		}

		throw new ListElementNotFoundInContextException(
			"List element with name \"$templateName\" can not be "
			. "found within the context $context->tagName element."
		);
	}

	private function extractTemplates(Document $document):void {
		$dataTemplateArray = [];
		/** @var Element $element */
		foreach($document->querySelectorAll("[data-list],[data-template]") as $element) {
			$templateElement = new ListElement($element);
			$nodePath = (string)(new NodePathCalculator($element));
			$key = $templateElement->getListItemName() ?? $nodePath;
			if(isset($dataTemplateArray[$key])) {
				$key = $nodePath . "[" . count($dataTemplateArray) . "]";
			}
			$dataTemplateArray[$key] = $templateElement;
		}

		uksort(
			$dataTemplateArray,
			fn(string $a, string $b):int => substr_count($a, "/") > substr_count($b, "/")
				? -1
				: 1
		);

		foreach($dataTemplateArray as $template) {
			$template->removeOriginalElement();
		}

		$this->elementKVP = array_reverse($dataTemplateArray, true);
	}

	private function findMatch(Element $context):ListElement {
		$contextPath = (string)(new NodePathCalculator($context));
		/** @noinspection RegExpRedundantEscape */
		$contextPath = preg_replace(
			"/(\[\d+\])/",
			"",
			$contextPath
		);

		$matchedElement = null;
		$matchedDistance = PHP_INT_MAX;
		foreach($this->elementKVP as $name => $element) {
			if($element->isNamed()) {
				continue;
			}

			try {
				$listItemParent = $element->getListItemParent();
			}
			catch(Throwable) {
				continue;
			}

			if(!$listItemParent instanceof Element) {
				continue;
			}
			if($listItemParent !== $context && !$context->contains($listItemParent)) {
				continue;
			}

			$distance = $this->getDistanceFromContext($context, $listItemParent);
			if($distance < $matchedDistance) {
				$matchedElement = $element;
				$matchedDistance = $distance;
			}
		}

		if($matchedElement) {
			return $matchedElement;
		}

		foreach($this->elementKVP as $name => $element) {
			if($element->isNamed()) {
				continue;
			}

			if($contextPath === $name) {
				continue;
			}

			if(!str_starts_with($name, $contextPath)) {
				continue;
			}

			$xpathResult = $context->ownerDocument->evaluate(
				$contextPath
			);

			if($xpathResult->valid()) {
				return $element;
			}
		}

		$elementDescription = $context->tagName;
		foreach($context->classList as $className) {
			$elementDescription .= ".$className";
		}

		if($context->id) {
			$elementDescription .= "#$context->id";
		}

		$elementNodePath = $context->getNodePath();

		throw new ListElementNotFoundInContextException(
			"There is no unnamed list element in the context element "
			. "$elementDescription ($elementNodePath)."
		);
	}

	private function getDistanceFromContext(
		Element $context,
		Element $listItemParent,
	):int {
		$distance = 0;
		$ancestor = $listItemParent;

		while($ancestor !== $context && $ancestor = $ancestor->parentElement) {
			$distance++;
		}

		return $distance;
	}
}
