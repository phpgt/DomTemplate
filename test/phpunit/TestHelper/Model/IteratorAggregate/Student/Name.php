<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student;

class Name {
	public function __construct(
		public readonly string $first,
		public readonly string $last,
	) {}
}
