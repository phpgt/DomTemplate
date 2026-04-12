<?php
namespace Gt\DomTemplate;

use Gt\Dom\Document;
use Gt\Dom\Element;
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

		if($templateName) {
			if(!isset($this->elementKVP[$templateName])) {
				throw new ListElementNotFoundInContextException(
					"List element with name \"$templateName\" can not be "
					. "found within the context $context->tagName element."
				);
			}
			return $this->elementKVP[$templateName];
		}

		return $this->findMatch($context);
	}

	private function extractTemplates(Document $document):void {
		$dataTemplateArray = [];
		/** @var Element $element */
		foreach($document->querySelectorAll("[data-list],[data-template]") as $element) {
			$templateElement = new ListElement($element);
			$nodePath = (string)(new NodePathCalculator($element));
			$key = $templateElement->getListItemName() ?? $nodePath;
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
		$matchedDepth = -1;
		foreach($this->elementKVP as $name => $element) {
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

			$depth = substr_count((string)(new NodePathCalculator($listItemParent)), "/");
			if($depth > $matchedDepth) {
				$matchedElement = $element;
				$matchedDepth = $depth;
			}
		}

		if($matchedElement) {
			return $matchedElement;
		}

		foreach($this->elementKVP as $name => $element) {
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
}
