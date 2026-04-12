<?php
namespace Gt\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\BindableCache;
use Gt\DomTemplate\ElementBinder;
use Gt\DomTemplate\HTMLAttributeBinder;
use Gt\DomTemplate\HTMLAttributeCollection;
use Gt\DomTemplate\ListBinder;
use Gt\DomTemplate\ListElementCollection;
use Gt\DomTemplate\ListElementNotFoundInContextException;
use Gt\DomTemplate\PlaceholderBinder;
use Gt\DomTemplate\TableBinder;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use Gt\DomTemplate\Test\TestHelper\TestData;
use PHPUnit\Framework\TestCase;

class ListElementCollectionTest extends TestCase {
	public function testGet_noName_noMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new ListElementCollection($document);

		self::expectException(ListElementNotFoundInContextException::class);
		$sut->get($document->querySelector("ol"));
	}

	public function testGet_noName_noMatchIncludesContextDescription():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_ELEMENT_COLLECTION_CONTEXT);
		$sut = new ListElementCollection($document);

		self::expectException(ListElementNotFoundInContextException::class);
		self::expectExceptionMessage("div.one.two#example");
		$sut->get($document->querySelector("div"));
	}

	public function testGet_noName():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$ul = $document->querySelector("ul");
		$ol = $document->querySelector("ol");
		self::assertCount(1, $ul->children);
		self::assertCount(1, $ol->children);
		$sut = new ListElementCollection($document);
		self::assertCount(0, $ul->children);
		self::assertCount(1, $ol->children);
		$listElement = $sut->get($document);
		$inserted = $listElement->insertListItem();
		self::assertSame("li", $inserted->tagName);
		self::assertSame($ul, $listElement->getListItemParent());
		self::assertSame($ul, $inserted->parentElement);
	}

	public function testGet_name_noMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS);
		$sut = new ListElementCollection($document);

		self::expectException(ListElementNotFoundInContextException::class);
		self::expectExceptionMessage('List element with name "unknown-list" can not be found within the context html element.');
		$sut->get($document, "unknown-list");
	}

	public function testGet_name():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS);
		$sut = new ListElementCollection($document);

		$listElement = $sut->get($document, "prog-lang");
		self::assertSame(
			$document->getElementById("prog-lang-list"),
			$listElement->getListItemParent()
		);
	}

	/**
	 * Baby steps...(this comment was written as part of DomTemplate's TDD).
	 * Instead of jumping into the implementation of recursive nested list
	 * binding, we're going to manually iterate over a data source and
	 * bind the appropriate elements by their explicit list name.
	 *
	 * This is a manually-bound version of
	 * ListBinderTest::testBindListData_nestedList()
	 */
	public function testBindListData_nestedList_manual():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MUSIC_EXPLICIT_LIST_NAMES);
		$listElementCollection = new ListElementCollection($document);
		$elementBinder = new ElementBinder();
		$elementBinder->setDependencies(...$this->elementBinderDependencies($document));

		foreach(TestData::MUSIC as $artistName => $albumList) {
			$artistListItem = $listElementCollection->get($document, "artist");
			$artistElement = $artistListItem->insertListItem();
			$elementBinder->bind(null, $artistName, $artistElement);

			foreach($albumList as $albumName => $trackList) {
				$albumListItem = $listElementCollection->get($document, "album");
				$albumElement = $albumListItem->insertListItem();
				$elementBinder->bind(null, $albumName, $albumElement);

				foreach($trackList as $trackName) {
					$trackListItem = $listElementCollection->get($document, "track");
					$trackElement = $trackListItem->insertListItem();
					$elementBinder->bind(
						null,
						$trackName,
						$trackElement
					);
				}
			}
		}

		$artistNameArray = array_keys(TestData::MUSIC);
		foreach($document->querySelectorAll("body>ul>li") as $i => $artistElement) {
			$artistName = $artistNameArray[$i];
			self::assertEquals(
				$artistName,
				$artistElement->querySelector("h2")->textContent
			);

			$albumNameArray = array_keys(TestData::MUSIC[$artistName]);
			foreach($artistElement->querySelectorAll("ul>li") as $j => $albumElement) {
				$albumName = $albumNameArray[$j];
				self::assertEquals(
					$albumName,
					$albumElement->querySelector("h3")->textContent
				);

				$trackNameArray = TestData::MUSIC[$artistName][$albumName];
				foreach($albumElement->querySelectorAll("ol>li") as $k => $trackElement) {
					$trackName = $trackNameArray[$k];
					self::assertEquals(
						$trackName,
						$trackElement->textContent
					);
				}
			}
		}
	}

	public function testConstructor_removesWhitespace():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		new ListElementCollection($document);
		self::assertSame("", $document->querySelector("ul")->innerHTML);
	}

	public function testConstructor_nonListChildrenArePreserved():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_WITH_TEXT_NODE);
		new ListElementCollection($document);
		$ulChildren = $document->querySelector("ul")->children;
		self::assertCount(1, $ulChildren);
		self::assertSame("This list item will always show at the end", $ulChildren[0]->textContent);
	}

	public function testConstructor_nonListChildrenArePreservedInOrder():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_WITH_TEXT_NODE);
		$sut = new ListElementCollection($document);
		$ulChildren = $document->querySelector("ul")->children;
		$listElement = $sut->get($document);
		$listElement->insertListItem();
		$listElement->insertListItem();
		$listElement->insertListItem();
		self::assertCount(4, $ulChildren);
		self::assertSame("This list item will always show at the end", $ulChildren[3]->textContent);
	}

	private function elementBinderDependencies(HTMLDocument $document, mixed...$otherObjectList):array {
		$htmlAttributeBinder = new HTMLAttributeBinder();
		$htmlAttributeCollection = new HTMLAttributeCollection();
		$placeholderBinder = new PlaceholderBinder();
		$elementBinder = new ElementBinder();
		$listElementCollection = new ListElementCollection($document);
		$bindableCache = new BindableCache();
		$listBinder = new ListBinder();
		$tableBinder = new TableBinder();

		$htmlAttributeBinder->setDependencies($listBinder, $tableBinder);
		$elementBinder->setDependencies($htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);
		$listBinder->setDependencies($elementBinder, $listElementCollection, $bindableCache, $tableBinder);
		$tableBinder->setDependencies($listBinder, $listElementCollection, $elementBinder, $htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);

		return [
			$htmlAttributeBinder,
			$htmlAttributeCollection,
			$placeholderBinder,
		];
	}
}
