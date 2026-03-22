<?php
namespace Gt\DomTemplate\Test;

use DateTime;
use Gt\Dom\HTMLDocument;
use Gt\DomTemplate\HTMLAttributeBinder;
use Gt\DomTemplate\Test\TestHelper\HTMLPageContent;
use PHPUnit\Framework\TestCase;

class HTMLAttributeBinderTest extends TestCase {
	public function testBind_wholeDocument():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LANGUAGE);
		$sut = new HTMLAttributeBinder();
		$sut->bind("language", "en_GB", $document);
		self::assertSame("en_GB", $document->documentElement->getAttribute("lang"));
	}

	public function testBind_selectValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SELECT_OPTIONS_WITH_VALUE);
		$select = $document->querySelector("select[name='drink']");
		$sut = new HTMLAttributeBinder();
		$valueToSelect = "tea";
		$sut->bind("drink", $valueToSelect, $select);

		foreach($document->querySelectorAll("select option") as $option) {
			$value = $option->getAttribute("value");
			if($value === $valueToSelect) {
				self::assertTrue($option->hasAttribute("selected"));
			}
			else {
				self::assertFalse($option->hasAttribute("selected"));
			}
		}
	}

	public function testBind_selectValue_noOptions():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SELECT_OPTIONS_WITHOUT_VALUE);
		$select = $document->querySelector("select[name='drink']");
		$sut = new HTMLAttributeBinder();
		$valueToSelect = "Tea";
		$sut->bind("drink", $valueToSelect, $select);

		foreach($document->querySelectorAll("select option") as $option) {
			if($option->value === $valueToSelect) {
				self::assertTrue($option->hasAttribute("selected"));
			}
			else {
				self::assertFalse($option->hasAttribute("selected"));
			}
		}
	}

	public function testBind_selectValue_optionDoesNotExist():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_SELECT_OPTIONS_WITHOUT_VALUE);
		$select = $document->querySelector("select[name='drink']");
		$sut = new HTMLAttributeBinder();
		$valueToSelect = "Grape Juice";
		$select->options[2]->selected = true;
		$sut->bind("drink", $valueToSelect, $select);

		foreach($document->querySelectorAll("select option") as $i => $option) {
			self::assertFalse($option->hasAttribute("selected"), $i);
		}
	}

	public function testBind_modifierColonNamedProperty_null():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO);
		$sut = new HTMLAttributeBinder();
		$li = $document->querySelector("ul li");
		$sut->bind("completedAt", null, $li);
		self::assertFalse($li->classList->contains("completed"));
	}

	public function testBind_modifierColonNamedProperty():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_TODO);
		$sut = new HTMLAttributeBinder();
		$li = $document->querySelector("ul li");
		$sut->bind("completedAt", new DateTime(), $li);
		self::assertTrue($li->classList->contains("completed"));
	}

	public function testBind_modifierColon():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new HTMLAttributeBinder();
		$img = $document->getElementById("img1");
		$sut->bind("size", "size-large", $img);
		self::assertTrue($img->classList->contains("size-large"));
	}

	public function testBind_modifierQuestion():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new HTMLAttributeBinder();
		$btn1 = $document->getElementById("btn1");
		$btn2 = $document->getElementById("btn2");
		$sut->bind("isBtn1Disabled", true, $btn1);
		$sut->bind("isBtn1Disabled", true, $btn2);
		$sut->bind("isBtn2Disabled", true, $btn1);
		$sut->bind("isBtn2Disabled", true, $btn2);

		self::assertTrue($btn1->disabled);
		self::assertFalse($btn2->disabled);
	}

	public function testBind_modifierQuestion_withNullValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new HTMLAttributeBinder();
		$img = $document->getElementById("img3");
		$sut->bind("alternativeText", null, $img);
		self::assertSame("Not bound", $img->alt);
	}

	public function testBind_modifierQuestion_withValue():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_DIFFERENT_BIND_PROPERTIES);
		$sut = new HTMLAttributeBinder();
		$img = $document->getElementById("img3");
		$testMessage = "This is a test message";
		$sut->bind("alternativeText", $testMessage, $img);
		self::assertSame($testMessage, $img->alt);
	}

	public function testBind_modifierQuestion_withConditionalMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_RADIO_GROUP_CONDITIONAL_CHECKED);
		$sut = new HTMLAttributeBinder();

		foreach($document->querySelectorAll("input[type=radio]") as $radio) {
			$sut->bind("size", "m", $radio);
		}

		self::assertFalse($document->getElementById("size-s")->checked);
		self::assertTrue($document->getElementById("size-m")->checked);
		self::assertFalse($document->getElementById("size-l")->checked);
	}

	public function testBind_modifierQuestion_withConditionalNoMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_RADIO_GROUP_CONDITIONAL_CHECKED);
		$sut = new HTMLAttributeBinder();
		$document->getElementById("size-s")->checked = true;

		foreach($document->querySelectorAll("input[type=radio]") as $radio) {
			$sut->bind("size", "xl", $radio);
		}

		self::assertFalse($document->getElementById("size-s")->checked);
		self::assertFalse($document->getElementById("size-m")->checked);
		self::assertFalse($document->getElementById("size-l")->checked);
	}

	public function testBind_dateTimeInterface():void {
		$dateTime = new DateTime("1988-04-05 17:23:00");

		$document = new HTMLDocument(HTMLPageContent::HTML_SINGLE_ELEMENT);
		$outputElement = $document->querySelector("output");
		$sut = new HTMLAttributeBinder();
		$sut->bind(null, $dateTime, $outputElement);
		self::assertSame("Tue, 05 Apr 1988 17:23:00 +0000", $outputElement->textContent);
	}

	public function testBind_multipleAttributes():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTIPLE_BINDS_ON_SINGLE_ELEMENT);
		$outputElement = $document->querySelector("output");
		$sut = new HTMLAttributeBinder();
		$sut->bind("key1", "value1", $outputElement);
		$sut->bind("key2", "value2", $outputElement);
		$sut->bind("id", "example-id", $outputElement);
		$sut->bind("name", "example-name", $outputElement);

		self::assertSame("value1", $outputElement->getAttribute("data-attr1"));
		self::assertSame("value2", $outputElement->getAttribute("data-attr2"));
		self::assertSame("example-id", $outputElement->getAttribute("id"));
		self::assertSame("example-name", $outputElement->getAttribute("name"));

		self::assertSame("existing-value", $outputElement->dataset->get("existingAttr"));
		self::assertSame("value1", $outputElement->dataset->get("attr1"));
		self::assertSame("value2", $outputElement->dataset->get("attr2"));
	}

	public function testExpandAttributes_atCharacter():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_BASIC_FORM_WITH_AT_BINDER);
		$sut = new HTMLAttributeBinder();
		$fromInput = $document->querySelector("input[name=from]");
		$toInput = $document->querySelector("input[name=to]");
		$sut->bind("from", "London", $fromInput);
		$sut->bind("to", "Derby", $toInput);

		self::assertSame("London", $fromInput->value);
		self::assertSame("Derby", $toInput->value);
	}

	public function testExpandAttributes_atCharacterDefaultsToName():void {
		$document = new HTMLDocument(
			"<!doctype html><html><body><input name='email' data-bind:value='@' /></body></html>"
		);
		$input = $document->querySelector("input");
		$sut = new HTMLAttributeBinder();
		$sut->expandAttributes($input);
		self::assertSame("email", $input->getAttribute("data-bind:value"));
	}

	public function testExpandAttributes_listUsesTagNameWhenNoHyphen():void {
		$document = new HTMLDocument(
			"<!doctype html><html><body><ul data-bind:list=''></ul></body></html>"
		);
		$list = $document->querySelector("ul");
		$sut = new HTMLAttributeBinder();
		$sut->expandAttributes($list);
		self::assertSame("ul", $list->getAttribute("data-bind:list"));
	}
}
