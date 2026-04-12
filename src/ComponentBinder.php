<?php
namespace Gt\DomTemplate;

use Gt\Dom\Document;
use Gt\Dom\Element;

class ComponentBinder extends DocumentBinder {
	private Element $componentElement;

	public function setComponentBinderDependencies(Element $componentElement):void {
		$this->componentElement = $componentElement;
	}

	public function bindList(
		iterable $listData,
		null|string|Element $context = null,
		?string $templateName = null
	):int {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		if($context) {
			$this->checkElementContainedWithinComponent($context);
		}
		else {
			$context = $this->componentElement;
		}

		return parent::bindList($listData, $context, $templateName);
	}

	public function bindListCallback(
		iterable $listData,
		callable $callback,
		null|string|Element $context = null,
		?string $templateName = null
	):int {
		if(is_string($context)) {
			$context = $this->stringToContext($context);
		}

		if($context) {
			$this->checkElementContainedWithinComponent($context);
		}
		else {
			$context = $this->componentElement;
		}

		return parent::bindListCallback($listData, $callback, $context, $templateName);
	}

	protected function bind(
		?string $key,
		mixed $value,
		?Element $context = null
	):void {
		if($context) {
			$this->checkElementContainedWithinComponent($context);
		}
		else {
			$context = $this->componentElement;
		}

		if(is_callable($value) && !is_string($value)) {
			$value = call_user_func($value);
		}

		$this->elementBinder->bind($key, $value, $context);
		$this->placeholderBinder->bind($key, $value, $context);
	}

	private function checkElementContainedWithinComponent(Element $context):void {
		if($this->componentElement !== $context && !$this->componentElement->contains($context)) {
			throw new ComponentDoesNotContainContextException(
				"<{$this->componentElement->tagName}> does not contain requested <$context->tagName>."
			);
		}
	}

	protected function stringToContext(string $context):Element {
		$element = $this->componentElement->querySelector($context);
		if($element) {
			return $element;
		}

		throw new ContextElementNotFoundException(
			"No element matches the context selector \"$context\" within "
			. "<{$this->componentElement->tagName}>."
		);
	}
}
