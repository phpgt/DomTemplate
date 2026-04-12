<?php
namespace GT\DomTemplate\Test\TestHelper\Model;

use GT\DomTemplate\BindGetter;

class Order {
	/** @param array<ShopItem> $itemList */
	public function __construct(
		public readonly int $id,
		public readonly Money $shippingCost,
		public readonly Address $shippingAddress,
		public readonly array $itemList = [],
	) {
	}

	#[BindGetter]
	public function getSubtotal():Money {
		$subtotal = new Money();

		foreach($this->itemList as $item) {
			$subtotal = $subtotal->withAddition($item->cost);
		}

		return $subtotal;
	}

	#[BindGetter]
	public function getTotalCost():Money {
		return $this->getSubtotal()->withAddition($this->shippingCost);
	}
}
