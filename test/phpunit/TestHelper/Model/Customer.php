<?php
namespace GT\DomTemplate\Test\TestHelper\Model;

class Customer {
	/** @param array<Order> $orderList */
	public function __construct(
		public int $id,
		public string $name,
		public ?Address $address = null,
		public array $orderList = [],
		public ?Customer $parentCustomer = null,
	) {}

	public function addOrder(Order $order):void {
		array_push($this->orderList, $order);
	}
}
