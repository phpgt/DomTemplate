<?php
namespace GT\DomTemplate\Test;

use ArrayIterator;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\Bind;
use GT\DomTemplate\BindableCache;
use GT\DomTemplate\BindGetter;
use GT\DomTemplate\ElementBinder;
use GT\DomTemplate\HTMLAttributeBinder;
use GT\DomTemplate\HTMLAttributeCollection;
use GT\DomTemplate\ListBinder;
use GT\DomTemplate\ListElementNotFoundInContextException;
use GT\DomTemplate\PlaceholderBinder;
use GT\DomTemplate\TableBinder;
use GT\DomTemplate\TableElementNotFoundInContextException;
use GT\DomTemplate\ListElementCollection;
use GT\DomTemplate\ListElement;
use GT\DomTemplate\Test\TestHelper\HTMLPageContent;
use GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Music\MusicFactory;
use GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student\Module as IteratorAggregateModule;
use GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student\Name as IteratorAggregateName;
use GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student\Student as IteratorAggregateStudent;
use GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Student\StudentFactory;
use GT\DomTemplate\Test\TestHelper\Model\Student;
use GT\DomTemplate\Test\TestHelper\TestData;
use PHPUnit\Framework\TestCase;
use Stringable;

class ListBinderTest extends TestCase {
	public function testBindList_emptyList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$boundCount = $sut->bindListData(
			[],
			$document
		);
		self::assertSame(0, $boundCount);
	}

	public function testBindList_empty_shouldHaveNoWhitespace():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData([], $document);
		self::assertSame("", $document->querySelector("ul")->innerHTML);
	}

	public function testBindList_emptyList_iterator():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);

		$templateParent = $document->querySelector("ul");
		$templateElement = self::createStub(ListElement::class);
		$templateElement->method("getListItemParent")
			->willReturn($templateParent);
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$boundCount = $sut->bindListData(
			new ArrayIterator([]),
			$document
		);
		self::assertSame(0, $boundCount);
	}

	public function testBindList_noMatchingTemplate():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$listElementCollection = self::createMock(ListElementCollection::class);
		$listElementCollection->expects(self::once())
			->method("get")
			->with($document->documentElement, "missing")
			->willReturnCallback(function() {
				throw new TableElementNotFoundInContextException();
			});

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		self::expectException(TableElementNotFoundInContextException::class);
		$sut->bindListData(
			["one", "two", "three"],
			$document,
			"missing"
		);
	}

	public function testBindList_simpleList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$templateElement = new ListElement($document->querySelector("li[data-list]"));

		$listElementCollection = self::createMock(ListElementCollection::class);
		$listElementCollection->expects(self::once())
			->method("get")
			->with($document->documentElement, null)
			->willReturn($templateElement);

		$templateElement->removeOriginalElement();

		$ul = $document->querySelector("ul");
		self::assertCount(
			0,
			$ul->children,
			"There should be no LI elements in the UL at the start of the test"
		);

		$testData = ["one", "two", "three"];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$boundCount = $sut->bindListData(
			$testData,
			$document
		);

		self::assertSame(count($testData), $boundCount);
		self::assertCount(
			$boundCount,
			$ul->children,
			"The correct number of LI elements should have been inserted into the UL"
		);

		foreach($testData as $i => $value) {
			self::assertSame($value, $ul->children[$i]->textContent);
		}
	}

	public function testBindList_sparseIndexedScalarList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_ASSOCIATIVE_SCALAR_WITH_KEY_BIND);
		$listData = [
			0 => "zero",
			2 => "two",
			5 => "five",
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$boundCount = $sut->bindListData($listData, $document);

		self::assertSame(count($listData), $boundCount);
		foreach($document->querySelectorAll("ul li") as $i => $li) {
			$key = array_keys($listData)[$i];
			self::assertSame((string)$key, $li->querySelector(".list-key")->textContent);
			self::assertSame($listData[$key], $li->querySelector(".list-value")->textContent);
		}
	}

	public function testBindListData_unnamedListIgnoresDeeperNamedList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_UNNAMED_LIST_WITH_DEEPER_NAMED_LIST);
		$component = $document->querySelector("venue-listing");
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData([
				[
					"id" => "venue-1",
					"name" => "Venue One",
					"slug" => "venue-one",
					"tag-list" => ["Tag one"],
				],
				[
					"id" => "venue-2",
					"name" => "Venue Two",
					"slug" => "venue-two",
					"tag-list" => ["Tag two", "Tag three"],
				],
			],
			$component,
		);

		self::assertCount(
			2,
			$component->querySelectorAll("ul.venues > li"),
			"The unnamed list should bind into the outer venue list.",
		);
		self::assertSame(
			"Venue One",
			$component->querySelector("ul.venues > li span")->textContent,
		);
		self::assertCount(
			0,
			$component->querySelectorAll("select option[value='venue-one']"),
			"The unnamed bind should not use the deeper named list element.",
		);
	}

	public function testBindList_filteredBindableObjectsWithoutZeroIndex():void {
		$listData = [
			new Student("A", "One", []),
			new Student("B", "Two", []),
		];
		$filteredList = array_filter(
			$listData,
			fn(Student $student):bool => $student->firstName === "B",
		);
		$document = new HTMLDocument(HTMLPageContent::HTML_STUDENT_LIST_NO_MODULE_LIST);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$boundCount = $sut->bindListData($filteredList, $document);

		self::assertSame([1], array_keys($filteredList));
		self::assertSame(1, $boundCount);
		self::assertSame("B Two", trim($document->querySelector("dd.name")->textContent));
	}

	public function testBindListData_filteredList():void {
		$listData = [
			"CAT1" => ["name" => "Cody", "colour" => "orange"],
			"CAT2" => ["name" => "Scarlett", "colour" => "tabby"],
			"CAT3" => ["name" => "Sonic", "colour" => "grey"],
		];
		$filteredListData = array_filter($listData, fn($item) => $item["colour"] !== "tabby");
		$document = new HTMLDocument(HTMLPageContent::HTML_CATS);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));

		$boundCount = $sut->bindListData($filteredListData, $document);
		self::assertSame(2, $boundCount);

		$filteredKeys = array_keys($filteredListData);
		foreach($document->querySelectorAll("li") as $i => $li) {
			$id = $li->dataset->get("id");
			$catName = $li->querySelector("p.name span")->textContent;
			$catColour = $li->querySelector("p.colour span")->textContent;
			self::assertSame($filteredKeys[$i], $id);
			self::assertSame($filteredListData[$filteredKeys[$i]]["name"], $catName);
			self::assertSame($filteredListData[$filteredKeys[$i]]["colour"], $catColour);
		}
	}

	public function testBindListData_existingChildren():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SELECT_OPTIONS_TEMPLATE_WITH_EXISTING_CHILDREN);
		$templateElement = new ListElement($document->querySelector("[data-list]"));

		$listElementCollection = self::createMock(ListElementCollection::class);
		$listElementCollection->expects(self::once())
			->method("get")
			->with($document->documentElement, null)
			->willReturn($templateElement);

		$templateElement->removeOriginalElement();

		$existingOptionList = $document->querySelectorAll("select[name='drink'] option");

		$testData = [
			["id" => "orange-juice", "name" => "Orange juice"],
			["id" => "bovril", "name" => "Bovril"],
			["id" => "almond-milk", "name" => "Almond Milk"],
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$bindCount = $sut->bindListData($testData, $document);

		$newOptionList = $document->querySelectorAll("select[name='drink'] option");
		$newOptionCount = count($newOptionList);
		self::assertSame($bindCount + count($existingOptionList), $newOptionCount);

		$expectedValues = [
			"",
			"orange-juice",
			"bovril",
			"almond-milk",
			"coffee",
			"tea",
			"chocolate",
			"soda",
			"water",
		];
		foreach($expectedValues as $i => $value) {
			self::assertSame($value, $newOptionList[$i]->value);
		}
	}

	public function testBindListData_associativeScalar_withReservedListKey():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_ASSOCIATIVE_SCALAR_WITH_KEY_BIND);
		$listData = [
			"key1" => "value1",
			"key2" => "value2",
			"key3" => "value3",
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		$listKeyElementList = $document->querySelectorAll("span.list-key");
		$listValueElementList = $document->querySelectorAll("span.list-value");

		self::assertCount(count($listData), $listKeyElementList);
		self::assertCount(count($listData), $listValueElementList);

		$i = 0;
		foreach($listData as $key => $value) {
			self::assertSame($key, $listKeyElementList[$i]->textContent);
			self::assertSame($value, $listValueElementList[$i]->textContent);
			$i++;
		}
	}

	public function testBindListData_templateList_unwrapTemplateElements():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TEMPLATE_LIST_DD_DT);
		$listData = [
			"key1" => "value1",
			"key2" => "value2",
			"key3" => "value3",
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		$templateList = $document->querySelectorAll("dl > template");
		$listKeyElementList = $document->querySelectorAll("dl > dt.list-key");
		$listValueElementList = $document->querySelectorAll("dl > dd.list-value");

		self::assertCount(0, $templateList);
		self::assertCount(count($listData), $listKeyElementList);
		self::assertCount(count($listData), $listValueElementList);

		$i = 0;
		foreach($listData as $key => $value) {
			self::assertSame($key, $listKeyElementList[$i]->textContent);
			self::assertSame($value, $listValueElementList[$i]->textContent);
			$i++;
		}
	}

	public function testBindListData_templateList_keepTemplateElements():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TEMPLATE_LIST_DD_DT_KEEP_TEMPLATE);
		$listData = [
			"key1" => "value1",
			"key2" => "value2",
			"key3" => "value3",
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		$templateList = $document->querySelectorAll("dl > template");
		self::assertCount(count($listData), $templateList);

		$i = 0;
		foreach($listData as $key => $value) {
			$templateInnerHTML = $templateList[$i]->innerHTML;
			self::assertStringContainsString(">$key</dt>", $templateInnerHTML);
			self::assertStringContainsString(">$value</dd>", $templateInnerHTML);
			$i++;
		}
	}

	/**
	 * This tests what happens when the context element has more than one
	 * element with a data-list attribute. In this test, we expect the
	 * two template elements to have different template names.
	 */
	public function testBindListData_twoLists():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS);
		$templateElementProgLang = new ListElement(
			$document->querySelector("#favourites li[data-list='prog-lang']")
		);
		$templateElementGame = new ListElement(
			$document->querySelector("#favourites li[data-list='game']")
		);

		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturnCallback(function(Element $documentElement, string $name)use($templateElementProgLang, $templateElementGame):ListElement {
				return $name === "game" ? $templateElementGame : $templateElementProgLang;
			});

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$progLangData = ["PHP", "HTML", "bash"];
		$sut->bindListData($progLangData, $document, "prog-lang");
		$gameData = ["Pac Man", "Mega Man", "Tetris"];
		$templateElementProgLang->removeOriginalElement();
		$templateElementGame->removeOriginalElement();
		$sut->bindListData($gameData, $document, "game");

		foreach($progLangData as $i => $progLang) {
			self::assertSame($progLang, $document->querySelectorAll("#prog-lang-list li")[$i]->textContent);
		}

		foreach($gameData as $i => $game) {
			self::assertSame($game, $document->querySelectorAll("#game-list li")[$i]->textContent);
		}
	}

	/**
	 * This is a slightly different test to above, where the context will
	 * be provided to specify the containing <UL> nodes, because the <LI>
	 * elements do not identify their own template name.
	 */
	public function testBindListData_twoListsDifferentContexts():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS_WITH_UNNAMED_TEMPLATES);
		$templateElementProgLang = new ListElement(
			$document->querySelector("#prog-lang-list li[data-list]")
		);
		$templateElementGame = new ListElement(
			$document->querySelector("#game-list li[data-list]")
		);

		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturnCallback(function(Element $element)use($templateElementProgLang, $templateElementGame):ListElement {
				return ($element->id === "prog-lang-list")
					? $templateElementProgLang
					: $templateElementGame;
			});

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$progLangData = ["PHP", "HTML", "bash"];
		$sut->bindListData($progLangData, $document->getElementById("prog-lang-list"));
		$gameData = ["Pac Man", "Mega Man", "Tetris"];
		$templateElementProgLang->removeOriginalElement();
		$templateElementGame->removeOriginalElement();
		$sut->bindListData($gameData, $document->getElementById("game-list"));

		foreach($progLangData as $i => $progLang) {
			self::assertSame($progLang, $document->querySelectorAll("#prog-lang-list li")[$i]->textContent);
		}

		foreach($gameData as $i => $game) {
			self::assertSame($game, $document->querySelectorAll("#game-list li")[$i]->textContent);
		}
	}

	public function testBindListData_empty_parentShouldBeEmpty():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$listElement = new ListElement($document->querySelector("li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($listElement);
		$listElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData([], $document);

		self::assertSame("", $document->querySelector("ul")->innerHTML);
	}

	public function testBindListData_kvpList_array():void {
		$kvpList = [
			["userId" => 543, "username" => "win95", "orderCount" => 55],
			["userId" => 559, "username" => "seafoam", "orderCount" => 30],
			["userId" => 274, "username" => "hammatime", "orderCount" => 23],
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$orderList = $document->querySelector("ul");

		$templateElement = new ListElement($document->querySelector("ul li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);
		$templateElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData($kvpList, $orderList);

		foreach($orderList->children as $i => $li) {
			self::assertEquals($kvpList[$i]["userId"], $li->querySelector("h3 span")->textContent);
			self::assertEquals($kvpList[$i]["username"], $li->querySelector("h2 span")->textContent);
			self::assertEquals($kvpList[$i]["orderCount"], $li->querySelector("p span")->textContent);
		}
	}

	public function testBindListData_kvpList_object():void {
		$kvpList = [
			(object)["userId" => 543, "username" => "win95", "orderCount" => 55],
			(object)["userId" => 559, "username" => "seafoam", "orderCount" => 30],
			(object)["userId" => 274, "username" => "hammatime", "orderCount" => 23],
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$orderList = $document->querySelector("ul");

		$templateElement = new ListElement($document->querySelector("ul li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);
		$templateElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData($kvpList, $orderList);

		foreach($orderList->children as $i => $li) {
			self::assertEquals($kvpList[$i]->{"userId"}, $li->querySelector("h3 span")->textContent);
			self::assertEquals($kvpList[$i]->{"username"}, $li->querySelector("h2 span")->textContent);
			self::assertEquals($kvpList[$i]->{"orderCount"}, $li->querySelector("p span")->textContent);
		}
	}

	/** @noinspection PhpUnused */
	public function testBindListData_kvpList_instanceObject():void {
		$kvpList = [
			new class { public int $userId = 543; public string $username = "win95"; public int $orderCount = 55; },
			new class { public int $userId = 559; public string $username = "seafoam"; public int $orderCount = 30; },
			new class { public int $userId = 274; public string $username = "hammatime"; public int $orderCount = 23; },
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$orderList = $document->querySelector("ul");

		$templateElement = new ListElement($document->querySelector("ul li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);
		$templateElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData($kvpList, $orderList);

		foreach($orderList->children as $i => $li) {
			self::assertEquals($kvpList[$i]->{"userId"}, $li->querySelector("h3 span")->textContent);
			self::assertEquals($kvpList[$i]->{"username"}, $li->querySelector("h2 span")->textContent);
			self::assertEquals($kvpList[$i]->{"orderCount"}, $li->querySelector("p span")->textContent);
		}
	}

	public function testBindListData_kvpList_instanceObjectWithBindAttributeMethods():void {
		$kvpList = [
			new class {
				/** @noinspection PhpUnused */
				#[Bind("userId")]
				public function getId():int {
					return 534;
				}
				/** @noinspection PhpUnused */
				#[Bind("username")]
				public function getUsername():string {
					return "win95";
				}
				/** @noinspection PhpUnused */
				#[Bind("this-matches-nothing")]
				public function getNothing():string {
					return "nothing!";
				}
				/** @noinspection PhpUnused */
				#[Bind("orderCount")]
				public function getTotalOrders():int {
					return 55;
				}
			},
			new class {
				/** @noinspection PhpUnused */
				#[Bind("userId")]
				public function getId():int {
					return 559;
				}
				/** @noinspection PhpUnused */
				#[Bind("username")]
				public function getUsername():string {
					return "seafoam";
				}
				/** @noinspection PhpUnused */
				#[Bind("orderCount")]
				public function getTotalOrders():int {
					return 30;
				}
			},
			new class {
				/** @noinspection PhpUnused */
				#[Bind("userId")]
				public function getId():int {
					return 274;
				}
				/** @noinspection PhpUnused */
				#[Bind("username")]
				public function getUsername():string {
					return "hammatime";
				}
				/** @noinspection PhpUnused */
				#[Bind("orderCount")]
				public function getTotalOrders():int {
					return 23;
				}
			},
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$orderList = $document->querySelector("ul");

		$templateElement = new ListElement($document->querySelector("ul li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);
		$templateElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData($kvpList, $orderList);

		foreach($orderList->children as $i => $li) {
			self::assertEquals($kvpList[$i]->getId(), $li->querySelector("h3 span")->textContent);
			self::assertEquals($kvpList[$i]->getUsername(), $li->querySelector("h2 span")->textContent);
			self::assertEquals($kvpList[$i]->getTotalOrders(), $li->querySelector("p span")->textContent);
		}
	}

	/** @noinspection PhpUnused */
	public function testBindListData_kvpList_instanceObjectWithBindAttributeProperties():void {
		$kvpList = [
			new class {
				#[Bind("userId")]
				public int $id = 534;

				#[Bind("username")]
				public string $user = "win95";

				#[Bind("this-matches-nothing")]
				public string $nothing = "nothing!";

				#[Bind("orderCount")]
				public int $totalOrders = 55;
			},
			new class {
				#[Bind("userId")]
				public int $id = 559;

				#[Bind("username")]
				public string $user = "seafoam";

				#[Bind("orderCount")]
				public int $totalOrders = 30;
			},
			new class {
				#[Bind("userId")]
				public int $id = 274;

				#[Bind("username")]
				public string $user = "hammatime";

				#[Bind("orderCount")]
				public int $totalOrders = 23;
			},
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$orderList = $document->querySelector("ul");

		$templateElement = new ListElement($document->querySelector("ul li[data-list]"));
		$listElementCollection = self::createStub(ListElementCollection::class);
		$listElementCollection->method("get")
			->willReturn($templateElement);
		$templateElement->removeOriginalElement();

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document, $listElementCollection));
		$sut->bindListData($kvpList, $orderList);

		foreach($orderList->children as $i => $li) {
			self::assertEquals($kvpList[$i]->id, $li->querySelector("h3 span")->textContent);
			self::assertEquals($kvpList[$i]->user, $li->querySelector("h2 span")->textContent);
			self::assertEquals($kvpList[$i]->totalOrders, $li->querySelector("p span")->textContent);
		}
	}

	public function testBindListData_nestedList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MUSIC_NO_TEMPLATE_NAMES);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData(TestData::MUSIC, $document);

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

	public function testBindListData_nestedList_withKvps():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_STUDENT_LIST);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData(TestData::STUDENTS, $document);

		foreach($document->querySelectorAll("body>ul>li") as $i => $studentLiElement) {
			self::assertEquals(
				TestData::STUDENTS[$i]["firstName"] . " " . TestData::STUDENTS[$i]["lastName"],
				$studentLiElement->querySelector(".name")->textContent
			);

			foreach($studentLiElement->querySelectorAll(".modules li") as $j => $moduleElement) {
				self::assertEquals(
					TestData::STUDENTS[$i]["modules"][$j],
					$moduleElement->textContent
				);
			}
		}
	}

	public function testBindListData_iterativeSomething():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SEQUENCES);
		$listData = [
			"Primes" => new ArrayIterator([2,3,5,7,11,13,17,19,23,29,31,37,41,43,47,53,59,61,67,71]),
			"Fibonacci" => new ArrayIterator([0,1,1,2,3,5,8,13,21,34,55,89,144,233,377,610,987,1597,2584,4181,6765]),
		];
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		$listDataKeys = array_keys($listData);
		foreach($document->querySelectorAll("ul>li") as $i => $sequenceLI) {
			self::assertEquals($listDataKeys[$i], $sequenceLI->querySelector("h2")->textContent);

			foreach($sequenceLI->querySelectorAll("ol>li") as $j => $numberLI) {
				self::assertEquals($listData[$listDataKeys[$i]][$j], $numberLI->textContent);
			}
		}
	}

	public function testBindListData_dateTime():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATES);
		$listData = [];

		$dateTime = new DateTime();
		$currentYear = $dateTime->format("Y");
		$dateTime->setDate($currentYear, 1, 1);

		while($dateTime->format("Y") === $currentYear) {
			array_push($listData, new class(clone $dateTime) implements Stringable {
				public function __construct(private readonly DateTime $dateTime) {}
				public function __toString():string {
					return $this->dateTime->format("F: l");
				}
			});
			$dateTime->add(new DateInterval("P1M"));
		}

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		foreach($document->querySelectorAll("li") as $i => $li) {
			self::assertSame((string)$listData[$i], $li->textContent);
		}
	}

	public function testBindListData_dateTimeAutomatic():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATES);
		/** @var array<DateTimeInterface> $listData */
		$listData = [];

		$dateTime = new DateTime();
		$currentYear = $dateTime->format("Y");
		$dateTime->setDate($currentYear, 1, 1);

		while($dateTime->format("Y") === $currentYear) {
			array_push($listData, clone $dateTime);
			$dateTime->add(new DateInterval("P1M"));
		}

		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($listData, $document);

		foreach($document->querySelectorAll("li") as $i => $li) {
			self::assertSame($listData[$i]->format(DateTimeInterface::RSS), $li->textContent);
		}
	}

	public function testBindListData_todoList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO);
		$data = TestData::TODO_DATA;
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($data, $document);

		$todoLiElements = $document->querySelectorAll("ul>li");
		foreach($data as $i => $todoItem) {
			$li = $todoLiElements[$i];
			self::assertEquals($todoItem["id"], $li->querySelector("[name=id]")->value);
			self::assertEquals($todoItem["title"], $li->querySelector("[name=title]")->value);
			if($todoItem["completedAt"]) {
				self::assertTrue($li->classList->contains("completed"));
			}
		}
	}

	public function testBindListData_multipleTemplateSiblings():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_GOOD_BAD);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));

		$sut->bindListData(["Good news 1", "Good news 2"], $document, "good");
		$sut->bindListData(["Bad news 1", "Bad news 2"], $document, "bad");
		$sut->bindListData(["Good news 3", "Good news 4"], $document, "good");
		$sut->bindListData(["Bad news 3", "Bad news 4"], $document, "bad");

		$expected = [
			"Good news 1",
			"Good news 2",
			"Bad news 1",
			"Bad news 2",
			"Good news 3",
			"Good news 4",
			"Bad news 3",
			"Bad news 4",
		];
		foreach($document->querySelectorAll("li") as $i => $li) {
			self::assertEquals($expected[$i], $li->querySelector("span")->textContent);
		}
	}

	/** @noinspection PhpUnusedParameterInspection */
	public function testBindListData_callback():void {
		$salesData = [
			[
				"name" => "Cactus",
				"count" => 14,
				"price" => 5.50,
				"cost" => 3.55,
			],
			[
				"name" => "Succulent",
				"count" => 9,
				"price" => 3.50,
				"cost" => 2.10,
			]
		];
		$salesCallback = function(Element $template, array $listItem, string $key):array {
			$totalPrice = $listItem["price"] * $listItem["count"];
			$totalCost = $listItem["cost"] * $listItem["count"];

			$listItem["profit"] = round($totalPrice - $totalCost, 2);
			return $listItem;
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_SALES);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData(
			$salesData,
			$document,
			callback: $salesCallback
		);

		$salesLiList = $document->querySelectorAll("ul>li");
		self::assertCount(count($salesData), $salesLiList);
		foreach($salesData as $i => $sale) {
			$li = $salesLiList[$i];
			$profitValue = round(($sale["count"] * $sale["price"]) - ($sale["count"] * $sale["cost"]), 2);
			self::assertEquals($sale["name"], $li->querySelector(".name span")->textContent);
			self::assertEquals($sale["count"], $li->querySelector(".count span")->textContent);
			self::assertEquals($sale["price"], $li->querySelector(".price span")->textContent);
			self::assertEquals($sale["cost"], $li->querySelector(".cost span")->textContent);
			self::assertEquals($profitValue, $li->querySelector(".profit span")->textContent);
		}
	}

	public function testBindListData_complexStructure():void {
		$customerOrderData = TestData::getCustomerOrderOverview1();
		$document = new HTMLDocument(HTMLPageContent::HTML_MAP_SHOP_CUSTOMER_OVERVIEW);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($customerOrderData, $document);

		foreach($customerOrderData as $customerIndex => $customer) {
			$customerLi = $document->querySelectorAll("customer-list>ul>li")[$customerIndex];

			self::assertSame((string)$customer->id, $customerLi->querySelectorAll("customer-details>dl>dd")[0]->textContent);
			self::assertSame($customer->name, $customerLi->querySelectorAll("customer-details>dl>dd")[1]->textContent);
			self::assertSame($customer->address->street, $customerLi->querySelectorAll("customer-details>dl>dd")[2]->querySelectorAll("span")[0]->textContent);
			self::assertSame($customer->address->line2, $customerLi->querySelectorAll("customer-details>dl>dd")[2]->querySelectorAll("span")[1]->textContent);
			self::assertSame($customer->address->cityState, $customerLi->querySelectorAll("customer-details>dl>dd")[2]->querySelectorAll("span")[2]->textContent);
			self::assertSame($customer->address->postcodeZip, $customerLi->querySelectorAll("customer-details>dl>dd")[2]->querySelectorAll("span")[3]->textContent);
			self::assertSame($customer->address->country->getName(), $customerLi->querySelectorAll("customer-details>dl>dd")[2]->querySelectorAll("span")[4]->textContent);

			foreach($customer->orderList as $orderIndex => $order) {
				$orderLi = $customerLi->querySelectorAll("order-list>ul>li")[$orderIndex];
				self::assertSame($order->shippingAddress->cityState, $orderLi->querySelectorAll("dl dd")[0]->textContent);
				self::assertSame((string)$order->getSubtotal(), $orderLi->querySelectorAll("dl dd")[1]->textContent);
				self::assertSame((string)$order->shippingCost, $orderLi->querySelectorAll("dl dd")[2]->textContent);
				self::assertSame((string)$order->getTotalCost(), $orderLi->querySelectorAll("dl dd")[3]->textContent);

				foreach($order->itemList as $itemIndex => $item) {
					self::assertSame($item->title, $orderLi->querySelectorAll("ul>li")[$itemIndex]->querySelector("h4")->textContent);
					self::assertSame("/item/$item->id", $orderLi->querySelectorAll("ul>li")[$itemIndex]->querySelector("h4 a")->href);
					self::assertSame((string)$item->cost, $orderLi->querySelectorAll("ul>li")[$itemIndex]->querySelector("p")->textContent);
				}
			}
		}
	}

	public function testBindListData_objectWithArrayProperties():void {
		$list = [
			new Student("Abbey", "Appleby", ["one", "two", "three"]),
			new Student("Bruna", "Biltsworth", ["four", "five", "six"]),
			new Student("Charlie", "Chudder", ["seven", "eight", "nine"]),
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_STUDENT_LIST);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($list, $document);

		self::assertCount(count($list), $document->querySelectorAll("body>ul>li"));
		foreach($document->querySelectorAll("dl") as $i => $dlElement) {
			$student = $list[$i];
			self::assertSame("$student->firstName $student->lastName", $dlElement->querySelector("dd.name")->textContent);
			$moduleLiElementList = $dlElement->querySelectorAll("dd.modules li");
			$moduleList = $student->getModuleList();
			self::assertCount(count($moduleList), $moduleLiElementList);

			foreach($moduleLiElementList as $j => $moduleLiElement) {
				self::assertSame($moduleList[$j], $moduleLiElement->textContent);
			}
		}
	}

	public function testBindListData_objectWithArrayProperties_noNestedList():void {
		$list = [
			new Student("Abbey", "Appleby", ["one", "two", "three"]),
			new Student("Bruna", "Biltsworth", ["four", "five", "six"]),
			new Student("Charlie", "Chudder", ["seven", "eight", "nine"]),
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_STUDENT_LIST_NO_MODULE_LIST);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$numBound = $sut->bindListData($list, $document);
		self::assertCount(count($list), $document->querySelectorAll("body>ul>li"));
		self::assertSame(count($list), $numBound);
	}

	public function testBindListData_noListInDocument():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		self::expectException(ListElementNotFoundInContextException::class);
		$sut->bindListData(["one", "two", "three"], $document);
	}

	private function listBinderDependencies(HTMLDocument $document, mixed...$otherObjectList):array {
		$htmlAttributeBinder = new HTMLAttributeBinder();
		$htmlAttributeCollection = new HTMLAttributeCollection();
		$placeholderBinder = new PlaceholderBinder();
		$elementBinder = new ElementBinder();
		$listElementCollection = new ListElementCollection($document);
		$bindableCache = new BindableCache();
		$listBinder = new ListBinder();
		$tableBinder = new TableBinder();

		foreach($otherObjectList as $object) {
			if($object instanceof HTMLAttributeBinder) {
				$htmlAttributeBinder = $object;
			}
			elseif($object instanceof HTMLAttributeCollection) {
				$htmlAttributeCollection = $object;
			}
			elseif($object instanceof ElementBinder) {
				$elementBinder = $object;
			}
			elseif($object instanceof PlaceholderBinder) {
				$placeholderBinder = $object;
			}
			elseif($object instanceof TableBinder) {
				$tableBinder = $object;
			}
			elseif($object instanceof ListBinder) {
				$listBinder = $object;
			}
			elseif($object instanceof ListElementCollection) {
				$listElementCollection = $object;
			}
			elseif($object instanceof BindableCache) {
				$bindableCache = $object;
			}
		}

		$htmlAttributeBinder->setDependencies($listBinder, $tableBinder);
		$elementBinder->setDependencies($htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);
		$listBinder->setDependencies($elementBinder, $listElementCollection, $bindableCache, $tableBinder);
		$tableBinder->setDependencies($listBinder, $listElementCollection, $elementBinder, $htmlAttributeBinder, $htmlAttributeCollection, $placeholderBinder);

		return [
			$elementBinder,
			$listElementCollection,
			$bindableCache,
			$tableBinder,
		];
	}

	public function testBindListData_objectWithPublicIterable():void {
		$obj1 = new class("First") {
			public function __construct(public string $name) {}
			#[BindGetter]
			public function getLettersOfName():array {
				return str_split($this->name, 1);
			}
		};
		$obj2 = new class("Second") {
			public function __construct(public string $name) {}
			#[BindGetter]
			public function getLettersOfName():array {
				return str_split($this->name, 1);
			}
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_BIND_NAME);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData([
			$obj1,
			$obj2,
		], $document);

		$nodeList = $document->querySelectorAll("ul li");
		self::assertCount(2, $nodeList);
		self::assertSame("First", $nodeList[0]->textContent);
		self::assertSame("Second", $nodeList[1]->textContent);
	}

	public function testBindListData_iteratorAggregate():void {
		$testData = (new MusicFactory())->buildArtistArray(TestData::MUSIC);
		$document = new HTMLDocument(HTMLPageContent::HTML_MUSIC_NO_TEMPLATE_NAMES);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($testData, $document);

		$arrayArtistData = TestData::MUSIC;

		foreach($document->querySelectorAll("body>ul>li") as $artistLi) {
			$artistName = key($arrayArtistData);
			$arrayAlbumData = current($arrayArtistData);

			self::assertSame($artistName, $artistLi->querySelector("h2")->textContent);

			foreach($artistLi->querySelectorAll("ul>li") as $albumLi) {
				$albumName = key($arrayAlbumData);
				$arrayTrackData = current($arrayAlbumData);

				self::assertSame($albumName, $albumLi->querySelector("h3")->textContent);

				foreach($albumLi->querySelectorAll("ol>li") as $trackLi) {
					$trackName = current($arrayTrackData);
					self::assertSame($trackName, $trackLi->textContent);
					next($arrayTrackData);
				}

				next($arrayAlbumData);
			}

			next($arrayArtistData);
		}
	}

	/**
	 * This test asserts that the outer element has its data bound correctly.
	 * We know that the nested sub-lists are bound correctly from the music examples,
	 * but there's a bug where the outer element (representing the Student in this case)
	 * does not have its data bound correctly.
	 */
	public function testBindListData_iteratorAggregate_outerBinds():void {
		$testData = (new StudentFactory())->buildStudentArray(TestData::STUDENTS);
		$document = new HTMLDocument(HTMLPageContent::HTML_STUDENT_LIST_EXPLICIT_BINDS);
		$sut = new ListBinder();
		$sut->setDependencies(...$this->listBinderDependencies($document));
		$sut->bindListData($testData, $document);

		$arrayStudentData = TestData::STUDENTS;

		foreach($document->querySelectorAll("body>ul>li") as $studentLi) {
			$arrayStudent = current($arrayStudentData);
			$firstName = $arrayStudent["firstName"];
			$lastName = $arrayStudent["lastName"];

			self::assertSame(
				"$firstName $lastName",
				trim(preg_replace("/\s+/", " ", $studentLi->querySelector("dd.name")->textContent))
			);

			$arrayModuleData = $arrayStudent["modules"];
			foreach($studentLi->querySelectorAll(".modules ul>li") as $moduleLi) {
				$moduleTitle = current($arrayModuleData);

				self::assertSame($moduleTitle, $moduleLi->textContent);

				next($arrayModuleData);
			}

			next($arrayStudentData);
		}
	}
}
