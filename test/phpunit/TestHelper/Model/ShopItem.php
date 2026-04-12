<?php
namespace GT\DomTemplate\Test\TestHelper\Model;

class ShopItem {
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $description,
		public readonly Money $cost,
	) {}
}
