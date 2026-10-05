<?php
namespace GT\DomTemplate;

use GT\Dom\Document;
use GT\Dom\Element;
use Throwable;

class ListElementCollection {
	/** @var array<string, ListElement> */
	private array $elementKVP;
	/** @var array<string, array<int, ListElement>> */
	private array $namedElementKVP;

	public function __construct(
		Document $document
	) {
		$this->elementKVP = [];
		$this->namedElementKVP = [];
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
			return $this->findNamedMatch($context, $templateName);
		}

		return $this->findMatch($context);
	}

	private function extractTemplates(Document $document):void {
		$dataTemplateArray = [];
		/** @var Element $element */
		foreach($document->querySelectorAll("[data-list],[data-template]") as $element) {
			$templateElement = new ListElement($element);
			$nodePath = (string)(new NodePathCalculator($element));
			$dataTemplateArray[] = [
				"path" => $nodePath,
				"template" => $templateElement,
			];
		}

		usort(
			$dataTemplateArray,
			fn(array $a, array $b):int => substr_count($a["path"], "/") > substr_count($b["path"], "/")
				? -1
				: 1
		);

		foreach($dataTemplateArray as ["template" => $template]) {
			$template->removeOriginalElement();
		}

		foreach(array_reverse($dataTemplateArray) as $templateData) {
			$template = $templateData["template"];
			if($name = $template->getListItemName()) {
				$this->namedElementKVP[$name][] = $template;
			}
			else {
				$this->elementKVP[$templateData["path"]] = $template;
			}
		}
	}

	private function findNamedMatch(
		Element $context,
		string $templateName,
	):ListElement {
		$matchedElementArray = [];
		foreach($this->namedElementKVP[$templateName] ?? [] as $element) {
			try {
				$listItemParent = $element->getListItemParent();
			}
			catch(Throwable) {
				continue;
			}

			if($listItemParent === $context || $context->contains($listItemParent)) {
				$matchedElementArray[] = $element;
			}
		}

		if(count($matchedElementArray) > 1) {
			throw new DuplicateListElementNameException(
				"More than one list element with name \"$templateName\" "
				. "was found within the context $context->tagName element."
			);
		}

		if($matchedElementArray) {
			return $matchedElementArray[0];
		}

		throw new ListElementNotFoundInContextException(
			"List element with name \"$templateName\" can not be "
			. "found within the context $context->tagName element."
		);
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
