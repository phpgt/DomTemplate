<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student;

use Traversable;

class Student implements \IteratorAggregate {
	/** @param array<Module> $moduleList */
	public function __construct(
		public readonly Name $name,
		public readonly array $moduleList,
	) {}

	public function getIterator():Traversable {
		return new \ArrayIterator($this->moduleList);
	}
}
