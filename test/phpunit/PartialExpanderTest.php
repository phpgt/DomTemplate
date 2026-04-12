<?php
namespace GT\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use GT\DomTemplate\CyclicRecursionException;
use GT\DomTemplate\DocumentBinder;
use GT\DomTemplate\PartialContent;
use GT\DomTemplate\PartialContentFileNotFoundException;
use GT\DomTemplate\PartialExpander;
use GT\DomTemplate\PartialInjectionMultiplePointException;
use GT\DomTemplate\PartialInjectionPointNotFoundException;
use GT\DomTemplate\Test\TestHelper\HTMLPageContent;

class PartialExpanderTest extends PartialContentTestCase {
	public function testExpand_noMatchingPartial():void {
		$partialContent = self::mockPartialContent(
			"_partial", [
				"nothing" => "this doesn't exist",
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$sut = new PartialExpander(
			$document,
			$partialContent
		);
		self::expectException(PartialContentFileNotFoundException::class);
		self::assertEmpty($sut->expand());
	}

	public function testExpand():void {
		$partialContent = self::mockPartialContent(
			"_partial", [
				"base-page" => HTMLPageContent::HTML_PARTIAL_VIEW
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);

		$mainElement = $document->querySelector("body>main");
		self::assertNull($mainElement);

		$sut = new PartialExpander(
			$document,
			$partialContent
		);
		$expandedPartials = $sut->expand();
		$mainElement = $document->querySelector("body>main");
		self::assertNotNull($mainElement);

		self::assertCount(1, $expandedPartials);
		self::assertSame("base-page", $expandedPartials[0]);

		self::assertSame("My website!", $document->querySelector("body>header>h1")->textContent);
		self::assertStringContainsString(
			"The page content will go in here.",
			$mainElement->firstChild->textContent,
			"The original content of the partial element should not be removed."
		);

		self::assertSame("Hello from within a sub-template!", $mainElement->querySelector("h1")->textContent);
	}

	public function testExpand_commentVarsBound():void {
		$partialContent = self::mockPartialContent(
			"_partial", [
				"base-page" => HTMLPageContent::HTML_PARTIAL_VIEW
			]
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$binder = self::createMock(DocumentBinder::class);
		$binder->expects(self::once())
			->method("bindKeyValue")
			->with("title", "My website, extended...");

		$sut = new PartialExpander(
			$document,
			$partialContent
		);
		$sut->expand(binder: $binder);
	}

	public function testExpand_noExtendsSectionOfCommentIni():void {
		$document = new HTMLDocument();
		$partialContent = self::createStub(PartialContent::class);

		$sut = new PartialExpander($document, $partialContent);
		self::assertEmpty($sut->expand());
	}

	public function testExpand_recursive():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW_RECURSIVE);
		$partialContent = self::mockPartialContent(
			"_partial", [
				"extended-page" => HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW_RECURSIVE_BASE,
				"partial-base" => HTMLPageContent::HTML_PARTIAL_VIEW,
			]
		);
		$sut = new PartialExpander($document, $partialContent);
		$sut->expand();
		$body = $document->body;
		$main = $body->querySelector("main");
		$outer = $main->querySelector(".outer");
		$inner = $outer->querySelector(".inner");

		self::assertStringContainsString(
			"This is an inner DIV",
			$inner->querySelector("p")->textContent
		);
		self::assertStringContainsString(
			"This is the outer DIV",
			$outer->querySelector("p")->textContent
		);
		self::assertSame("This title was set in the inner partial view.", $document->title);
	}

	public function testExpand_noDataPartialElement():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$partialContent = self::mockPartialContent(
			"_partial", [
// Here, the HTML_COMPONENT isn't expected, because there is no data-partial element.
				"base-page" => HTMLPageContent::HTML_COMPONENT,
			]
		);
		$sut = new PartialExpander($document, $partialContent);
		self::expectException(PartialInjectionPointNotFoundException::class);
		self::expectExceptionMessage("The current view extends the partial \"base-page\", but there is no element marked with `data-partial`.");
		$sut->expand();
	}

	public function testExpand_multipleDataPartialElements():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$partialContent = self::mockPartialContent(
			"_partial", [
				"base-page" => HTMLPageContent::HTML_INCORRECT_PARTIAL_VIEW,
			]
		);
		$sut = new PartialExpander($document, $partialContent);
		self::expectException(PartialInjectionMultiplePointException::class);
		self::expectExceptionMessage("The current view extends the partial \"base-page\", but there is more than one element marked with `data-partial`.");
		$sut->expand();
	}

	public function testExpand_detectCyclicRecursion():void {
		$document = HTMLPageContent::createHTML(HTMLPageContent::HTML_EXTENDS_PARTIAL_CYCLIC_RECURSION);
		$partialContent = self::mockPartialContent(
			"_partial", [
				"extended-page-1" => HTMLPageContent::HTML_EXTENDS_PARTIAL_CYCLIC_RECURSION_1,
				"extended-page-2" => HTMLPageContent::HTML_EXTENDS_PARTIAL_CYCLIC_RECURSION_2,
				"partial-base" => HTMLPageContent::HTML_PARTIAL_VIEW,
			]
		);
		$sut = new PartialExpander($document, $partialContent);

		self::expectException(CyclicRecursionException::class);
		$sut->expand();
	}
}
