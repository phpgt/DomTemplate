<?php
namespace GT\DomTemplate\Test;

use GT\DomTemplate\Bind;
use GT\DomTemplate\BindGetter;
use GT\DomTemplate\BindableCache;
use GT\DomTemplate\BindGetterMethodDoesNotStartWithGetException;
use GT\DomTemplate\Test\TestHelper\Model\Address;
use GT\DomTemplate\Test\TestHelper\Model\Customer;
use GT\DomTemplate\Test\TestHelper\Model\Order;
use GT\DomTemplate\Test\TestHelper\TestData;
use PHPUnit\Framework\TestCase;
use stdClass;

class BindableCacheTest extends TestCase {
	public function testIsBindable_nonBindableCached():void {
		$obj = new StdClass();
		$sut = new BindableCache();
		self::assertFalse($sut->isBindable($obj));
		self::assertFalse($sut->isBindable($obj));
	}

	public function testIsBindable_bindableCached():void {
		$obj1 = new class extends StdClass {
			#[Bind("name")]
			public function getName():string {
				return "Test 1";
			}
		};

		$obj2 = new class extends StdClass {
			#[Bind("name")]
			public function getName():string {
				return "Test 2";
			}
		};

		$sut = new BindableCache();
		self::assertTrue($sut->isBindable($obj1));
		self::assertTrue($sut->isBindable($obj1));
		self::assertTrue($sut->isBindable($obj2));
	}

	public function testConvertToKvp_getter():void {
		$obj = new class {
			#[BindGetter]
			public function getName():string {
				return "Test Name";
			}
		};

		$sut = new BindableCache();
		$kvp = $sut->convertToKvp($obj);
		self::assertEquals("Test Name", $kvp["name"]);
	}

	public function testConvertToKvp_getterDoesNotStartWithGet():void {
		$obj = new class {
			/** @noinspection PhpUnused */
			#[BindGetter]
			public function retrieveName():string {
				return "Test Name";
			}
		};

		$sut = new BindableCache();
		self::expectException(BindGetterMethodDoesNotStartWithGetException::class);
		self::expectExceptionMessage("Method retrieveName has the BindGetter Attribute, but its name doesn't start with \"get\".");
		$sut->convertToKvp($obj);
	}

	public function testConvertToKvp_notBindable():void {
		$obj = new class {
			public function getName():string {
				return "Test";
			}
		};

		$sut = new BindableCache();
		self::assertSame([], $sut->convertToKvp($obj));
	}

	/** @noinspection PhpUnused */
	public function testConvertToKvp_publicReadOnly():void {
		$obj = new class {
			public readonly string $id;
			public readonly string $name;
			public readonly int $age;

			public function __construct() {
				$this->id = "test-id";
				$this->name = "test-name";
			}
		};

		$sut = new BindableCache();
		self::assertSame([
			"id" => "test-id",
			"name" => "test-name",
			"age" => null,
		], $sut->convertToKvp($obj));
	}

	public function testConvertToKvp_publicReadOnly_constructor():void {
		$obj = new class("test-name", 55) {
			public readonly string $id;

			public function __construct(
				public readonly string $name,
				public readonly int $age,
			) {
				$this->id = "id-$name";
			}
		};

		$sut = new BindableCache();
		self::assertSame([
			"id" => "id-test-name",
			"name" => "test-name",
			"age" => "55",
		], $sut->convertToKvp($obj));
	}

	public function testConvertToKvp_publicReadOnly_mixedWithBindAttr():void {
		$obj = new class("test-name", 5) {
			public function __construct(
				public readonly string $name,
				public readonly int $age,
			) {}

			/** @noinspection PhpUnused */
			#[BindGetter]
			public function getAgeStatus():string {
				return $this->age >= 18
					? "adult"
					: "minor";
			}
		};

		$sut = new BindableCache();
		self::assertSame([
			"ageStatus" => "minor",
			"name" => "test-name",
			"age" => "5",
		], $sut->convertToKvp($obj));
	}

	public function testConvertToKvp_publicReadOnlyNull():void {
		$obj = new class("test-name") {
			public function __construct(
				public readonly string $name,
				public readonly ?string $email = null,
			) {}
		};

		$sut = new BindableCache();
		self::assertSame([
			"name" => "test-name",
			"email" => null,
		], $sut->convertToKvp($obj));
	}

	public function testConvertToKvp_nestedObject():void {
		$sut = new BindableCache();

		$customerList = TestData::getCustomerOrderOverview1();
		$kvpList = [];
		foreach($customerList as $customer) {
			array_push($kvpList, $sut->convertToKvp($customer));
		}

		foreach($customerList as $i => $customer) {
			self::assertSame((string)$customer->id, $kvpList[$i]["id"]);
			self::assertSame($customer->name, $kvpList[$i]["name"]);
			self::assertSame($customer->address->street, $kvpList[$i]["address.street"]);
			self::assertSame($customer->address->line2, $kvpList[$i]["address.line2"]);
			self::assertSame($customer->address->cityState, $kvpList[$i]["address.cityState"]);
			self::assertSame($customer->address->postcodeZip, $kvpList[$i]["address.postcodeZip"]);
			self::assertSame($customer->address->country->code, $kvpList[$i]["address.country.code"]);
			self::assertSame($customer->address->country->getName(), $kvpList[$i]["address.country.name"]);
		}
	}

	public function testConvertKvp_nestedObjectSameType():void {
		$sut = new BindableCache();
		$parentCustomer = new Customer(100, "Parent Customer");
		$customer = new Customer(101, "Test Customer", parentCustomer: $parentCustomer);
		$kvp = $sut->convertToKvp($customer);
		self::assertSame("101", $kvp["id"]);
		self::assertSame("Test Customer", $kvp["name"]);
	}

	public function testConvertToKvp_nestedNullProperty():void {
		$sut = new BindableCache();

		$customerList = TestData::getCustomerOrderOverview1();
		$kvpList = [];
		foreach($customerList as $customer) {
// force the address to be null, so address.street can't resolve.
			$customer->address = null;
			array_push($kvpList, $sut->convertToKvp($customer));
		}

		foreach($customerList as $i => $customer) {
			self::assertSame((string)$customer->id, $kvpList[$i]["id"]);
			self::assertSame($customer->name, $kvpList[$i]["name"]);
			self::assertNull($customer->address);
			self::assertNull($kvpList[$i]["address.street"]);
			self::assertNull($kvpList[$i]["address.line2"]);
			self::assertNull($kvpList[$i]["address.cityState"]);
			self::assertNull($kvpList[$i]["address.postcodeZip"]);
			self::assertNull($kvpList[$i]["address.country.code"]);
			self::assertNull($kvpList[$i]["address.country.name"]);
		}
	}

	public function testReadNestedObjectValue_privatePropertyReturnsNull():void {
		$sut = new BindableCache();
		$address = new Address(
			"123 Example Street",
			"Apt 4",
			"Example City",
			"AB1 2CD",
			new \GT\DomTemplate\Test\TestHelper\Model\Country("GB"),
		);
		$object = new class($address) {
			public function __construct(private readonly Address $address) {}
		};

		self::assertNull(
			$this->invokeBindableCacheMethod($sut, "readNestedObjectValue", $object, "address"),
		);
	}

	public function testReadNestedObjectValue_uninitializedTypedPropertyReturnsNull():void {
		$sut = new BindableCache();
		$object = new class {
			public Address $address;
		};

		self::assertNull(
			$this->invokeBindableCacheMethod($sut, "readNestedObjectValue", $object, "address"),
		);
	}

	public function testReadNestedObjectValue_missingPropertyReturnsNull():void {
		$sut = new BindableCache();
		$object = new class {};

		self::assertNull(
			$this->invokeBindableCacheMethod($sut, "readNestedObjectValue", $object, "address"),
		);
	}

	public function testNullableStringOrIterable_missingMemberReturnsNull():void {
		$sut = new BindableCache();
		$object = new class {};

		self::assertNull(
			$this->invokeBindableCacheMethod($sut, "nullableStringOrIterable", $object, "missing"),
		);
	}

	private function invokeBindableCacheMethod(
		BindableCache $sut,
		string $methodName,
		object $object,
		string $propertyName,
	):mixed {
		$invokeMethod = \Closure::bind(
			fn(object $target, string $member):mixed => $this->$methodName($target, $member),
			$sut,
			BindableCache::class,
		);
		return $invokeMethod($object, $propertyName);
	}
}
