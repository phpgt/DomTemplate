<?php
namespace GT\DomTemplate\Test\TestHelper\Model;

use GT\DomTemplate\BindGetter;

class Address {
	public function __construct(
		public readonly string $street,
		public readonly string $line2,
		public readonly string $cityState,
		public readonly string $postcodeZip,
		public readonly Country $country,
	) {}
}
