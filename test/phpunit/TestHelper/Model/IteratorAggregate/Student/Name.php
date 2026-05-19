<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student;

use GT\DomTemplate\BindGetter;

class Name {
	public function __construct(
		public readonly string $first,
		public readonly string $last,
	) {}

	#[BindGetter]
	public function getFullName():string {
		return implode(" ", [$this->first, $this->last]);
	}
}
