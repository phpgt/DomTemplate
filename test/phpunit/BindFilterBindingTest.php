<?php
namespace GT\DomTemplate\Test;

use GT\Dom\HTMLDocument;
use GT\DomTemplate\BindableCache;
use GT\DomTemplate\BindFilter;
use GT\DomTemplate\BindGetter;
use GT\DomTemplate\BindFilterException;
use GT\DomTemplate\BindFilterNotFoundException;
use GT\DomTemplate\BindFilterNotRegisteredException;
use GT\DomTemplate\InvalidBindFilterExpressionException;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use stdClass;
use GT\DomTemplate\ComponentBinder;
use GT\DomTemplate\DocumentBinder;
use GT\DomTemplate\ElementBinder;
use GT\DomTemplate\HTMLAttributeBinder;
use GT\DomTemplate\HTMLAttributeCollection;
use GT\DomTemplate\ListBinder;
use GT\DomTemplate\ListElementCollection;
use GT\DomTemplate\PlaceholderBinder;
use GT\DomTemplate\TableBinder;
use PHPUnit\Framework\TestCase;

class BindFilterBindingTest extends TestCase {
	public function testFiltersAreChainedPerExpressionWithoutChangingOtherBindings():void {
		$document = new HTMLDocument('<p id="formatted" data-bind:text="amount|numericOutput|currency"></p>'
			. '<p id="raw" data-bind:text="amount"></p>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterNumericOutput(float $value):string {
				return number_format($value, 2);
			}
			public function filterCurrency(string $value):string {
				return "£" . $value;
			}
		});

		$binder->bindKeyValue("amount", 12345);

		self::assertSame("£12,345.00", $document->querySelector("#formatted")->textContent);
		self::assertSame("12345", $document->querySelector("#raw")->textContent);
	}

	public function testMultipleProvidersChainWithLaterOverridesAndEarlierFallbacks():void {
		$document = new HTMLDocument('<p data-bind:text="amount|number|currency"></p>'
			. '<b data-bind:text="amount|number"></b>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterNumber(int $value):string {
				return number_format($value, 2);
			}
			public function filterCurrency(string $value):string {
				return "£" . $value;
			}
		});
		$application = new class extends BindFilter {
			public function filterCurrency(string $value):string {
				return ($this->getDependency(stdClass::class)?->symbol ?? "€") . $value;
			}
		};
		$application->setDependency((object)["symbol" => "$"]);
		$binder->registerBindFilter($application);
		$binder->bindKeyValue("amount", 12345);

		self::assertSame("$12,345.00", $document->querySelector("p")->textContent);
		self::assertSame("12,345.00", $document->querySelector("b")->textContent);
	}

	public function testRegisteringTheSameClassReplacesItAndUpdatesPrecedence():void {
		$document = new HTMLDocument('<p data-bind:text="name|label" data-rebind></p>');
		$binder = $this->createBinder($document);
		$first = new class extends BindFilter {
			public string $prefix = "first ";
			public function filterLabel(string $value):string {
				return $this->prefix . $value;
			}
		};
		$binder->registerBindFilter($first);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterLabel(string $value):string {
				return "second " . $value;
			}
		});
		$binder->bindKeyValue("name", "hello");
		self::assertSame("second hello", $document->querySelector("p")->textContent);
		$replacement = clone $first;
		$replacement->prefix = "replacement ";
		$binder->registerBindFilter($replacement);
		$binder->bindKeyValue("name", "hello");
		self::assertSame("replacement hello", $document->querySelector("p")->textContent);
	}

	public function testRawObjectsAreFilteredBeforeStringConversion():void {
		$document = new HTMLDocument('<p data-bind:text="date|date"></p>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterDate(DateTimeInterface $value):string {
				return $value->format("Y-m-d");
			}
		});
		$binder->bindKeyValue("date", new DateTimeImmutable("2026-11-06"));
		self::assertSame("2026-11-06", $document->querySelector("p")->textContent);
	}

	public function testBindGettersAndListObjectsPreserveInputTypes():void {
		$value = new class {
			#[BindGetter]
			public function getDate():DateTimeImmutable {
				return new DateTimeImmutable("2026-11-06");
			}
			#[BindGetter]
			public function getAmount():int {
				return 12345;
			}
		};
		$document = new HTMLDocument('<p data-bind:text="date|date"></p><ul><li data-list>'
			. '<span data-bind:text="date|date"></span><b data-bind:text="amount|type"></b></li></ul>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterDate(DateTimeInterface $value):string {
				return $value->format("Y-m-d");
			}
			public function filterType(mixed $value):string {
				return get_debug_type($value);
			}
		});
		$binder->bindData($value, "p");
		$binder->bindList([$value]);
		self::assertSame("2026-11-06", $document->querySelector("p")->textContent);
		self::assertSame("2026-11-06", $document->querySelector("li span")->textContent);
		self::assertSame("int", $document->querySelector("li b")->textContent);
	}

	public function testNullCanBeFilteredAndNullResultsRemainUnbound():void {
		$document = new HTMLDocument('<p id="fallback" data-bind:text="value|fallback">Original</p>'
			. '<p id="ignored" data-bind:text="value|ignore">Original</p>'
			. '<p id="raw" data-bind:text="value">Original</p>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterFallback(mixed $value):string {
				return $value ?? "Default";
			}
			public function filterIgnore(mixed $value):mixed {
				return null;
			}
		});
		$binder->bindKeyValue("value", null);
		self::assertSame("Default", $document->querySelector("#fallback")->textContent);
		self::assertFalse($document->querySelector("#fallback")->hasAttribute("data-bind:text"));
		self::assertSame("Original", $document->querySelector("#ignored")->textContent);
		self::assertTrue($document->querySelector("#ignored")->hasAttribute("data-bind:text"));
		self::assertSame("Original", $document->querySelector("#raw")->textContent);
	}

	public function testFiltersWorkWithModifiersEqualityReferencesAndRebinding():void {
		$document = new HTMLDocument('<div data-bind:class=":amount|positive active; :hidden|positive hidden" data-rebind></div>'
			. '<button name="status" data-bind:disabled="?@name|lower=blocked" data-rebind></button>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterPositive(int $value):bool {
				return $value > 0;
			}
			public function filterLower(string $value):string {
				return strtolower($value);
			}
		});
		$div = $document->querySelector("div");
		$binder->bindKeyValue("amount", 5);
		self::assertSame("active", $div->className);
		$binder->bindKeyValue("hidden", 1);
		self::assertTrue($div->classList->contains("active"));
		self::assertTrue($div->classList->contains("hidden"));
		$binder->bindKeyValue("amount", -1);
		self::assertSame("hidden", $div->className);
		$binder->bindKeyValue("status", "BLOCKED");
		self::assertTrue($document->querySelector("button")->hasAttribute("disabled"));
		$binder->bindKeyValue("status", "READY");
		self::assertFalse($document->querySelector("button")->hasAttribute("disabled"));
	}

	public function testRemainingExpressionsKeepTheirFilters():void {
		$document = new HTMLDocument('<div data-bind:class=":one|positive first; :two|positive second"></div>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterPositive(int $value):bool {
				return $value > 0;
			}
		});
		$binder->bindKeyValue("one", 1);
		self::assertSame(":two|positive second", $document->querySelector("div")->getAttribute("data-bind:class"));
		$binder->bindKeyValue("two", 2);
		self::assertSame("first second", $document->querySelector("div")->className);
		self::assertFalse($document->querySelector("div")->hasAttribute("data-bind:class"));
	}

	public function testOneProviderAndItsDependenciesAreSharedWithComponentsAndLists():void {
		$document = new HTMLDocument('<p id="page" data-bind:text="name|label"></p>'
			. '<section><p data-bind:text="name|label"></p><ul><li data-list data-bind:text="name|label"></li></ul></section>');
		$page = $this->createBinder($document);
		$component = new ComponentBinder($document);
		$component->setDependencies(...$this->dependencies);
		$component->setComponentBinderDependencies($document->querySelector("section"));
		$filter = new class extends BindFilter {
			public int $calls = 0;
			public function filterLabel(string $value):string {
				$this->calls++;
				return ($this->getDependency(stdClass::class)?->prefix ?? "") . $value;
			}
		};
		$page->registerBindFilter($filter);
		$filter->setDependency((object)["prefix" => "Hello "]);
		$page->bindKeyValue("name", "Page", "#page");
		$component->bindKeyValue("name", "Component", "p");
		$component->bindList([["name" => "One"], ["name" => "Two"]]);
		self::assertSame("Hello Page", $document->querySelector("#page")->textContent);
		self::assertSame("Hello Component", $document->querySelector("section p")->textContent);
		self::assertSame("Hello One", $document->querySelectorAll("li")[0]->textContent);
		self::assertSame("Hello Two", $document->querySelectorAll("li")[1]->textContent);
		self::assertSame(4, $filter->calls);
	}

	public function testAnonymousBindingsAndPlainPlaceholders():void {
		$document = new HTMLDocument('<p data-bind:text="|upper"></p><span>{{}}</span>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterUpper(string $value):string {
				return strtoupper($value);
			}
		});
		$binder->bindValue("hello");
		self::assertSame("HELLO", $document->querySelector("p")->textContent);
		self::assertSame("hello", $document->querySelector("span")->textContent);
	}

	public function testUnmatchedExpressionsDoNotRequireRegistration():void {
		$document = new HTMLDocument('<p data-bind:text="other|unknown">Original</p><span data-bind:text="name"></span>');
		$binder = $this->createBinder($document);
		$binder->bindKeyValue("name", "hello");
		self::assertSame("Original", $document->querySelector("p")->textContent);
		self::assertSame("hello", $document->querySelector("span")->textContent);
	}

	#[DataProvider("invalidExpressions")]
	public function testInvalidFiltersExposeTheNameAndExpression(string $expression, string $exceptionClass, bool $registered):void {
		$document = new HTMLDocument('<p data-bind:text="' . $expression . '">Original</p>');
		$binder = $this->createBinder($document);
		if($registered) {
			$binder->registerBindFilter(new class extends BindFilter {});
		}
		try {
			$binder->bindKeyValue("name", "hello");
			self::fail("Expected a filter exception");
		}
		catch(BindFilterException $exception) {
			self::assertInstanceOf($exceptionClass, $exception);
			self::assertSame($expression, $exception->expression);
			self::assertStringContainsString($expression, $exception->getMessage());
			self::assertSame("Original", $document->querySelector("p")->textContent);
			self::assertSame($expression, $document->querySelector("p")->getAttribute("data-bind:text"));
		}
	}

	public static function invalidExpressions():iterable {
		yield ["name|upper", BindFilterNotRegisteredException::class, false];
		yield ["name|upper", BindFilterNotFoundException::class, true];
		yield ["name|", InvalidBindFilterExpressionException::class, true];
		yield ["name||upper", InvalidBindFilterExpressionException::class, true];
		yield ["name |upper", InvalidBindFilterExpressionException::class, true];
		yield ["name|upper()", InvalidBindFilterExpressionException::class, true];
		yield ["name|setDependency", BindFilterNotFoundException::class, true];
	}

	public function testFilteredListsUseTheSharedProviderForRows():void {
		$document = new HTMLDocument('<div data-bind:list="items|reverse"><ul>'
			. '<li data-list data-bind:text="name|upper"></li></ul></div>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterReverse(array $value):array {
				return array_reverse($value);
			}
			public function filterUpper(string $value):string {
				return strtoupper($value);
			}
		});
		$binder->bindKeyValue("items", [["name" => "one"], ["name" => "two"]]);
		self::assertSame("TWO", $document->querySelectorAll("li")[0]->textContent);
		self::assertSame("ONE", $document->querySelectorAll("li")[1]->textContent);
	}

	public function testFilteringDoesNotStringifyTheInputObject():void {
		$value = new class implements \Stringable {
			public string $label = "raw";
			public function __toString():string {
				throw new RuntimeException("Do not stringify before filtering");
			}
		};
		$document = new HTMLDocument('<p data-bind:text="value|label"></p>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterLabel(object $value):string {
				return $value->label;
			}
		});
		$binder->bindData((object)["value" => $value]);
		self::assertSame("raw", $document->querySelector("p")->textContent);
	}

	public function testOrdinaryObjectValuesAreConvertedOnlyOnce():void {
		$value = new class implements \Stringable {
			public int $calls = 0;
			public function __toString():string {
				$this->calls++;
				return "hello";
			}
		};
		$document = new HTMLDocument('<p data-bind:text="value"></p><b data-bind:text="value"></b><span>{{value}}</span>');
		$binder = $this->createBinder($document);
		$binder->bindData((object)["value" => $value]);
		self::assertSame("hello", $document->querySelector("p")->textContent);
		self::assertSame("hello", $document->querySelector("b")->textContent);
		self::assertSame("hello", $document->querySelector("span")->textContent);
		self::assertSame(1, $value->calls);
	}

	public function testListCallbacksKeepTheirOrdinaryObjectValues():void {
		$document = new HTMLDocument('<ul><li data-list><b data-bind:text="amount|type"></b>'
			. '<span>{{amount}}</span></li></ul>');
		$binder = $this->createBinder($document);
		$binder->registerBindFilter(new class extends BindFilter {
			public function filterType(mixed $value):string {
				return get_debug_type($value);
			}
		});
		$binder->bindListCallback([(object)["amount" => 25]], function($element, $value):void {
			self::assertSame(["amount" => "25"], $value);
		});
		self::assertSame("int", $document->querySelector("b")->textContent);
		self::assertSame("25", $document->querySelector("span")->textContent);
	}

	public function testUnregisteredDocumentDoesNotInheritAnotherProvider():void {
		$firstDocument = new HTMLDocument('<p data-bind:text="name|upper"></p>');
		$first = $this->createBinder($firstDocument);
		$first->registerBindFilter(new class extends BindFilter {
			public function filterUpper(string $value):string {
				return strtoupper($value);
			}
		});
		$first->bindKeyValue("name", "hello");
		self::assertSame("HELLO", $firstDocument->querySelector("p")->textContent);
		$second = $this->createBinder(new HTMLDocument('<p data-bind:text="name|upper"></p>'));
		$this->expectException(BindFilterNotRegisteredException::class);
		$second->bindKeyValue("name", "hello");
	}

	public function testFilterFailuresRetainTheOriginalException():void {
		$document = new HTMLDocument('<p data-bind:text="name|broken">Original</p>');
		$binder = $this->createBinder($document);
		$failure = new RuntimeException("Filter failed");
		$binder->registerBindFilter(new class($failure) extends BindFilter {
			public function __construct(private RuntimeException $failure) {}
			public function filterBroken(mixed $value):mixed {
				throw $this->failure;
			}
		});
		try {
			$binder->bindKeyValue("name", "hello");
			self::fail("Expected the original exception");
		}
		catch(RuntimeException $exception) {
			self::assertSame($failure, $exception);
			self::assertSame("Original", $document->querySelector("p")->textContent);
		}
	}

	private array $dependencies;

	private function createBinder(HTMLDocument $document, ?ElementBinder $elementBinder = null):DocumentBinder {
		$elementBinder ??= new ElementBinder();
		$attributes = new HTMLAttributeBinder();
		$collection = new HTMLAttributeCollection();
		$placeholders = new PlaceholderBinder();
		$table = new TableBinder();
		$list = new ListBinder();
		$templates = new ListElementCollection($document);
		$cache = new BindableCache();
		$attributes->setDependencies($list, $table);
		$elementBinder->setDependencies($attributes, $collection, $placeholders);
		$table->setDependencies($list, $templates, $elementBinder, $attributes, $collection, $placeholders);
		$list->setDependencies($elementBinder, $templates, $cache, $table);
		$binder = new DocumentBinder($document);
		$this->dependencies = [$elementBinder, $placeholders, $table, $list, $templates, $cache];
		$binder->setDependencies(...$this->dependencies);
		return $binder;
	}
}
