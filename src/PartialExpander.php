<?php
namespace GT\DomTemplate;

use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;

class PartialExpander extends PartialContentExpander {
	/**
	 * @return string[] A list of names of partials that have been expanded,
	 * in the order that they were expanded.
	 */
	public function expand(
		?Element $context = null,
		?DocumentBinder $binder = null,
	):array {
		if(!$context) {
			$context = $this->document->documentElement;
		}

		[$partialDocumentArray, $vars] = $this->collectPartialDocuments($context);
		foreach($partialDocumentArray as $extends => $partialDocument) {
			$this->injectPartialDocument($extends, $partialDocument);
		}

		if($binder) {
			foreach($vars as $key => $value) {
				$binder->bindKeyValue($key, $value);
			}
		}

		return array_keys($partialDocumentArray);
	}

	/**
	 * @return array{
	 *  0: array<string, HTMLDocument>,
	 *  1: array<string, string>
	 * }
	 */
	private function collectPartialDocuments(Element $context):array {
		$vars = [];
		$partialDocumentArray = [];

		do {
			$commentIni = new CommentIni($context);
			$extends = $commentIni->get("extends");
			if(is_null($extends)) {
				break;
			}

			$vars += $commentIni->getVars();
			$partialDocumentArray[$extends] = $this->loadPartialDocument(
				$extends,
				$partialDocumentArray,
			);
			$context = $partialDocumentArray[$extends];
		}
		while(true);

		return [$partialDocumentArray, $vars];
	}

	/**
	 * @param array<string, HTMLDocument> $partialDocumentArray
	 */
	private function loadPartialDocument(
		string $extends,
		array $partialDocumentArray,
	):HTMLDocument {
		if(isset($partialDocumentArray[$extends])) {
			throw new CyclicRecursionException(
				"Partial '$extends' has already been expanded in this "
				. "document, expanding again would cause cyclic recursion."
			);
		}

		return $this->partialContent->getHTMLDocument($extends);
	}

	private function injectPartialDocument(
		string $extends,
		HTMLDocument $partialDocument,
	):void {
		$this->applyDocumentTitle($partialDocument);
		$importedRoot = $this->document->importNode(
			$partialDocument->documentElement,
			true,
		);
		$injectionPoint = $this->resolveInjectionPoint($extends, $importedRoot);
		$this->moveBodyChildrenToInjectionPoint($injectionPoint);
		$this->replaceDocumentElementChildren($importedRoot);
	}

	private function applyDocumentTitle(HTMLDocument $partialDocument):void {
		if($currentTitle = $this->document->title) {
			$partialDocument->title = $currentTitle;
		}
	}

	private function resolveInjectionPoint(
		string $extends,
		Element $importedRoot,
	):Element {
		$partialElementList = $importedRoot->querySelectorAll("[data-partial]");
		if(count($partialElementList) > 1) {
			throw new PartialInjectionMultiplePointException(
				"The current view extends the partial \"$extends\", but "
				. "there is more than one element marked with `data-partial`. "
				. "For help, see https://www.php.gt/domtemplate/partials"
			);
		}

		$injectionPoint = $partialElementList[0] ?? null;
		$partialElementList[0]?->removeAttribute("data-partial");
		if(!$injectionPoint) {
			throw new PartialInjectionPointNotFoundException(
				"The current view extends the partial \"$extends\", but "
				. "there is no element marked with `data-partial`. "
				. "For help, see https://www.php.gt/domtemplate/partials"
			);
		}

		return $injectionPoint;
	}

	private function moveBodyChildrenToInjectionPoint(Element $injectionPoint):void {
		while($child = $this->document->body->firstChild) {
			$injectionPoint->appendChild($child);
		}
	}

	private function replaceDocumentElementChildren(Element $importedRoot):void {
		while($child = $this->document->documentElement->firstChild) {
			$child->parentNode->removeChild($child);
		}

		while($child = $importedRoot->firstChild) {
			$this->document->documentElement->appendChild($child);
		}
	}
}
