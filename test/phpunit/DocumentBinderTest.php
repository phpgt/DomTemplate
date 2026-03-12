<?php /** @noinspection ALL */
/** @noinspection PhpUnused */
namespace Gt\DomTemplate\Test;

use DateInterval;
use Exception;
use Gt\Dom\Document;
use Gt\Dom\Element;
use Gt\Dom\HTMLCollection;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\Bind;
use Gt\DomTemplate\BindableCache;
use Gt\DomTemplate\BindGetter;
use Gt\DomTemplate\BindValue;
use Gt\DomTemplate\DocumentBinder;
use Gt\DomTemplate\ElementBinder;
use Gt\DomTemplate\HTMLAttributeBinder;
use Gt\DomTemplate\HTMLAttributeCollection;
use Gt\DomTemplate\IncompatibleBindDataException;
use Gt\DomTemplate\InvalidBindPropertyException;
use Gt\DomTemplate\ListBinder;
use Gt\DomTemplate\ListElementCollection;
use Gt\DomTemplate\PlaceholderBinder;
use Gt\DomTemplate\TableBinder;
use Gt\DomTemplate\TableElementNotFoundInContextException;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use Gt\DomTemplate\Test\TestHelper\ExampleClass;
use Gt\DomTemplate\Test\TestHelper\Model\Address;
use Gt\DomTemplate\Test\TestHelper\Model\ArrayIterator\Product\ProductList;
use Gt\DomTemplate\Test\TestHelper\Model\Country;
use Gt\DomTemplate\Test\TestHelper\Model\Customer;
use PHPUnit\Framework\TestCase;
use stdClass;
use IteratorAggregate;
use Traversable;
use ArrayIterator;

class DocumentBinderTest extends TestCase {
	/**
	 * If the developer forgets to add a bind property (the bit after the
	 * colon in `data-bind:text`, we should let them know with a friendly
	 * error message.
	 */
	public function testBindValue_missingBindProperty():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_NO_BIND_PROPERTY);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		self::expectException(InvalidBindPropertyException::class);
		self::expectExceptionMessage("<output> Element has a data-bind attribute with missing bind property - did you mean `data-bind:text`?");
		$sut->bindValue("Test!");
	}

	public function testBindValue_singleElement():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$output = $document->querySelector("output");
		self::assertSame("Nothing is bound", $output->textContent);
		$sut->bindValue("Test!");
		self::assertSame("Test!", $output->textContent);
	}

	public function testBindValue_multipleElements():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$output1 = $document->getElementById("o1");
		$output2 = $document->getElementById("o2");
		$output3 = $document->getElementById("o3");
		$sut->bindValue("Test!");
		self::assertSame("Test!", $output1->textContent);
		self::assertSame("Test!", $output2->textContent);
		self::assertSame("Test!", $output3->textContent);
	}

	public function testBindValue_multipleNestedElements():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_NESTED_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$container1 = $document->getElementById("container1");
		$container2 = $document->getElementById("container2");
		$sut->bindValue("Test!", $container1);

		foreach($container1->querySelectorAll("output") as $output) {
			self::assertSame("Test!", $output->textContent);
		}
		foreach($container2->querySelectorAll("output") as $output) {
			self::assertNotSame("Test!", $output->textContent);
		}

		$sut->bindValue("Test!", $container2);
		foreach($container1->querySelectorAll("output") as $output) {
			self::assertSame("Test!", $output->textContent);
		}
		foreach($container2->querySelectorAll("output") as $output) {
			self::assertSame("Test!", $output->textContent);
		}
	}

	public function testBindValue_multipleNestedElements_skipsElementWithBindProperty():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_NESTED_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$container3 = $document->getElementById("container3");
		$sut->bindValue("Test!", $container3);
		self::assertSame("Default title", $document->querySelector("#container3 h1")->textContent);
		self::assertSame("Test!", $document->getElementById("o7")->textContent);
	}

	public function testBindValue_synonymousProperties():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SYNONYMOUS_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindValue("updated <b>bold</b>");

		self::assertSame("updated &lt;b&gt;bold&lt;/b&gt;", $document->getElementById("o1")->innerHTML);
		self::assertSame("updated &lt;b&gt;bold&lt;/b&gt;", $document->getElementById("o2")->innerHTML);
		self::assertSame("updated &lt;b&gt;bold&lt;/b&gt;", $document->getElementById("o3")->innerHTML);
		self::assertSame("updated &lt;b&gt;bold&lt;/b&gt;", $document->getElementById("o4")->innerHTML);
		self::assertSame("updated &lt;b&gt;bold&lt;/b&gt;", $document->getElementById("o5")->innerHTML);
		self::assertSame("updated <b>bold</b>", $document->getElementById("o6")->innerHTML);
		self::assertSame("updated <b>bold</b>", $document->getElementById("o7")->innerHTML);
		self::assertSame("updated <b>bold</b>", $document->getElementById("o8")->innerHTML);
		self::assertSame("updated <b>bold</b>", $document->getElementById("o9")->innerHTML);
	}

	public function testBindValue_null():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$exception = null;
		try {
			$sut->bindValue(null);
		}
		catch(Exception $exception) {}

		self::assertNull($exception);
	}

	public function testBindKeyValue_noMatches():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("missing", "example");
		self::assertSame("Nothing is bound", $document->querySelector("output")->innerHTML);
	}

	public function testBindKeyValue_noMatchesInDifferentHierarchy():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_NESTED_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
// The "title" bind element is actually within the #c3 hierarchy so should not be bound.
		$sut->bindKeyValue("title", "This should not bind", $document->getElementById("container1"));
		self::assertSame("Default title", $document->querySelector("#container3 h1")->textContent);
	}

	public function testBindKeyValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_NESTED_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("title", "This should bind");
		self::assertSame("This should bind", $document->querySelector("#container3 h1")->textContent);
		self::assertSame("This should bind", $document->querySelector("#container3 p span")->textContent);
	}

	public function testBindKeyValue_null():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_NESTED_ELEMENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$exception = null;
		try {
			$sut->bindKeyValue("title", null);
		}
		catch(Exception $exception) {}

		self::assertNull($exception);
		self::assertSame("Default title", $document->querySelector("#container3 h1")->textContent);
		self::assertSame("default title", $document->querySelector("#container3 p span")->textContent);
	}

	public function testBindData_assocArray():void {
		$username = uniqid("user");
		$email = uniqid() . "@example.com";
		$category = uniqid("category-");

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData([
			"username" => $username,
			"email" => $email,
			"category" => $category,
		]);

		self::assertSame($username, $document->getElementById("dd1")->textContent);
		self::assertSame($email, $document->getElementById("dd2")->textContent);
		self::assertSame($category, $document->getElementById("dd3")->textContent);
	}

	public function testBindData_assocArray_withNull():void {
		$username = uniqid("user");
		$email = null;
		$category = uniqid("category-");

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData([
			"username" => $username,
			"email" => $email,
			"category" => $category,
		]);

		self::assertSame($username, $document->getElementById("dd1")->textContent);
		self::assertSame("you@example.com", $document->getElementById("dd2")->textContent);
		self::assertSame($category, $document->getElementById("dd3")->textContent);
	}

	public function testBindData_indexedArray():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		self::expectException(IncompatibleBindDataException::class);
		self::expectExceptionMessage("bindData is only compatible with key-value-pair data, but it was passed an indexed array.");
		$sut->bindData(["one", "two", "three"]);
	}

	public function testBindData_object():void {
		$userObject = new StdClass();
		$userObject->username = "g105b";
		$userObject->email = "greg.bowler@g105b.com";
		$userObject->category = "maintainer";

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($userObject);

		self::assertSame($userObject->username, $document->getElementById("dd1")->textContent);
		self::assertSame($userObject->email, $document->getElementById("dd2")->textContent);
		self::assertSame($userObject->category, $document->getElementById("dd3")->textContent);
	}

	public function testBindData_objectWithNonScalarProperties():void {
		$email = new class() extends StdClass {
			#[BindGetter]
			public function getEmail():string {
				return "greg.bowler@g105b.com";
			}
		};

		$userObject = new class("g105b", $email, "maintainer") {
			public function __construct(
				public readonly string $username,
				public readonly StdClass $email,
				public readonly string $category,
			) {}
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($userObject);

		self::assertSame($userObject->username, $document->getElementById("dd1")->textContent);
		self::assertSame($userObject->category, $document->getElementById("dd3")->textContent);
// The email address should show the default value, as the provided object is not Stringable or a scalar.
		self::assertSame("you@example.com", $document->getElementById("dd2")->textContent);
// But we should be able to bind manually:
		$sut->bindKeyValue("email", $userObject->email->getEmail());
		self::assertSame("greg.bowler@g105b.com", $document->getElementById("dd2")->textContent);
	}

	public function testBindData_objectWithNonScalarProperties_stringable():void {
		$email = new class() implements \Stringable {
			#[BindGetter]
			public function getEmail():string {
				return "greg.bowler@g105b.com";
			}

			public function __toString():string {
				return $this->getEmail();
			}
		};

		$userObject = new class("g105b", $email, "maintainer") {
			public function __construct(
				public readonly string $username,
				public readonly object $email,
				public readonly string $category,
			) {}
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($userObject);

		self::assertSame($userObject->username, $document->getElementById("dd1")->textContent);
		self::assertSame($userObject->category, $document->getElementById("dd3")->textContent);
// The email address should show the provided value, as the email object implements Stringable.
		self::assertSame("greg.bowler@g105b.com", $document->getElementById("dd2")->textContent);
	}

	public function testBindData_classWithReadonlyProperties():void {
		$userObject = new class("g105b", "greg.bowler@g105b.com") {
			public function __construct(
				public readonly string $username,
				public readonly string $email,
				public readonly ?string $category = null,
			) {}
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($userObject);

		self::assertSame($userObject->username, $document->getElementById("dd1")->textContent);
		self::assertSame($userObject->email, $document->getElementById("dd2")->textContent);
		self::assertSame("N/A", $document->getElementById("dd3")->textContent);
	}

	public function testBindData_object_withNull():void {
		$userObject = new StdClass();
		$userObject->username = "g105b";
		$userObject->email = "greg.bowler@g105b.com";
		$userObject->category = null;

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($userObject);

		self::assertSame($userObject->username, $document->getElementById("dd1")->textContent);
		self::assertSame($userObject->email, $document->getElementById("dd2")->textContent);
		self::assertSame("N/A", $document->getElementById("dd3")->textContent);
	}

	public function testBindData_indexArray_shouldThrowException():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		self::expectException(IncompatibleBindDataException::class);
		self::expectExceptionMessage("bindData is only compatible with key-value-pair data, but it was passed an indexed array.");
		$sut->bindData(["one", "two", "three"]);
	}

	public function testBindData_outOfContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData([
			"username" => "will-not-bind",
			"email" => "will-not-bind",
			"category" => "will-not-bind",
		], $document->getElementById("audit-trail"));

		self::assertNotSame("will-not-bind", $document->getElementById("dd1")->textContent);
		self::assertNotSame("will-not-bind", $document->getElementById("dd2")->textContent);
		self::assertNotSame("will-not-bind", $document->getElementById("dd3")->textContent);
	}

	public function testBindKeyValue_arbitraryAttributes():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$img = $document->getElementById("img1");

		$sut->bindKeyValue("photoURL", "/cat.jpg");
		self::assertSame("/cat.jpg", $img->src);

		$sut->bindKeyValue("altText", "My cat");
		self::assertSame("My cat", $img->alt);
	}

	public function testBindKeyValue_classAttribute():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$img = $document->getElementById("img1");

		self::assertSame("main", $img->className);
		$sut->bindKeyValue("size", "large");
		self::assertSame("main large", $img->className);
		$sut->bindKeyValue("size", "large");
		self::assertSame("main large", $img->className);
	}

	public function testBindKeyValue_classToggle():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$img = $document->getElementById("img2");

		self::assertSame("secondary", $img->className);
		$sut->bindKeyValue("is-selected", true, $img);
		self::assertSame("secondary is-selected", $img->className);
		$sut->bindKeyValue("is-selected", false, $img);
		self::assertSame("secondary", $img->className);
	}

	public function testBindKeyValue_classToggle_differentClassNameToBindKey():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$img = $document->getElementById("img3");

		self::assertSame("secondary", $img->className);
		$sut->bindKeyValue("isSelected", true, $img);
		self::assertSame("secondary selected-image", $img->className);
		$sut->bindKeyValue("isSelected", false, $img);
		self::assertSame("secondary", $img->className);
	}

	public function testBindKeyValue_toggleArbitraryAttribute():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$paragraph = $document->getElementById("p1");

		self::assertSame("funny friendly", $paragraph->dataset->get("params"));
		$sut->bindKeyValue("isMagic", false, $paragraph);
		self::assertSame("funny friendly", $paragraph->dataset->get("params"));
		$sut->bindKeyValue("isMagic", true, $paragraph);
		self::assertSame("funny friendly magical", $paragraph->dataset->get("params"));
		$sut->bindKeyValue("isMagic", false, $paragraph);
		self::assertSame("funny friendly", $paragraph->dataset->get("params"));
	}

	/**
	 * This tests the `data-bind:disabled="?isDisabled" functionality. The
	 * question mark at the start of the bind parameter indicates that the
	 * bind attribute will be toggled depending on a bound boolean value.
	 */
	public function testBindKeyValue_toggleDisabled():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$button = $document->getElementById("btn1");

		self::assertFalse($button->disabled);
		$sut->bindKeyValue("isBtn1Disabled", true);
		self::assertTrue($button->disabled);
		$sut->bindKeyValue("isBtn1Disabled", false);
		self::assertFalse($button->disabled);
	}

	/**
	 * This tests the inverse logic of the above test. The bind parameter
	 * is prefixed with a question mark AND an exclamation mark, meaning to
	 * use the inverse of what is passed. This makes sense for the
	 * "disabled" attribute, because it is likely that the data represents
	 * whether the element should be enabled (but there's no "enabled"
	 * HTML attribute).
	 */
	public function testBindKeyValue_toggleDisabled_inverseLogic():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$button = $document->getElementById("btn2");

		self::assertFalse($button->disabled);
		$sut->bindKeyValue("isBtn2Enabled", false);
		self::assertTrue($button->disabled);
		$sut->bindKeyValue("isBtn2Enabled", true);
		self::assertFalse($button->disabled);
	}

	public function testBindKeyValue_tableData_noTable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_NO_TABLE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		self::expectException(TableElementNotFoundInContextException::class);
		$sut->bindKeyValue("tableData", []);
	}

	public function testBindTable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$tableData = [
			["Name", "Position"],
			["Alan Statham", "Head of Radiology"],
			["Sue White", "Staff Liason Officer"],
			["Mac Macartney", "General Surgeon"],
			["Joanna Clore", "HR"],
			["Caroline Todd", "Surgical Registrar"],
		];

		$table = $document->getElementById("tbl1");
		$sut->bindTable($tableData, $table, "tableData");

		foreach($tableData as $rowIndex => $rowData) {
			$row = $table->rows[$rowIndex];

			foreach($rowData as $cellIndex => $cellValue) {
				self::assertSame(
					$cellValue,
					$row->cells[$cellIndex]->textContent
				);
			}
		}
	}

	public function testBindTable_withNullData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$tableData = [
			["Name", "Position"],
			["Alan Statham", "Head of Radiology"],
			["Sue White", "Staff Liason Officer"],
			["Mac Macartney", null],
			["Joanna Clore", "HR"],
			["Caroline Todd", null],
		];

		$exception = null;
		$table = $document->getElementById("tbl1");
		try {
			$sut->bindTable($tableData, $table, "tableData");
		}
		catch(Exception $exception) {}
		self::assertNull($exception);

		foreach($tableData as $rowIndex => $rowData) {
			$row = $table->rows[$rowIndex];

			foreach($rowData as $cellIndex => $cellValue) {
				if(($rowIndex === 3 || $rowIndex === 5)
				&& $cellIndex === 1) {
					self::assertSame(
						"",
						$row->cells[$cellIndex]->textContent
					);
				}
				else {
					self::assertSame(
						$cellValue,
						$row->cells[$cellIndex]->textContent
					);
				}
			}
		}
	}

	public function testBindKeyValue_tableData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TABLES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$tableData = [
			["Name", "Position"],
			["Alan Statham", "Head of Radiology"],
			["Sue White", "Staff Liason Officer"],
			["Mac Macartney", "General Surgeon"],
			["Joanna Clore", "HR"],
			["Caroline Todd", "Surgical Registrar"],
		];

		$table = $document->getElementById("tbl1");
		$sut->bindKeyValue("tableData", $tableData);

		foreach($tableData as $rowIndex => $rowData) {
			$row = $table->rows[$rowIndex];

			foreach($rowData as $cellIndex => $cellValue) {
				self::assertSame(
					$cellValue,
					$row->cells[$cellIndex]->textContent
				);
			}
		}
	}

	public function testBindList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$listData = ["One", "Two", "Three"];
		$sut->bindList($listData);

		$liElementList = $document->querySelectorAll("ul li");

		foreach($listData as $i => $listItem) {
			self::assertSame($listItem, $liElementList[$i]->textContent);
		}
	}

	public function testBindList_nullData():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$listData = ["One", null, "Three"];
		$sut->bindList($listData);

		$liElementList = $document->querySelectorAll("ul li");

		foreach($listData as $i => $listItem) {
			if(is_null($listItem)) {
				self::assertSame("Template item!", $liElementList[$i]->textContent);
			}
			else {
				self::assertSame($listItem, $liElementList[$i]->textContent);
			}
		}
	}

	public function testBindList_emptyLeavesNoWhiteSpace():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$listData = [];
		$sut->bindList($listData);
		self::assertEquals("", $document->querySelector("ul")->innerHTML);
	}

	public function testBindData_objectWithAttribute():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$userObject = new class {
			#[Bind("username")]
			public function getUser():string {
				return "some_username";
			}

			#[Bind("email")]
			public function getEmailAddress():string {
				return "test@example.com";
			}
		};

		$sut->bindData($userObject);
		self::assertSame("some_username", $document->getElementById("dd1")->textContent);
		self::assertSame("test@example.com", $document->getElementById("dd2")->textContent);
	}

	public function testBindList_objectListWithAttributes():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$userObjectList = [
			new class {
				#[Bind("userId")]
				public function getId():int {
					return 111;
				}

				#[Bind("username")]
				public function getUser():string {
					return "firstUser";
				}

				#[Bind("orderCount")]
				public function ordersCompleted():int {
					return 3;
				}
			},

			new class {
				#[Bind("userId")]
				public function getId():int {
					return 512;
				}

				#[Bind("username")]
				public function getUser():string {
					return "userTheSecond";
				}

				#[Bind("orderCount")]
				public function ordersCompleted():int {
					return 10;
				}
			},

			new class {
				#[Bind("userId")]
				public function getId():int {
					return 660;
				}

				#[Bind("username")]
				public function getUser():string {
					return "th3rd";
				}

				#[Bind("orderCount")]
				public function ordersCompleted():int {
					return 0;
				}
			}
		];
		$sut->bindList($userObjectList);

		/** @var HTMLCollection<Element> $liCollection */
		$liCollection = $document->querySelectorAll("#orders>ul>li");

		self::assertCount(3, $liCollection);
		self::assertEquals("firstUser", $liCollection[0]->querySelector("h2 span")->textContent);
		self::assertEquals(111, $liCollection[0]->querySelector("h3 span")->textContent);
		self::assertEquals(3, $liCollection[0]->querySelector("p span")->textContent);
		self::assertEquals("user-111", $liCollection[0]->id);
		self::assertEquals("/orders/111", $liCollection[0]->querySelector("a")->href);

		self::assertEquals("userTheSecond", $liCollection[1]->querySelector("h2 span")->textContent);
		self::assertEquals(512, $liCollection[1]->querySelector("h3 span")->textContent);
		self::assertEquals(10, $liCollection[1]->querySelector("p span")->textContent);
		self::assertEquals("user-512", $liCollection[1]->id);
		self::assertEquals("/orders/512", $liCollection[1]->querySelector("a")->href);

		self::assertEquals("th3rd", $liCollection[2]->querySelector("h2 span")->textContent);
		self::assertEquals(660, $liCollection[2]->querySelector("h3 span")->textContent);
		self::assertEquals(0, $liCollection[2]->querySelector("p span")->textContent);
		self::assertEquals("user-660", $liCollection[2]->id);
		self::assertEquals("/orders/660", $liCollection[2]->querySelector("a")->href);
	}

	public function testBindData_castToArray():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$row = new class {
			private string $username = "g105b";
			private string $email = "greg.bowler@g105b.com";
			private string $category = "Unit Test";

			public function asArray():array {
				return get_object_vars($this);
			}
		};

		$sut->bindData($row);

		self::assertEquals("g105b", $document->querySelector("#dd1")->textContent);
		self::assertEquals("greg.bowler@g105b.com", $document->querySelector("#dd2")->textContent);
		self::assertEquals("Unit Test", $document->querySelector("#dd3")->textContent);
	}

	public function testBindList_castToArray():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$row1 = new class {
			private int $userId = 123;
			private string $username = "firstUser";
			private int $orderCount = 4;

			public function asArray():array {
				return get_object_vars($this);
			}
		};
		$row2 = new class {
			private int $userId = 456;
			private string $username = "secondUser";
			private int $orderCount = 16;

			public function asArray():array {
				return get_object_vars($this);
			}
		};

		$sut->bindList([$row1, $row2]);

		self::assertCount(2, $document->querySelectorAll("li"));
		self::assertEquals("firstUser", $document->querySelector("li#user-123 h2 span")->textContent);
		self::assertEquals("secondUser", $document->querySelector("li#user-456 h2 span")->textContent);
	}

	public function testBindValue_callable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindValue(fn() => "test");
		self::assertSame("test", $document->querySelector("output")->textContent);
	}

	public function testBindList_complexHTML():void {
		$from = "Slitting Mill";
		$to = "Clipstone";

		$routesData = [
			[
				"duration" => new DateInterval("PT3H58M"),
				"method" => "Train",
				"steps" => [
					"t-rtv471" => [
						"time" => "07:02",
						"location" => "Rugeley Trent Valley",
					],
					"t-ltv991" => [
						"time" => "07:49",
						"location" => "Lichfield Trent Valley",
					],
					"t-tem010" => [
						"time" => "08:03",
						"location" => "Tamworth",
					],
					"t-csy001" => [
						"time" => "09:03",
						"location" => "Chesterfield",
					],
					"t-ep090" => [
						"time" => "09:42",
						"location" => "Eastwood Park",
					],
					"t-mnn310" => [
						"time" => "10:25",
						"location" => "Mansfield",
					],
					"t-c0390" => [
						"time" => "11:00",
						"location" => "Clipstone",
					]
				]
			],
			[
				"duration" => new DateInterval("PT4H11M"),
				"method" => "Bus",
				"steps" => [
					"b-stv472" => [
						"time" => "06:20",
						"location" => "Rugeley Trent Valley",
					],
					"b-ltv050" => [
						"time" => "07:40",
						"location" => "Lichfield City Centre",
					],
					"b-ltv921" => [
						"time" => "08:00",
						"location" => "Mosley Street",
					],
					"b-sd094" => [
						"time" => "08:18",
						"location" => "Burton-on-Trent"
					],
					"b-ng001" => [
						"time" => "09:06",
						"location" => "Nottingham",
					],
					"b-mnn310" => [
						"time" => "10:01",
						"location" => "Mansfield",
					],
					"b-mnn209" => [
						"time" => "10:24",
						"location" => "Kirkby in Ashfield",
					],
					"b-c0353" => [
						"time" => "10:31",
						"location" => "Greendale Crescent",
					]
				]
			]
		];

		$document = new HTMLDocument(HTMLPageContent::HTML_TRANSPORT_ROUTES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("from", $from);
		$sut->bindKeyValue("to", $to);

		/**
		 * This callback function is used just to articulate the purpose
		 * of bindListCallback - so we can manipulate each KVP element
		 * inline with the binding - here we just format the date
		 * differently, but in reality we could be grabbing data from
		 * other sources, sorting, binding nested lists, etc.
		 */
		$callback = function(
			Element $templateElement,
			array $kvp,
			int|string $key,
		):array {
			if($duration = $kvp["duration"]) {
				/** @var DateInterval $duration */
				$kvp["duration"] = $duration->format("%H:%I");
			}

			return $kvp;
		};

		$sut->bindListCallback($routesData, $callback);
		$routeLiList = $document->querySelectorAll("ul>li");
		self::assertCount(2, $routeLiList);
		self::assertCount(count($routesData[0]["steps"]), $routeLiList[0]->querySelectorAll("ol>li"));
		self::assertCount(count($routesData[1]["steps"]), $routeLiList[1]->querySelectorAll("ol>li"));

		foreach($routesData as $i => $route) {
			self::assertEquals($route["method"], $routeLiList[$i]->querySelector("p")->textContent);
			self::assertEquals($route["duration"]->format("%H:%I"), $routeLiList[$i]->querySelector("time")->textContent);
			$stepLiList = $routeLiList[$i]->querySelectorAll("ol>li");
			$j = 0;

			foreach($route["steps"] as $id => $step) {
				$stepLi = $stepLiList[$j];
				self::assertEquals($step["time"], $stepLi->querySelector("time")->textContent);
				self::assertEquals($step["location"], $stepLi->querySelector("span")->textContent);
				self::assertEquals("/route/step/$id", $stepLi->querySelector("a")->href);
				$j++;
			}
		}
	}

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
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindListCallback(
			$salesData,
			$salesCallback
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

	public function testCleanDatasets_dataBind():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_USER_PROFILE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData([
			"username" => "codyboy123",
			"email" => "codyboy@g105b.com",
			"category" => "cat",
		]);
		$sut->cleanupDocument();

		foreach($document->querySelectorAll("dd") as $dd) {
			self::assertCount(1, $dd->attributes);
		}

		self::assertStringNotContainsString(
			"data-bind:text",
			$document->documentElement->innerHTML
		);
	}

	public function testCleanDatasets_dataTemplate():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindList(["One", "Two", "Three", "Four"]);
		$sut->cleanupDocument();

		foreach($document->querySelectorAll("ul>li") as $li) {
			self::assertCount(0, $li->attributes);
		}

		self::assertStringNotContainsString(
			"data-bind:text",
			$document->documentElement->innerHTML
		);
		self::assertStringNotContainsString(
			"data-list",
			$document->documentElement->innerHTML
		);
	}

	public function testBindListData_twoListsDifferentContexts():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS_WITH_UNNAMED_TEMPLATES);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$progLangData = ["PHP", "HTML", "bash"];
		$sut->bindList($progLangData, $document->getElementById("prog-lang-list"));
		$gameData = ["Pac Man", "Mega Man", "Tetris"];
		$sut->bindList($gameData, $document->getElementById("game-list"));

		foreach($progLangData as $i => $progLang) {
			self::assertSame($progLang, $document->querySelectorAll("#prog-lang-list li")[$i]->textContent);
		}

		foreach($gameData as $i => $game) {
			self::assertSame($game, $document->querySelectorAll("#game-list li")[$i]->textContent);
		}
	}

	public function testBindListData_twoListsDifferentContexts_withHtmlParents():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TWO_LISTS_WITH_UNNAMED_TEMPLATES_CLASS_PARENTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$progLangData = ["PHP", "HTML", "bash"];
		$sut->bindList($progLangData, $document->querySelector(".favourite-list.prog-lang"));
		$gameData = ["Pac Man", "Mega Man", "Tetris"];
		$sut->bindList($gameData, $document->querySelector(".favourite-list.game"));

		foreach($progLangData as $i => $progLang) {
			self::assertSame($progLang, $document->querySelectorAll(".prog-lang li")[$i]->textContent);
		}

		foreach($gameData as $i => $game) {
			self::assertSame($game, $document->querySelectorAll(".game li")[$i]->textContent);
		}
	}

	public function testBindValue_callableString():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$value = "explode";
		$sut->bindValue($value);
		self::assertSame($value, $document->querySelector("output")->textContent);
	}

	public function testBindList_twoListsWithSamePath():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TEMPLATES_WITH_SAME_XPATH);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$list1 = [
			["uuid" => "AAAAAAAA", "fullName" => "Test 1"],
			["uuid" => "BBBBBBBB", "fullName" => "Test 2"],
			["uuid" => "CCCCCCCC", "fullName" => "Test 3"],
			["uuid" => "DDDDDDDD", "fullName" => "Test 4"],
		];
		$list2 = [
			["uuid" => "EEEEEEEE", "fullName" => "Test 5"],
			["uuid" => "FFFFFFFF", "fullName" => "Test 6"],
		];

		$select1 = $document->querySelector("[name='pass-on-to']");
		$select2 = $document->querySelector("[name='tag-user']");

		$sut->bindList($list1, $select1);
		$sut->bindList($list2, $select2);
		$sut->cleanupDocument();

		$optionList1 = $select1->options;
		$optionList2 = $select2->options;

		self::assertCount(count($list1) + 1, $optionList1);
		self::assertCount(count($list2) + 1, $optionList2);

		foreach($list1 as $i => $item) {
			$option = $optionList1[$i + 1];
			self::assertSame($item["uuid"], $option->value);
			self::assertSame($item["fullName"], $option->textContent);
		}

		foreach($list2 as $i => $item) {
			$option = $optionList2[$i + 1];
			self::assertSame($item["uuid"], $option->value);
			self::assertSame($item["fullName"], $option->textContent);
		}
	}

	public function testBindList_readOnlyProperties():void {
		$userObject1 = new class(1, "g105b", 3) {
			public function __construct(
				public readonly int $userId,
				public readonly string $username,
				public readonly int $orderCount,
			) {}
		};
		$userObject2 = new class(2, "codyboy", 21) {
			public function __construct(
				public readonly int $userId,
				public readonly string $username,
				public readonly int $orderCount,
			) {}
		};

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindList([$userObject1, $userObject2]);

		$li1 = $document->getElementById("user-1");
		$li2 = $document->getElementById("user-2");
		self::assertNotSame($li1, $li2);
		self::assertSame($userObject1->username, $li1->querySelector("h2 span")->textContent);
		self::assertSame((string)$userObject1->userId, $li1->querySelector("h3 span")->textContent);
		self::assertSame($userObject2->username, $li2->querySelector("h2 span")->textContent);
		self::assertSame((string)$userObject2->userId, $li2->querySelector("h3 span")->textContent);
	}

	public function testBindList_readOnlyProperties_fullClass():void {
		$userObject1 = new ExampleClass(1, "g105b", 3);
		$userObject2 = new ExampleClass(2, "codyboy", 21);

		$document = new HTMLDocument(HTMLPageContent::HTML_USER_ORDER_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindList([$userObject1, $userObject2]);

		$li1 = $document->getElementById("user-1");
		$li2 = $document->getElementById("user-2");
		self::assertNotSame($li1, $li2);
		self::assertSame($userObject1->username, $li1->querySelector("h2 span")->textContent);
		self::assertSame($userObject2->username, $li2->querySelector("h2 span")->textContent);
	}

	public function test_onlyBindOnce():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_BIND_KEY_REUSED);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$shopList = [
			["id" => "123", "name" => "Alice's Animals"],
			["id" => "456", "name" => "Bob's Big Breakfast"],
			["id" => "789", "name" => "Charlie's Crayons"],
		];
		$data = [
			"id" => "111",
			"name" => "Phillipa"
		];

// Once the list is bound, the data should be bound to the whole document,
// but due to clashing keys, we want to ensure the select is only bound once.
		$sut->bindList($shopList, templateName: "shop");
		$sut->bindData($data);

		$shopOptions = $document->querySelector("[name='shopId']")->options;
		foreach($shopList as $i => $shop) {
			$option = $shopOptions[$i];
			self::assertSame($shop["id"], $option->value);
			self::assertSame($shop["name"], $option->textContent);
		}
		self::assertSame("111", $document->querySelector("p span")->textContent);
	}

	public function testBindKeyValue_onlyBindPlaceholderOnce():void {
		$placeholderBinder = self::createMock(PlaceholderBinder::class);
		$placeholderBinder->expects(self::once())
			->method("bind")
			->with("name", "Cody");

		$document = new HTMLDocument(HTMLPageContent::HTML_PLACEHOLDER);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document, $placeholderBinder));
		$sut->bindKeyValue("name", "Cody", $document->getElementById("test1"));
	}

	public function testBindKeyValue_nestedObject():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_ADDRESS_NESTED_OBJECT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$address = new Address(
			"2184 Jasper Avenue",
			"Sherwood Park",
			"Edmonton",
			"T5J 3N2",
			new Country("CA"),
		);
		$customer = new Customer(
			123,
			"Joy Buolamwini",
			$address,
		);

		$sut->bindData($customer);

		self::assertSame("123", $document->querySelectorAll("dd")[0]->textContent);
		self::assertSame("Joy Buolamwini", $document->querySelectorAll("dd")[1]->textContent);
		self::assertSame($address->street, $document->querySelectorAll("dd")[2]->textContent);
		self::assertSame($address->line2, $document->querySelectorAll("dd")[3]->textContent);
		self::assertSame($address->cityState, $document->querySelectorAll("dd")[4]->textContent);
		self::assertSame($address->postcodeZip, $document->querySelectorAll("dd")[5]->textContent);
		self::assertSame(
			$address->country->getName() . " (" . $address->country->code . ")",
			$document->querySelectorAll("dd")[6]->textContent
		);
	}

	public function testBind_multipleAttributes():void {
		$kvp = [
			"key1" => "value1",
			"key2" => "value2",
			"id" => "abc123",
			"name" => "example",
		];
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_BINDS_ON_SINGLE_ELEMENT);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindData($kvp);
		$outputElement = $document->querySelector("output");
		self::assertSame($kvp["key1"], $outputElement->dataset->get("attr1"));
		self::assertSame($kvp["key2"], $outputElement->dataset->get("attr2"));
		self::assertSame($kvp["id"], $outputElement->id);
		self::assertSame($kvp["name"], $outputElement->getAttribute("name"));
		self::assertSame($kvp["name"], $outputElement->getAttribute("title"));
	}

	/** For issue #438 (https://github.com/PhpGt/DomTemplate/issues/438) */
	public function test_removesUnboundDataElement():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_REMOVE_UNBOUND);
		$sut = new DocumentBinder($document);
		$sut->cleanupDocument();
		self::assertStringNotContainsStringIgnoringCase("error", (string)$document);
	}

	public function test_keepsElementWhenBound():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_REMOVE_UNBOUND);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("error", "Example error!");
		$sut->cleanupDocument();
		$errorDiv = $document->querySelector("form>div");
		self::assertNotNull($errorDiv);
		self::assertSame("Example error!", $errorDiv->textContent);
	}

	public function test_bindElementWithBindValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_REMOVE_UNBOUND_BIND_VALUE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("error", true);
		$sut->cleanupDocument();
		$errorDiv = $document->querySelector("form>div");
		self::assertNotNull($errorDiv);
		self::assertSame("There has been an error!", $errorDiv->textContent);
	}

	public function test_bindElementRemovesMultiple():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_ADMIN_PANEL);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("isAdmin", false);
		$sut->cleanupDocument();

		$panelDiv = $document->querySelector("div.panel");
		self::assertCount(2, $panelDiv->children);
	}

	public function test_bindElementRemovesMultiple_doesNotRemoveWithTrue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_ADMIN_PANEL);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->bindKeyValue("isAdmin", true);
		$sut->cleanupDocument();

		$panelDiv = $document->querySelector("div.panel");
		self::assertCount(4, $panelDiv->children);
	}

	public function test_bindElementIsRemovedWhenNotBound():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_REMOVE_UNBOUND_BIND_VALUE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));
		$sut->cleanupDocument();
		$errorDiv = $document->querySelector("form>div");
		self::assertNull($errorDiv);
	}

	public function test_bindData_withList_dataBindList():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATA_BIND_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$obj = new class {
			public string $name = "This name was bound";
			public array $exampleList = ["one", "two", "three"];
		};

		$sut->bindData($obj);

		self::assertSame("This name was bound", $document->querySelector("h1")->innerText);
		$liElementList = $document->querySelectorAll("ul.second-list>li");
		self::assertCount(3, $liElementList);
		foreach($liElementList as $i => $liElement) {
			if($i === 0) {
				$expectedNumber = "one";
			}
			if($i === 1) {
				$expectedNumber = "two";
			}
			if($i === 2) {
				$expectedNumber = "three";
			}
			self::assertSame($expectedNumber, $liElement->textContent);
		}
	}

	public function testBind_remove():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATA_BIND_REMOVE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$sut->bindKeyValue("name", "Example");
		$sut->bindKeyValue("isDay", true);

		self::assertSame("Hello, Example!", $document->querySelector("h1")->textContent);
		$dayOrNight = $document->getElementById("day-or-night");
		self::assertSame("Is it day or night? It's daytime!", $dayOrNight->textContent);
	}

	public function testBind_removeInverse():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATA_BIND_REMOVE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$sut->bindKeyValue("name", "Example");
		$sut->bindKeyValue("isDay", false);

		self::assertSame("Hello, Example!", $document->querySelector("h1")->textContent);
		$dayOrNight = $document->getElementById("day-or-night");
		self::assertSame("Is it day or night? It's nighttime!", $dayOrNight->textContent);
	}

	public function testBind_removeTruthy():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATA_BIND_REMOVE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$sut->bindKeyValue("name", "Example");
		$sut->bindKeyValue("isDay", "Daytime");

		self::assertSame("Hello, Example!", $document->querySelector("h1")->textContent);
		$dayOrNight = $document->getElementById("day-or-night");
		self::assertSame("Is it day or night? It's daytime!", $dayOrNight->textContent);
	}

	public function testBind_removeObject():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DATA_BIND_REMOVE);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$obj = new class {
			public string $name = "Example";

			#[Bind("isDay")]
			public function isTimeCurrentlyDaytime():bool {
				return true;
			}
		};

		$sut->bindData($obj);

		self::assertSame("Hello, Example!", $document->querySelector("h1")->textContent);
		$dayOrNight = $document->getElementById("day-or-night");
		self::assertSame("Is it day or night? It's daytime!", $dayOrNight->textContent);
	}

	/**
	 * When passing an object to bindData, the object could be both
	 * key-value-pairs and iterable (notably an IteratorAggregate).
	 */
	public function testBindData_iterableObject():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$obj = new class implements IteratorAggregate {
			public string $id = "ABC123";
			public string $name = "Example";

			public function getIterator():Traversable {
				return new ArrayIterator(["One", "Two", "Three", "Four"]);
			}
		};

		$sut->bindData($obj);
		$h1 = $document->querySelector("h1");
		$ul = $document->querySelector("ul");
		$ol = $document->querySelector("ol");

		// The object should have its properties bound to the page:
		self::assertSame("Example", $h1->textContent);
		// but it should bind itself as an iterator to the list:
		self::assertCount(4, $ul->children);
		// and the un-attributed list should not change:
		self::assertCount(1, $ol->children);
	}

	public function testBindList_arrayIterator():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SHOP_PRODUCTS);
		$sut = new DocumentBinder($document);
		$sut->setDependencies(...$this->documentBinderDependencies($document));

		$categoryNameList = ["Category 1", "Category 2"];
		$productNameList = [
			["Product 1 in cat 1", "Product 2 in cat 1"],
			["Product 3 in cat 2", "Product 4 in cat 2", "Product 5 in cat 2"],
		];

		$obj = new ProductList(
			$categoryNameList,
			$productNameList,
		);
		$sut->bindList($obj);

		$categoryList = $document->querySelector("ul.categoryList");
		self::assertCount(count($categoryNameList), $categoryList->children);

		$productCount = 0;
		foreach($categoryList->children as $categoryIndex => $categoryLi) {
			self::assertSame("Category " . ($categoryIndex + 1), $categoryLi->querySelector("h2")->innerText);

			self::assertCount(count($productNameList[$categoryIndex]), $categoryLi->querySelector("ul")->children);

			foreach($categoryLi->querySelectorAll("ul li") as $productIndex => $productLi) {
				self::assertSame("Product " . ($productCount + 1) . " in cat " . ($categoryIndex + 1), $productLi->textContent);
				$productCount++;
			}
		}
	}

	public function testBindKeyValue_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$documentElement = $document->documentElement;
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(3);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(string $key, string $value, Element $element)use($bindMatcher, $documentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals(["title", "Title 1!", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals(["title", "Title 2!", $subComponent2], [$key, $value, $element]),
					3 => self::assertEquals(["title", "Main title!", $documentElement], [$key, $value, $element]),
				};
			});

		$sut = new DocumentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);

		$sut->bindKeyValue("title", "Title 1!", "#subcomponent-1");
		$sut->bindKeyValue("title", "Title 2!", "#subcomponent-2");
		$sut->bindKeyValue("title", "Main title!");
	}

	public function testBindData_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$documentElement = $document->documentElement;
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(6);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(string $key, string $value, Element $element)use($bindMatcher, $documentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals(["title", "Title 1!", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals(["number", "1", $subComponent1], [$key, $value, $element]),
					3 => self::assertEquals(["title", "Title 2!", $subComponent2], [$key, $value, $element]),
					4 => self::assertEquals(["number", "2", $subComponent2], [$key, $value, $element]),
					5 => self::assertEquals(["title", "Main title!", $documentElement], [$key, $value, $element]),
					6 => self::assertEquals(["number", "3", $documentElement], [$key, $value, $element]),
				};
			});

		$sut = new DocumentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);

		$sut->bindData(["title" => "Title 1!", "number" => "1"], "#subcomponent-1");
		$sut->bindData(["title" => "Title 2!", "number" => "2"], "#subcomponent-2");
		$sut->bindData(["title" => "Main title!", "number" => "3"]);
	}

	public function testBindList_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$documentElement = $document->documentElement;
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$listBinder = self::createMock(ListBinder::class);
		$bindMatcher = self::exactly(3);
		$listBinder->expects($bindMatcher)
			->method("bindListData")
			->willReturnCallback(function(array $listData, Element|Document $context)use($bindMatcher, $documentElement, $subComponent1, $subComponent2):int {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals([["List", "for", "component 2"], $subComponent2], [$listData, $context]),
					2 => self::assertEquals([["List", "for", "component 1"], $subComponent1], [$listData, $context]),
					3 => self::assertEquals([["List", "for", "main component"], $documentElement], [$listData, $context]),
				};

				return 0;
			});

		$sut = new DocumentBinder($document);
		$sut->setDependencies(
			self::createStub(ElementBinder::class),
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			$listBinder,
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);

		$sut->bindList(["List", "for", "component 2"], "#subcomponent-2");
		$sut->bindList(["List", "for", "component 1"], "#subcomponent-1");
		$sut->bindList(["List", "for", "main component"]);
	}

	public function testBindValue_stringContext():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_COMPONENT_WITH_ATTRIBUTE_NESTED);
		$documentElement = $document->documentElement;
		$subComponent1 = $document->querySelector("#subcomponent-1");
		$subComponent2 = $document->querySelector("#subcomponent-2");

		$elementBinder = self::createMock(ElementBinder::class);
		$bindMatcher = self::exactly(3);
		$elementBinder->expects($bindMatcher)
			->method("bind")
			->willReturnCallback(function(?string $key, string $value, Element $element)use($bindMatcher, $documentElement, $subComponent1, $subComponent2):void {
				match($bindMatcher->numberOfInvocations()) {
					1 => self::assertEquals([null, "1", $subComponent1], [$key, $value, $element]),
					2 => self::assertEquals([null, "2", $subComponent2], [$key, $value, $element]),
					3 => self::assertEquals([null, "3", $documentElement], [$key, $value, $element]),
				};
			});

		$sut = new DocumentBinder($document);
		$sut->setDependencies(
			$elementBinder,
			self::createStub(PlaceholderBinder::class),
			self::createStub(TableBinder::class),
			self::createStub(ListBinder::class),
			self::createStub(ListElementCollection::class),
			self::createStub(BindableCache::class),
		);

		$sut->bindValue("1", "#subcomponent-1");
		$sut->bindValue("2", "#subcomponent-2");
		$sut->bindValue("3");
	}

	private function documentBinderDependencies(HTMLDocument $document, mixed...$otherObjectList):array {
		$htmlAttributeBinder = new HTMLAttributeBinder();
		$htmlAttributeCollection = new HTMLAttributeCollection();
		$elementBinder = new ElementBinder();
		$placeholderBinder = new PlaceholderBinder();
		$tableBinder = new TableBinder();
		$listBinder = new ListBinder();
		$listElementCollection = new ListElementCollection($document);
		$bindableCache = new BindableCache();

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

		$htmlAttributeBinder->setDependencies(
			$listBinder,
			$tableBinder,
		);
		$elementBinder->setDependencies(
			$htmlAttributeBinder,
			$htmlAttributeCollection,
			$placeholderBinder,
		);
		$tableBinder->setDependencies(
			$listBinder,
			$listElementCollection,
			$elementBinder,
			$htmlAttributeBinder,
			$htmlAttributeCollection,
			$placeholderBinder,
		);
		$listBinder->setDependencies(
			$elementBinder,
			$listElementCollection,
			$bindableCache,
			$tableBinder,
		);

		return [
			$elementBinder,
			$placeholderBinder,
			$tableBinder,
			$listBinder,
			$listElementCollection,
			$bindableCache,
		];
	}
}
