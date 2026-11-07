<?php
namespace GT\DomTemplate\Test;

use GT\DomTemplate\BindFilter;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use Countable;
use stdClass;

class BindFilterTest extends TestCase {
	public function testGetDependency_optionalAndMatchesInterfaces():void {
		$sut = new class extends BindFilter {};
		self::assertNull($sut->getDependency(Countable::class));
		$first = new ArrayObject([1]);
		$sut->setDependency($first);
		self::assertSame($first, $sut->getDependency(Countable::class));
		self::assertSame($first, $sut->getDependency(ArrayObject::class));
		self::assertNull($sut->getDependency(stdClass::class));

		$second = new ArrayObject([1, 2]);
		$sut->setDependency($second);
		self::assertSame($second, $sut->getDependency(Countable::class));
	}

	public function testGetFilter_exposesPublicInstanceMethods():void {
		$sut = new class extends BindFilter {
			public function upper(string $value):string {
				return strtoupper($value);
			}
			protected function hidden(string $value):string {
				return $value;
			}
			public static function static(string $value):string {
				return $value;
			}
			public function helper(string $value):string {
				return $value;
			}
		};
		self::assertSame("HELLO", $sut->getFilter("upper")("hello"));
		self::assertSame("hello", $sut->getFilter("helper")("hello"));
		foreach(["hidden", "static", "setDependency", "getDependency", "getFilter", "missing"] as $name) {
			self::assertNull($sut->getFilter($name));
		}
	}
}
