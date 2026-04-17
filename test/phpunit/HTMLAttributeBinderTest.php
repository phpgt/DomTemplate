<?php
namespace GT\DomTemplate\Test;

use DateTime;
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\HTMLAttributeBinder;
use GT\DomTemplate\Test\TestHelper\HTMLPageContent;
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

	public function testBind_classProperty_multipleClassNamesFromString():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div1");

		$sut->bind("statusClasses", "featured promoted", $div);

		self::assertTrue($div->classList->contains("panel"));
		self::assertTrue($div->classList->contains("featured"));
		self::assertTrue($div->classList->contains("promoted"));
	}

	public function testBind_classProperty_multipleClassNamesFromIterable():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div1");

		$sut->bind("statusClasses", ["featured promoted", "compact"], $div);

		self::assertTrue($div->classList->contains("featured"));
		self::assertTrue($div->classList->contains("promoted"));
		self::assertTrue($div->classList->contains("compact"));
	}

	public function testBind_classProperty_iterableIgnoresNonStringableValues():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div1");

		$sut->bind("statusClasses", ["featured", new \stdClass(), "compact"], $div);

		self::assertTrue($div->classList->contains("featured"));
		self::assertTrue($div->classList->contains("compact"));
		self::assertFalse($div->classList->contains("stdClass"));
	}

	public function testBind_modifierColon_multipleExplicitClassNames():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div2");

		$sut->bind("isSelected", true, $div);

		self::assertTrue($div->classList->contains("selected-image"));
		self::assertTrue($div->classList->contains("featured"));
	}

	public function testBind_modifierColon_usesBoundValueWhenNoExplicitClassNames():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div3");

		$sut->bind("statusClasses", "featured promoted", $div);

		self::assertTrue($div->classList->contains("featured"));
		self::assertTrue($div->classList->contains("promoted"));
	}

	public function testBind_modifierColon_removesMultipleClassNamesAtOnce():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div2");
		$div->classList->add("selected-image");
		$div->classList->add("featured");

		$sut->bind("isSelected", false, $div);

		self::assertFalse($div->classList->contains("selected-image"));
		self::assertFalse($div->classList->contains("featured"));
	}

	public function testBind_modifierColon_inverseLogic_afterTokenModifier():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INVERSE_MODIFIER_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div1");

		$sut->bind("show", false, $div);
		self::assertTrue($div->classList->contains("hidden"));

		$sut->bind("show", true, $div);
		self::assertFalse($div->classList->contains("hidden"));
	}

	public function testBind_modifierColon_inverseLogic_beforeTokenModifier():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INVERSE_MODIFIER_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div2");

		$sut->bind("show", false, $div);
		self::assertTrue($div->classList->contains("hidden"));

		$sut->bind("show", true, $div);
		self::assertFalse($div->classList->contains("hidden"));
	}

	public function testBind_modifierColon_inverseLogic_usesBindKeyWhenNoExplicitTokenNames():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INVERSE_MODIFIER_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div3");

		$sut->bind("show", false, $div);
		self::assertTrue($div->classList->contains("show"));

		$sut->bind("show", true, $div);
		self::assertFalse($div->classList->contains("show"));
	}

	public function testBind_modifierColon_multipleExpressionsCanBeBundled():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div4");

		$sut->bind("isSelected", true, $div);
		$sut->bind("isAdmin", true, $div);

		self::assertTrue($div->classList->contains("selected"));
		self::assertTrue($div->classList->contains("admin"));
	}

	public function testBind_modifierBundle_preservesRemainingExpressionsWithoutRebind():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$div = $document->getElementById("div4");

		$sut->bind("isSelected", true, $div);

		self::assertSame(":isAdmin admin", $div->getAttribute("data-bind:class"));
	}

	public function testBind_modifierQuestion_multipleExpressionsCanBeBundled():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_MULTI_CLASS_BINDING);
		$sut = new HTMLAttributeBinder();
		$button = $document->getElementById("btn3");

		$sut->bind("isBusy", false, $button);
		self::assertSame("?isLocked", $button->getAttribute("data-bind:disabled"));
		self::assertFalse($button->disabled);

		$sut->bind("isLocked", true, $button);
		self::assertTrue($button->disabled);
		self::assertFalse($button->hasAttribute("data-bind:disabled"));
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

	public function testBind_modifierQuestion_inverseLogic_afterQuestionModifier():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INVERSE_MODIFIER_BINDING);
		$sut = new HTMLAttributeBinder();
		$button = $document->getElementById("btn1");

		$sut->bind("isEnabled", false, $button);
		self::assertTrue($button->disabled);

		$sut->bind("isEnabled", true, $button);
		self::assertFalse($button->disabled);
	}

	public function testBind_modifierQuestion_inverseLogic_beforeQuestionModifier():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_INVERSE_MODIFIER_BINDING);
		$sut = new HTMLAttributeBinder();
		$button = $document->getElementById("btn2");

		$sut->bind("isEnabled", false, $button);
		self::assertTrue($button->disabled);

		$sut->bind("isEnabled", true, $button);
		self::assertFalse($button->disabled);
	}

	public function testGetModifierType_withoutTokenOrBooleanModifier_returnsFirstCharacter():void {
		$sut = new HTMLAttributeBinder();
		$method = new \ReflectionMethod($sut, "getModifierType");

		self::assertSame("!", $method->invoke($sut, "!show"));
	}

	public function testGetModifierBody_withoutLeadingTokenOrBooleanModifier_trimsPrefixCharacter():void {
		$sut = new HTMLAttributeBinder();
		$method = new \ReflectionMethod($sut, "getModifierBody");

		self::assertSame("show", $method->invoke($sut, "!show"));
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

	public function testBind_modifierQuestion_withConditionalBooleanMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_CONDITIONAL_BOOLEAN_CHECKBOX);
		$sut = new HTMLAttributeBinder();
		$input = $document->getElementById("flag");
		$input->checked = false;

		$sut->bind("enabled", true, $input);

		self::assertTrue($input->checked);
	}

	public function testBind_modifierQuestion_withConditionalIterableDoesNotMatch():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_CONDITIONAL_BOOLEAN_CHECKBOX);
		$sut = new HTMLAttributeBinder();
		$input = $document->getElementById("flag");

		$sut->bind("enabled", ["1"], $input);

		self::assertFalse($input->checked);
	}

	public function testBind_modifierQuestion_withConditionalMatch_attributeModifier():void {
		$document = new HTMLDocument(
			HTMLPageContent::HTML_RADIO_GROUP_CONDITIONAL_CHECKED_ATTRIBUTE_MODIFIER
		);
		$sut = new HTMLAttributeBinder();

		foreach($document->querySelectorAll("input[type=radio]") as $radio) {
			$sut->expandAttributes($radio);
			$sut->bind("size", "m", $radio);
		}

		self::assertFalse($document->getElementById("size-s")->checked);
		self::assertTrue($document->getElementById("size-m")->checked);
		self::assertFalse($document->getElementById("size-l")->checked);
	}

	public function testBind_modifierQuestion_withConditionalNoMatch_attributeModifier():void {
		$document = new HTMLDocument(
			HTMLPageContent::HTML_RADIO_GROUP_CONDITIONAL_CHECKED_ATTRIBUTE_MODIFIER
		);
		$sut = new HTMLAttributeBinder();
		$document->getElementById("size-s")->checked = true;

		foreach($document->querySelectorAll("input[type=radio]") as $radio) {
			$sut->expandAttributes($radio);
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

	public function testBind_multipleAttributes_withDebug():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_ATTRIBUTE_BIND_DEBUG);
		$outputElement = $document->querySelector("output");
		$sut = new HTMLAttributeBinder();
		$sut->setDebugSource("app/ProfileController.php:42");
		$sut->bind("key1", "value1", $outputElement);
		$sut->bind("key2", "value2", $outputElement);

		self::assertSame(
			"data-attr1=app/ProfileController.php:42,data-attr2=app/ProfileController.php:42",
			$outputElement->getAttribute("data-bind-debug")
		);
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
		$document = new HTMLDocument(HTMLPageContent::HTML_INPUT_VALUE_AT_DEFAULT_NAME);
		$input = $document->querySelector("input");
		$sut = new HTMLAttributeBinder();
		$sut->expandAttributes($input);
		self::assertSame("email", $input->getAttribute("data-bind:value"));
	}

	public function testExpandAttributes_listUsesTagNameWhenNoHyphen():void {
		$document = new HTMLDocument(HTMLPageContent::HTML_LIST_BIND_EMPTY_NAME);
		$list = $document->querySelector("ul");
		$sut = new HTMLAttributeBinder();
		$sut->expandAttributes($list);
		self::assertSame("ul", $list->getAttribute("data-bind:list"));
	}
}
