<?php
namespace Gt\DomTemplate;

use Gt\Dom\Attr;
use Gt\Dom\Document;
use Gt\Dom\Element;

class DocumentBinder extends Binder {
	protected ElementBinder $elementBinder;
	protected PlaceholderBinder $placeholderBinder;
	protected TableBinder $tableBinder;
	protected ListBinder $listBinder;
	protected ListElementCollection $templateCollection;
	protected BindableCache $bindableCache;

	public function __construct(
		protected readonly Document $document,
	) {}

	public function setDependencies(
		ElementBinder $elementBinder,
		PlaceholderBinder $placeholderBinder,
		TableBinder $tableBinder,
		ListBinder $listBinder,
		ListElementCollection $listElementCollection,
		BindableCache $bindableCache,
	):void {
		$this->elementBinder = $elementBinder;
		$this->placeholderBinder = $placeholderBinder;
		$this->tableBinder = $tableBinder;
		$this->listBinder = $listBinder;
		$this->templateCollection = $listElementCollection;
		$this->bindableCache = $bindableCache;
	}

	/**
	 * Applies the string value of $value anywhere within $context that
	 * has a data-bind attribute with no specified key.
	 */
	public function bindValue(
		mixed $value,
		null|string|Element $context = null
	):void {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		$this->bind(null, $value, $context);
	}

	/**
	 * Applies the string value of $value to any elements within $context
	 * that have the data-bind attribute matching the provided key.
	 */
	public function bindKeyValue(
		string $key,
		mixed $value,
		null|Element|string $context = null,
	):void {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		$this->bind($key, $value, $context);
	}

	/**
	 * Binds multiple key-value-pairs to any matching elements within
	 * the $context element.
	 */
	public function bindData(
		mixed $kvp,
		null|string|Element $context = null
	):void {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		if($this->isIndexedArray($kvp)) {
			throw new IncompatibleBindDataException(
				"bindData is only compatible with key-value-pair data, "
				. "but it was passed an indexed array."
			);
		}

		if(is_object($kvp) && method_exists($kvp, "asArray")) {
			$kvp = $kvp->asArray();
		}

// The $kvp object may be both an object with its own key-value-pairs and
// an iterable object. We can perform the two bind operations here.

		$object = null;
		if(is_object($kvp)) {
			if($this->bindableCache->isBindable($kvp)) {
				$object = $kvp;
				$kvp = $this->bindableCache->convertToKvp($kvp);
			}
		}

		foreach($kvp ?? [] as $key => $value) {
			$this->bindKeyValue($key, $value, $context);
		}

		if(is_iterable($object)) {
			$this->listBinder->bindListData($object, $context ?? $this->document);
		}
	}

	public function bindTable(
		mixed $tableData,
		null|string|Element $context = null,
		?string $bindKey = null
	):void {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		$this->tableBinder->bindTableData(
			$tableData,
			$context ?? $this->document,
			$bindKey
		);
	}

	/**
	 * @param iterable<int, mixed> $listData
	 */
	public function bindList(
		iterable $listData,
		null|string|Element $context = null,
		?string $templateName = null
	):int {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		if(!$context) {
			$context = $this->document;
		}

		return $this->listBinder->bindListData($listData, $context, $templateName);
	}

	/** @param iterable<int, mixed> $listData */
	public function bindListCallback(
		iterable $listData,
		callable $callback,
		null|string|Element $context = null,
		?string $templateName = null
	):int {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		if(!$context) {
			$context = $this->document;
		}

		return $this->listBinder->bindListData(
			$listData,
			$context,
			$templateName,
			$callback
		);
	}

	public function cleanupDocument():void {
		$xpathResult = $this->document->evaluate(
			"//*/@*[starts-with(name(), 'data-bind')]"
			. " | //*/@*[starts-with(name(), 'data-list')]"
			. " | //*/@*[starts-with(name(), 'data-template')]"
			. " | //*/@*[starts-with(name(), 'data-table-key')]"
			. " | //*/@*[starts-with(name(), 'data-element')]"
		);

		$elementsToRemove = [];
		while($item = $xpathResult->iterateNext()) {
			if(!$item instanceof Attr) {
				continue;
			}

			$ownerElement = $item->ownerElement;
			if($ownerElement->hasAttribute("data-element")) {
				if(!$ownerElement->hasAttribute("data-bound")) {
					array_push($elementsToRemove, $ownerElement);
				}
				continue;
			}

			$ownerElement->removeAttribute($item->name);
		}

		foreach($this->document->querySelectorAll("[data-element]") as $dataElement) {
			$dataElement->removeAttribute("data-element");
		}
		foreach($this->document->querySelectorAll("[data-bound]") as $dataBound) {
			$dataBound->removeAttribute("data-bound");
		}

		foreach($elementsToRemove as $element) {
			$element->remove();
		}
	}

	protected function bind(
		?string $key,
		mixed $value,
		?Element $context = null
	):void {
		if(!$context) {
			$context = $this->document->documentElement;
		}

		if(is_callable($value) && !is_string($value)) {
			$value = call_user_func($value);
		}

		$this->elementBinder->bind($key, $value, $context);
	}

	private function isIndexedArray(mixed $data):bool {
		if(!is_array($data)) {
			return false;
		}

		foreach(array_keys($data) as $key) {
			if(!is_int($key)) {
				return false;
			}
		}

		return true;
	}

	protected function stringToContext(string $context):Element {
		return $this->document->querySelector($context);
	}
}
