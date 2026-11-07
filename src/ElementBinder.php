<?php
namespace GT\DomTemplate;

use GT\Dom\Element;

class ElementBinder {
	private HTMLAttributeBinder $htmlAttributeBinder;
	private HTMLAttributeCollection $htmlAttributeCollection;
	private PlaceholderBinder $placeholderBinder;

	public function registerBindFilter(BindFilter $filter):void {
		$this->htmlAttributeBinder->registerBindFilter($filter);
	}

	public function setDebugSource(?string $debugSource):void {
		$this->htmlAttributeBinder->setDebugSource($debugSource);
	}

	public function setDependencies(
		HTMLAttributeBinder $htmlAttributeBinder,
		HTMLAttributeCollection $htmlAttributeCollection,
		PlaceholderBinder $placeholderBinder,
	):void {
		$this->htmlAttributeBinder = $htmlAttributeBinder;
		$this->htmlAttributeCollection = $htmlAttributeCollection;
		$this->placeholderBinder = $placeholderBinder;
	}

	/**
	 * Binds an Element and its children according to any HTML "data-bind"
	 * attributes found, and any {{placeholder}} text.
	 */
	public function bind(
		?string $key,
		mixed $value,
		Element $context
	):void {
		/** @var Element $element */
		foreach($this->htmlAttributeCollection->find($context) as $element) {
			$this->htmlAttributeBinder->expandAttributes($element);
			$this->htmlAttributeBinder->bind($key, $value, $element);
		}

		if(!is_null($value) && !is_scalar($value) && !is_iterable($value)
		&& !$value instanceof BindableValue) {
			$value = new BindValue($value);
		}

		$this->placeholderBinder->bind($key, $value, $context);
	}
}
