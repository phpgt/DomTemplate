<?php
namespace GT\DomTemplate;

use Gt\Dom\Element;

abstract class Binder {
	abstract public function bindValue(
		mixed $value,
		null|string|Element $context = null
	):void;

	/**
	 * Applies the string value of $value to any elements within $context
	 * that have the data-bind attribute matching the provided key.
	 */
	abstract public function bindKeyValue(
		string $key,
		mixed $value,
		null|string|Element $context = null
	):void;

	/**
	 * Binds multiple key-value-pairs to any matching elements within
	 * the $context element.
	 */
	abstract public function bindData(
		mixed $kvp,
		null|string|Element $context = null
	):void;

	abstract public function bindTable(
		mixed $tableData,
		null|string|Element $context = null,
		?string $bindKey = null
	):void;

	/**
	 * @param iterable<int, mixed> $listData
	 */
	abstract public function bindList(
		iterable $listData,
		null|string|Element $context = null,
		?string $templateName = null
	):int;

	/** @param iterable<int, mixed> $listData */
	abstract public function bindListCallback(
		iterable $listData,
		callable $callback,
		null|string|Element $context = null,
		?string $templateName = null
	):int;
}
