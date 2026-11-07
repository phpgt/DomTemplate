<?php
namespace GT\DomTemplate;

use Stringable;

/** @internal Retain the original value without changing ordinary object bindings. */
class BindableValue {
	private bool $resolved = false;
	private mixed $bindingValue = null;

	public function __construct(public readonly mixed $rawValue) {}

	public function getBindingValue():mixed {
		if(!$this->resolved) {
			$this->bindingValue = match(true) {
				is_iterable($this->rawValue), is_null($this->rawValue) => $this->rawValue,
				is_scalar($this->rawValue), $this->rawValue instanceof Stringable => (string)$this->rawValue,
				default => null,
			};
			$this->resolved = true;
		}

		return $this->bindingValue;
	}
}
