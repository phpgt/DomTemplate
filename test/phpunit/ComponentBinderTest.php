<?php
namespace Gt\DomTemplate\Test;
use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\BindableCache;
use Gt\DomTemplate\ComponentBinder;
use Gt\DomTemplate\ComponentDoesNotContainContextException;
use Gt\DomTemplate\ComponentExpander;
use Gt\DomTemplate\ElementBinder;
use Gt\DomTemplate\ListBinder;
use Gt\DomTemplate\ListElementCollection;
use Gt\DomTemplate\PlaceholderBinder;
use Gt\DomTemplate\TableBinder;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use PHPUnit\Framework\TestCase;

class ComponentBinderTest extends TestCase {
	public function testBindKeyValue_invalidContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT_ALREADY_EXPANDED);
		$componentElement = $document->querySelector("todo-list");
		$sut = new ComponentBinder($document);
		$sut->setComponentBinderDependencies($componentElement);
		self::expectException(ComponentDoesNotContainContextException::class);
		self::expectExceptionMessage("<todo-list> does not contain requested <body>");
		$sut->bindKeyValue("example-key", "example-value", $document->querySelector("body"));
	}

	public function testBindList_invalidContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT_ALREADY_EXPANDED);
		$componentElement = $document->querySelector("todo-list");
		$sut = new ComponentBinder($document);
		$sut->setComponentBinderDependencies($componentElement);
		self::expectException(ComponentDoesNotContainContextException::class);
		self::expectExceptionMessage("<todo-list> does not contain requested <body>");
		$sut->bindList([], $document->querySelector("body"));
	}

	public function testBindKeyValue_notInContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT_ALREADY_EXPANDED);
		$componentElement = $document->querySelector("todo-list");
		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			self::createStub(ElementBinder::class),
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);
		$sut->bindKeyValue("subtitle", "This should not change!");
		self::assertSame("Subtitle here", $document->querySelector("h2")->innerText);
	}

	public function testBindKeyValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT_ALREADY_EXPANDED);
		$componentElement = $document->querySelector("todo-list");
		$elementBinder = self::createMock(ElementBinder::class);
		$elementBinder->expects(self::once())
			->method("bind")
			->with("listTitle", "This should change!", $componentElement);

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);
		$sut->bindKeyValue("listTitle", "This should change!");
	}

	public function testBindKeyValue_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$componentElement = $document->querySelector("example-component");
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(3);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(string $key, string $value, Element $element)use($bindMatcher, $componentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals(["title", "Title 1!", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals(["title", "Title 2!", $subComponent2], [$key, $value, $element]),
					3 => self::assertEquals(["title", "Main title!", $componentElement], [$key, $value, $element]),
				};
			});

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);

		$sut->bindKeyValue("title", "Title 1!", "#subcomponent-1");
		$sut->bindKeyValue("title", "Title 2!", "#subcomponent-2");
		$sut->bindKeyValue("title", "Main title!");
	}

	public function testBindData_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$componentElement = $document->querySelector("example-component");
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(6);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(string $key, string $value, Element $element)use($bindMatcher, $componentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals(["title", "Title 1!", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals(["number", "1", $subComponent1], [$key, $value, $element]),
					3 => self::assertEquals(["title", "Title 2!", $subComponent2], [$key, $value, $element]),
					4 => self::assertEquals(["number", "2", $subComponent2], [$key, $value, $element]),
					5 => self::assertEquals(["title", "Main title!", $componentElement], [$key, $value, $element]),
					6 => self::assertEquals(["number", "3", $componentElement], [$key, $value, $element]),
				};
			});

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);

		$sut->bindData(["title" => "Title 1!", "number" => "1"], "#subcomponent-1");
		$sut->bindData(["title" => "Title 2!", "number" => "2"], "#subcomponent-2");
		$sut->bindData(["title" => "Main title!", "number" => "3"]);
	}

	public function testBindList_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$componentElement = $document->querySelector("example-component");
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$listBinder = self::createMock(ListBinder::class);
		$bindMatcher = self::exactly(3);
		$listBinder->expects($bindMatcher)
			->method("bindListData")
			->willReturnCallback(function(array $listData, Element $context)use($bindMatcher, $componentElement, $subComponent1, $subComponent2):int {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals([["List", "for", "component 2"], $subComponent2], [$listData, $context]),
					2 => self::assertEquals([["List", "for", "component 1"], $subComponent1], [$listData, $context]),
					3 => self::assertEquals([["List", "for", "main component"], $componentElement], [$listData, $context]),
				};

				return 0;
			});

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			self::createStub(ElementBinder::class),
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			$listBinder,
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);

		$sut->bindList(["List", "for", "component 2"], "#subcomponent-2");
		$sut->bindList(["List", "for", "component 1"], "#subcomponent-1");
		$sut->bindList(["List", "for", "main component"]);
	}

	public function testBindValue_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$componentElement = $document->querySelector("example-component");
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(3);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(?string $key, string $value, Element $element)use($bindMatcher, $componentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals([null, "1", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals([null, "2", $subComponent2], [$key, $value, $element]),
					3 => self::assertEquals([null, "3", $componentElement], [$key, $value, $element]),
				};
			});

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);

		$sut->bindValue("1", "#subcomponent-1");
		$sut->bindValue("2", "#subcomponent-2");
		$sut->bindValue("3");
	}

	public function testBindValue_callable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT_ALREADY_EXPANDED);
		$componentElement = $document->querySelector("todo-list");
		$elementBinder = self::createMock(ElementBinder::class);
		$elementBinder->expects(self::once())
			->method("bind")
			->with(null, "computed value", $componentElement);

		$sut = new ComponentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);
		$sut->setComponentBinderDependencies($componentElement);

		$sut->bindValue(fn():string => "computed value");
	}
}
