<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student;

use GT\DomTemplate\BindGetter;
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

	#[BindGetter]
	public function getGeneratedId():string {
		$id = "";
		$id .= $this->name->first[0];
		$id .= $this->name->last[0];
		$id .= "-";
		foreach($this->moduleList as $module) {
			$id .= $module->title[0];
		}

		return $id;
	}
}
