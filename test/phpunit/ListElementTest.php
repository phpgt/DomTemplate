<?php
namespace GT\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use GT\DomTemplate\InvalidListElementNameException;
use GT\DomTemplate\ListElement;
use GT\DomTemplate\Test\TestHelper\HTMLPageContent;
use PHPUnit\Framework\TestCase;

class ListElementTest extends TestCase {
	public function testGetListItemName_forwardSlashStarter():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EMPTY);
		$originalElement = $document->createElement("div");
		$originalElement->setAttribute("data-list", "/oh/dear/oh/dear");
		$document->body->appendChild($originalElement);
		$sut = new ListElement($originalElement);
		self::expectException(InvalidListElementNameException::class);
		self::expectExceptionMessage('A list\'s name must not start with a forward slash ("/oh/dear/oh/dear")');
		$sut->getListItemName();
	}

	public function testNextElementSibling():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_ELEMENT_WITH_MULTIPLE_DIVS);
		$originalElement = $document->querySelector("[data-list]");
		$originalElementNextElementSibling = $originalElement->nextElementSibling;

		$sut = new ListElement($originalElement);
		$sut->removeOriginalElement();
		self::assertSame($originalElementNextElementSibling, $sut->getListItemNextSibling());
	}

	public function testRemoveOriginalElement_detachedParent():void {
		$document = new HTMLDocument('<ul><li data-list="item">Example</li></ul>');
		$parent = $document->querySelector("ul");
		$originalElement = $parent->firstElementChild;
		$sut = new ListElement($originalElement);
		$parent->remove();

		$sut->removeOriginalElement();
		self::assertNull($originalElement->parentElement);
		self::assertCount(0, $parent->children);

		$document->body->appendChild($parent);
		$inserted = $sut->insertListItem();
		self::assertSame($parent, $inserted->parentElement);
		self::assertSame("Example", $inserted->textContent);
	}

	public function testInsertListItem():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_ELEMENT_WITH_MULTIPLE_DIVS);
		$originalElement = $document->querySelector("[data-list]");
		$originalElementNextElementSibling = $originalElement->nextElementSibling;

		$sut = new ListElement($originalElement);
		$sut->removeOriginalElement();
		$inserted = $sut->insertListItem();
		self::assertSame($originalElementNextElementSibling, $inserted->nextElementSibling);
	}

	public function testGetListItemName_dataTemplate():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EMPTY);
		$originalElement = $document->createElement("template");
		$originalElement->setAttribute("data-template", "example-template");
		$document->body->appendChild($originalElement);
		$sut = new ListElement($originalElement);
		self::assertSame("example-template", $sut->getListItemName());
	}

	public function testIsNamed():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EMPTY);
		$unnamedElement = $document->createElement("li");
		$unnamedElement->setAttribute("data-list", "");
		$document->body->appendChild($unnamedElement);
		$namedElement = $document->createElement("li");
		$namedElement->setAttribute("data-list", "named-list");
		$document->body->appendChild($namedElement);

		self::assertFalse((new ListElement($unnamedElement))->isNamed());
		self::assertTrue((new ListElement($namedElement))->isNamed());
	}

	public function testFinalizeListItem_noParentDoesNothing():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EMPTY);
		$template = $document->createElement("template");
		$template->setAttribute("data-list", "example");
		$template->innerHTML = "<li>Example</li>";
		$document->body->appendChild($template);

		$sut = new ListElement($template);
		$sut->removeOriginalElement();
		$proxy = $sut->insertListItem();
		$proxy->remove();

		$sut->finalizeListItem($proxy);
		self::assertCount(0, $document->body->children);
	}
}
