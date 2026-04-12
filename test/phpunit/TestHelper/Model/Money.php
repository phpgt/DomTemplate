<?php
namespace GT\DomTemplate\Test\TestHelper\Model;

use Stringable;

class Money implements Stringable {
	const DEFAULT_CURRENCY = Currency::USD;

	public function __construct(
		public readonly float $value = 0.0,
		public readonly Currency $currency = self::DEFAULT_CURRENCY,
		public readonly int $decimalAccuracy = 2,
	) {}

	public function __toString():string {
		return Currency::getSymbol($this->currency)
			. number_format($this->value, 2);
	}

	public function withAddition(Money $add):self {
		$newValue = round(
			$add->value,
			$this->decimalAccuracy,
		) + round(
			$this->value,
			$this->decimalAccuracy,
		);
		return new self(
			$newValue,
			$this->currency,
		);
	}
}
