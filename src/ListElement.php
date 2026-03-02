<?php
namespace Gt\DomTemplate;

use Gt\Dom\Element;
use Gt\Dom\Node;
use Gt\Dom\Text;
use Throwable;

class ListElement {
	const ATTRIBUTE_LIST_PARENT = "data-list-parent";
	const ATTRIBUTE_LIST_KEEP_TEMPLATE = "data-list-keep-template";
	private const ATTRIBUTE_LIST_PROXY = "data-list-proxy";

	private string $listItemParentPath;
	private null|Element $listItemNextSibling;
	private int $insertCount;
	/** @var array<int, array{template:Element, keepTemplate:bool}> */
	private array $templateProxyMap;

	public function __construct(
		private readonly Node|Element $originalElement
	) {
		$parentElement = $this->originalElement->parentElement;
		if(!$parentElement->getAttribute(self::ATTRIBUTE_LIST_PARENT)) {
			$parentElement->setAttribute(self::ATTRIBUTE_LIST_PARENT, uniqid("list-parent-"));
		}

		$this->listItemParentPath = new NodePathCalculator($parentElement);

		$siblingContext = $this->originalElement;
		while($siblingContext = $siblingContext->nextElementSibling) {
			if(!$siblingContext->hasAttribute("data-list")
			&& !$siblingContext->hasAttribute("data-template")) {
				break;
			}
		}
		$this->listItemNextSibling =
			is_null($siblingContext)
			? null
			: $siblingContext;

		$this->insertCount = 0;
		$this->templateProxyMap = [];
	}

	public function removeOriginalElement():void {
		$this->originalElement->remove();
		try {
			$parent = $this->getListItemParent();
			if(count($parent->children) === 0) {
				if($firstNode = $parent->childNodes[0] ?? null) {
					if(trim($firstNode->wholeText) === "") {
						$parent->innerHTML = "";
					}
				}
			}
		}
// In nested lists, there may not be an actual element attached to the document
// yet, but the parent still has a path - this outcome is expected and
// completely fine in this case.
		catch(Throwable) {}
	}

	public function getClone():Node|Element {
// TODO: #368 Bug here - the template-parent-xxx ID is being generated the same for multiple instances.
		/** @var Element $element */
		$element = $this->originalElement->cloneNode(true);
//		foreach($this->originalElement->ownerDocument->evaluate("./*[starts-with(@id,'template-parent-')]", $element) as $existingTemplateElement) {
//			$existingTemplateElement->id = uniqid("template-parent-");
//		}
//		$this->templateParentPath = new NodePathCalculator($element->parentElement);
		return $element;
	}

	/**
	 * Inserts a deep clone of the original element in place where it was
	 * originally extracted from the document, returning the newly-inserted
	 * clone.
	 */
	public function insertListItem():Element {
		$listItemParent = $this->getListItemParent();
		$nextSibling = $this->getListItemNextSibling();
		$clone = $this->getClone();

		if(strtolower($clone->tagName) === "template") {
			$keepTemplate = $clone->hasAttribute(self::ATTRIBUTE_LIST_KEEP_TEMPLATE);
			$proxy = $clone->ownerDocument->createElement("div");
			$proxy->setAttribute(self::ATTRIBUTE_LIST_PROXY, "");
			$proxy->innerHTML = $clone->innerHTML;
			$listItemParent->insertBefore($proxy, $nextSibling);
			$this->templateProxyMap[spl_object_id($proxy)] = [
				"template" => $clone,
				"keepTemplate" => $keepTemplate,
			];
			$this->insertCount++;
			return $proxy;
		}

		$listItemParent->insertBefore($clone, $nextSibling);
		$this->insertCount++;
		return $clone;
	}

	public function finalizeListItem(Element $inserted):void {
		$insertedId = spl_object_id($inserted);
		if(!isset($this->templateProxyMap[$insertedId])) {
			return;
		}

		$templateInfo = $this->templateProxyMap[$insertedId];
		unset($this->templateProxyMap[$insertedId]);

		$template = $templateInfo["template"];
		$parent = $inserted->parentElement;
		if(!$parent) {
			return;
		}

		if($templateInfo["keepTemplate"]) {
			$template->innerHTML = $inserted->innerHTML;
			$parent->insertBefore($template, $inserted);
			$inserted->remove();
			return;
		}

		while($inserted->childNodes->length) {
			$parent->insertBefore($inserted->childNodes[0], $inserted);
		}
		$inserted->remove();
	}

	public function getListItemParent():Node|Element {
		$matches = $this->originalElement->ownerDocument->evaluate(
			$this->listItemParentPath
		);
		do {
			/** @var Element $parent */
			$parent = $matches->current();
			$matches->next();
		}
		while($matches->valid());
		return $parent;
	}

	public function getListItemNextSibling():null|Node|Element {
		return $this->listItemNextSibling ?? null;
	}

	public function getListItemName():?string {
		$listName = $this->originalElement->getAttribute("data-list") ?? $this->originalElement->getAttribute("data-template");
		if(strlen($listName) === 0) {
			return null;
		}
		elseif($listName[0] === "/") {
			throw new InvalidListElementNameException("A list's name must not start with a forward slash (\"$listName\")");
		}

		return $listName;
	}
}
