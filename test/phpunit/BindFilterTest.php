<?php
namespace GT\DomTemplate\Test;

use GT\DomTemplate\BindFilter;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use Countable;
use stdClass;

class BindFilterTest extends TestCase {
	public function testDependenciesAreOptionalAndMatchInterfaces():void {
		$filter = new class extends BindFilter {};
		self::assertNull($filter->getDependency(Countable::class));
		$first = new ArrayObject([1]);
		$filter->setDependency($first);
		self::assertSame($first, $filter->getDependency(Countable::class));
		self::assertSame($first, $filter->getDependency(ArrayObject::class));
		self::assertNull($filter->getDependency(stdClass::class));

		$second = new ArrayObject([1, 2]);
		$filter->setDependency($second);
		self::assertSame($second, $filter->getDependency(Countable::class));
	}

	public function testOnlyPublicInstanceFilterMethodsAreExposed():void {
		$filter = new class extends BindFilter {
			public function filterUpper(string $value):string {
				return strtoupper($value);
			}
			protected function filterHidden(string $value):string {
				return $value;
			}
			public static function filterStatic(string $value):string {
				return $value;
			}
			public function helper(string $value):string {
				return $value;
			}
		};
		self::assertSame("HELLO", $filter->getFilter("upper")("hello"));
		foreach(["hidden", "static", "helper", "setDependency", "getDependency", "missing"] as $name) {
			self::assertNull($filter->getFilter($name));
		}
	}
}
