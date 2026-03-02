<?php
namespace Gt\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\ComponentExpander;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;

class ComponentExpanderTest extends PartialContentTestCase {
	public function testExpand_doesNothingWhenNoMatchingFiles():void {
		$partialContent = self::mockPartialContent("_component");
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT);
		$sut = new ComponentExpander($document, $partialContent);
		self::assertEmpty($sut->expand());
	}

	public function testExpand_returnsArrayOfExpandedElements():void {
		$html = "<h2>This has been replaced!</h2> <p>If you can read this, your custom element is working!</p>";

		$partialContent = self::mockPartialContent(
			"_component", [
				"custom-element" => $html
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT);
		$sut = new ComponentExpander($document, $partialContent);
		$expandedElements = $sut->expand();
		self::assertCount(1, $expandedElements);
		self::assertSame("custom-element", $expandedElements[0]->tagName);
		self::assertSame($html, $expandedElements[0]->innerHTML);
	}

	public function testExpand_recursive():void {
		$partialContent = self::mockPartialContent(
			"_component", [
				"todo-list" => HTMLPageContent::HTML_TODO_COMPONENT_TODO_LIST,
				"todo-list-item" => HTMLPageContent::HTML_TODO_COMPONENT_TODO_LIST_ITEM,
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO_CUSTOM_ELEMENT);
		$sut = new ComponentExpander($document, $partialContent);
		$expandedElements = $sut->expand();
		self::assertCount(2, $expandedElements);
		self::assertSame("todo-list", $expandedElements[0]->tagName);
		self::assertSame("todo-list-item", $expandedElements[1]->tagName);
	}

	public function testExpand_empty():void {
		$partialContent = self::mockPartialContent(
			"_component", [
				"empty-component" => "",
			]
		);
		/** @noinspection HtmlRequiredLangAttribute */
		$document = new HTMLDocument("<!doctype html><html><body><empty-component /></body></html>");
		$sut = new ComponentExpander($document, $partialContent);
		$expandedElements = $sut->expand();
		self::assertCount(1, $expandedElements);
		self::assertEquals("<empty-component></empty-component>", $document->body->innerHTML);
	}

	public function testExpand_nested():void {
		$partialContent = self::mockPartialContent(
			"_component", [
				"example-nested/first" => HTMLPageContent::HTML_COMPONENT_NESTED_INNER_FIRST,
				"example-nested/second" => HTMLPageContent::HTML_COMPONENT_NESTED_INNER_SECOND,
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_NESTED_OUTER);
		$sut = new ComponentExpander($document, $partialContent);
		$expandedElements = $sut->expand();
		self::assertCount(2, $expandedElements);
		self::assertStringContainsString(
			"This is the first nested component!",
			$document->querySelectorAll("example-nested")[0]->innerText,
		);
		self::assertStringContainsString(
			"This is the second nested component!",
			$document->querySelectorAll("example-nested")[1]->innerText,
		);
		self::assertStringContainsString(
			"And it has a more complex structure.",
			$document->querySelectorAll("example-nested")[1]->innerText,
		);
		self::assertSame("more complex", $document->querySelector("example-nested p strong")->innerText);
	}
}
