<?php
namespace Gt\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\CommentIni;
use Gt\DomTemplate\CommentIniInvalidDocumentLocationException;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use PHPUnit\Framework\TestCase;

class CommentIniTest extends TestCase {
	public function testConstruct_nullWhenNoCommentBlock():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new CommentIni($document);
		self::assertFalse($sut->containsIniData());
	}

	public function testConstruct_throwsIfCommentBlockNotFirst():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INCORRECTLY_EXTENDS_PARTIAL_VIEW);
		self::expectException(CommentIniInvalidDocumentLocationException::class);
		self::expectExceptionMessage("A Comment INI must only appear as the first node of the HTML.");
		new CommentIni($document);
	}

	public function testContainsIniData_emptyIfNoIniData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMMENT_WITHOUT_INI_DATA_PARTIAL_VIEW);
		$sut = new CommentIni($document);
		self::assertFalse($sut->containsIniData());
	}

	public function testContainsIniData_emptyIfMalformedIniSyntax():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMMENT_WITH_MALFORMED_INI_DATA_PARTIAL_VIEW);
		$sut = new CommentIni($document);
		self::assertFalse($sut->containsIniData());
		self::assertNull($sut->get("extends"));
		self::assertStringContainsString("something: broken", $document->saveHTML());
	}

	public function testGet_noMatchingData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$sut = new CommentIni($document);
		self::assertNull($sut->get("no-match"));
	}

	public function testGet():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$sut = new CommentIni($document);
		self::assertEquals("base-page", $sut->get("extends"));
	}

	public function testGetNested():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_EXTENDS_PARTIAL_VIEW);
		$sut = new CommentIni($document);
		self::assertEquals("My website, extended...", $sut->get("vars.title"));
	}
}
