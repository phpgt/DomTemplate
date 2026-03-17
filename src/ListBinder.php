<?php
namespace Gt\DomTemplate;

use DateTimeInterface;
use Gt\Dom\Document;
use Gt\Dom\Element;
use Iterator;
use IteratorAggregate;
use Stringable;

class ListBinder {
	public const LIST_KEY_BIND_KEY = "{{}}";

	private ElementBinder $elementBinder;
	private ListElementCollection $listElementCollection;
	private BindableCache $bindableCache;
	/**
	 * @noinspection PhpPropertyOnlyWrittenInspection
	 * @phpstan-ignore-next-line
	 */
	private TableBinder $tableBinder;

	public function setDependencies(
		ElementBinder $elementBinder,
		ListElementCollection $listElementCollection,
		BindableCache $bindableCache,
		TableBinder $tableBinder,
	):void {
		$this->elementBinder = $elementBinder;
		$this->listElementCollection = $listElementCollection;
		$this->bindableCache = $bindableCache;
		$this->tableBinder = $tableBinder;
	}

	/** @param iterable<int|string,mixed> $listData */
	public function bindListData(
		iterable $listData,
		Document|Element $context,
		?string $listItemName = null,
		?callable $callback = null,
		bool $recursiveCall = false,
	):int {
		if($context instanceof Document) {
			$context = $context->documentElement;
		}

		if($this->isEmpty($listData)) {
			$this->clearListItemParentHtml($context, $listItemName);
			return 0;
		}

		$listItem = $this->getListItem($context, $listItemName, $recursiveCall);
		if(!$listItem) {
			return 0;
		}

		$nestedCount = 0;
		$i = -1;

		foreach($listData as $listKey => $listValue) {
			$i++;
			$template = $listItem->insertListItem();
			try {
				$nestedCount += $this->bindListItem(
					$template,
					$listKey,
					$listValue,
					$listItemName,
					$callback,
				);
			}
			finally {
				$listItem->finalizeListItem($template);
			}
		}

		return $nestedCount + $i + 1;
	}

	private function getListItem(
		Element $context,
		?string $listItemName,
		bool $recursiveCall,
	):?ListElement {
		try {
			return $this->listElementCollection->get($context, $listItemName);
		}
		catch(ListElementNotFoundInContextException $exception) {
			if($recursiveCall) {
				return null;
			}

			throw $exception;
		}
	}

	private function bindListItem(
		Element $template,
		int|string $listKey,
		mixed $listValue,
		?string $listItemName,
		?callable $callback,
	):int {
		$this->elementBinder->bind(self::LIST_KEY_BIND_KEY, $listKey, $template);
		if($this->isNested($listValue)) {
			return $this->bindNestedListItem($template, $listKey, $listValue);
		}

		$listValue = $this->normalizeListValue($listValue);
		if($callback) {
			$listValue = $callback($template, $listValue, $listKey);
		}

		if(is_null($listValue)) {
			return 0;
		}

		if($this->isKeyValuePair($listValue)) {
			return $this->bindKeyValueListItem(
				$template,
				$listKey,
				$listValue,
				$listItemName,
			);
		}

		$this->elementBinder->bind(null, $listValue, $template);
		return 0;
	}

	private function bindNestedListItem(
		Element $template,
		int|string $listKey,
		mixed $listValue,
	):int {
		$this->elementBinder->bind(null, $listKey, $template);
		$this->bindListData($listValue, $template);
		foreach($this->bindableCache->convertToKvp($listValue) as $key => $value) {
			$this->elementBinder->bind($key, $value, $template);
		}

		return 0;
	}

	private function normalizeListValue(mixed $listValue):mixed {
		if(is_object($listValue) && method_exists($listValue, "asArray")) {
			return $listValue->asArray();
		}

		if(is_object($listValue)
		&& !is_iterable($listValue)
		&& $this->bindableCache->isBindable($listValue)) {
			return $this->bindableCache->convertToKvp($listValue);
		}

		return $listValue;
	}

	/**
	 * @param iterable<int|string, mixed> $listValue
	 */
	private function bindKeyValueListItem(
		Element $template,
		int|string $listKey,
		iterable $listValue,
		?string $listItemName,
	):int {
		$nestedCount = 0;
		$this->elementBinder->bind(null, $listKey, $template);

		foreach($listValue as $key => $value) {
			$this->elementBinder->bind($key, $value, $template);
			if(!$this->isNested($value)) {
				continue;
			}

			$this->elementBinder->bind(null, $key, $template);
			$nestedCount += $this->bindListData(
				$value,
				$template,
				$listItemName,
				recursiveCall: true,
			);
		}

		return $nestedCount;
	}

	/** @param iterable<int|string,mixed> $listData */
	private function isEmpty(iterable $listData):bool {
		if(is_array($listData)) {
			return is_null(array_key_first($listData));
		}
		else {
			/** @var Iterator|IteratorAggregate $iterator */
			$iterator = $listData;

			if($iterator instanceof IteratorAggregate) {
				$iterator = $iterator->getIterator();
				/** @var Iterator $iterator */
			}
			$iterator->rewind();
			return !$iterator->valid();
		}
	}

	private function clearListItemParentHtml(
		Element $context,
		?string $listName
	):void {
		$listElement = $this->listElementCollection->get($context, $listName);
		$parent = $listElement->getListItemParent();
		$parent->innerHTML = trim($parent->innerHTML ?? "");
	}

	private function isKeyValuePair(mixed $item):bool {
		if(is_scalar($item)) {
			return false;
		}

		if($item instanceof DateTimeInterface) {
			return false;
		}
		if($item instanceof Stringable) {
			return false;
		}

		return true;
	}

	private function isNested(mixed $item):bool {
		if(empty($item)) {
			return false;
		}
		if(is_array($item)) {
			$key = array_key_first($item);
			return is_int($key) || (isset($item[$key]) && is_iterable($item[$key]));
		}
		elseif(is_iterable($item)) {
			return true;
		}

		return false;
	}
}
