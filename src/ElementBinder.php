<?php
namespace GT\DomTemplate;

use Gt\Dom\Element;

class ElementBinder {
	private HTMLAttributeBinder $htmlAttributeBinder;
	private HTMLAttributeCollection $htmlAttributeCollection;
	private PlaceholderBinder $placeholderBinder;

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
		if(!is_null($value) && !is_scalar($value) && !is_iterable($value)) {
			$value = new BindValue($value);
		}

		/** @var Element $element */
		foreach($this->htmlAttributeCollection->find($context) as $element) {
			$this->htmlAttributeBinder->expandAttributes($element);
			$this->htmlAttributeBinder->bind($key, $value, $element);
		}

		$this->placeholderBinder->bind($key, $value, $context);
	}
}
