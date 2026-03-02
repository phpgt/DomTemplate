<?php
namespace Gt\DomTemplate;

use Gt\Dom\Attr;
use Gt\Dom\Document;
use Gt\Dom\DOMTokenList;
use Gt\Dom\DOMTokenListFactory;
use Gt\Dom\Element;

class HTMLAttributeBinder {
	private ListBinder $listBinder;
	private TableBinder $tableBinder;

	public function setDependencies(ListBinder $listBinder, TableBinder $tableBinder):void {
		$this->listBinder = $listBinder;
		$this->tableBinder = $tableBinder;
	}

	public function bind(
		?string $key,
		mixed $value,
		Document|Element $element
	):void {
		if(is_null($value)) {
			return;
		}
		if(!is_scalar($value) && !is_iterable($value)) {
			$value = new BindValue($value);
		}

		if($element instanceof Document) {
			$element = $element->documentElement;
		}

		$attributesToRemove = [];

		/**
		 * @var string $attrName
		 * @var Attr $attr
		 */
		foreach($element->attributes as $attrName => $attr) {
			$attrValue = $attr->value;

			if($attrName === "data-element") {
				if($attr->value === $key && $value) {
					$element->setAttribute("data-bound", "");
				}
			}

			if(!str_starts_with($attrName, "data-bind")) {
				continue;
			}

			if(!str_contains($attrName, ":")) {
				$tag = $this->getHTMLTag($element);
				throw new InvalidBindPropertyException("$tag Element has a data-bind attribute with missing bind property - did you mean `data-bind:text`?");
			}

			$modifier = null;
			if(is_null($key)) {
// If there is no key specified, only bind the elements that don't have a
// specified key in their bind attribute's value.
				if(strlen($attrValue) > 0) {
					continue;
				}
			}
			else {
// If a key is specified, and the bind attribute's value doesn't match,
// skip this attribute.
				$trimmedAttrValue = ltrim($attrValue, ":!?");
				$trimmedAttrValue = strtok($trimmedAttrValue, " ");
				if($key !== $trimmedAttrValue && $trimmedAttrValue !== "@") {
					continue;
				}
				if($attrValue !== $trimmedAttrValue) {
					$modifier = $attrValue;
				}
			}

			$bindProperty = substr(
				$attrName,
				strpos($attrName, ":") + 1
			);
			$this->setBindProperty(
				$element,
				$bindProperty,
				$value,
				$modifier
			);
			$element->setAttribute("data-bound", "");

			if(!$attr->ownerElement->hasAttribute("data-rebind")) {
				array_push($attributesToRemove, $attrName);
			}
		}

		foreach($attributesToRemove as $attrName) {
			$element->removeAttribute($attrName);
		}
	}

	public function expandAttributes(Element $element):void {
		/**
		 * @var string $attrName
		 * @var Attr $attr
		 */
		foreach($element->attributes as $attrName => $attr) {
			$attrValue = $attr->value;

			if(!str_starts_with($attrName, "data-bind:")) {
				continue;
			}

			if($attrName === "data-bind:list") {
				if($attrValue === "") {
					$attrValue = $element->tagName;
					if(str_contains($attrValue, "-")) {
						$newAttrValue = "";
						foreach(explode("-", $attrValue) as $i => $part) {
							if($i > 0) {
								$part = ucfirst($part);
							}
							$newAttrValue .= $part;
						}
						$attrValue = $newAttrValue;
					}
					$element->setAttribute($attrName, $attrValue);
				}
			}

			if(strlen($attrValue) === 0) {
				continue;
			}

			if($attrValue[0] === "@") {
				if($attrValue === "@") {
					$otherAttrName = "name";
				}
				else {
					$otherAttrName = substr($attrValue, 1);
				}
				$element->setAttribute(
					$attrName,
					$element->getAttribute($otherAttrName)
				);
			}
		}
	}

	private function getHTMLTag(Element $element):string {
		return "<" . strtolower($element->tagName) . ">";
	}

	/**
	 * This function actually mutates the Element. The type of mutation is
	 * defined by the value of $bindProperty. The default behaviour is to
	 * set the an attribute on $element where the attribute key is equal to
	 * $bindProperty and the attribute value is equal to $bindValue, however
	 * there are a few values of $bindProperty that affect this behaviour:
	 *
	 * 1) "text" will set the textContent of $element. Why "text" and
	 * not "textContent"? Because HTML attributes can't have uppercase
	 * characters, and this removes ambiguity.
	 * 2) "html" will set the innerHTML of $element. Same as above.
	 * 3) "class" will add the provided value as a class (rather than
	 * setting the class attribute and losing existing classes). The colon
	 * can be added to the bindKey to toggle, as explained in point 6 below.
	 * 4) "table" will create the appropriate columns and rows within the
	 * first <table> element within the element being bound.
	 * 5) "attr" will bind the attribute with the same name as the bindKey.
	 * 6) By default, the attribute matching $bindProperty will be set,
	 * according to these rules:
	 *    + If the bindKey is an alphanumeric string, the attribute will be
	 * 	set to the value of the matching bindValue.
	 *    + If the bindKey starts with a colon character ":", the attribute
	 * 	will be treated as a Token List, and the matching token will be
	 * 	added/removed from the attribute value depending on whether the
	 * 	$bindValue is true/false.
	 *    + If the bindKey starts with a question mark "?", the attribute
	 * 	will be toggled, depending on whether the $bindValue is
	 * 	true/false.
	 *    + If the bindKey starts with a question mark and exclamation mark,
	 * 	"?!", the attribute will be toggled as above, but with inverse
	 * 	logic. Useful for toggling "disabled" attribute from data that
	 * 	represents "enabled" state.
	 *
	 * With colon/question mark bind values, the value of the attribute will
	 * match the value of $bindValue - if a different attribute value is
	 * required, this can be specified after a space. For example:
	 * data-bind:class=":isSelected selected-item" will add/remove the
	 * "selected-item" class depending on the $bindValue's boolean value.
	 * @noinspection SpellCheckingInspection
	 */
	private function setBindProperty(
		Element $element,
		string $bindProperty,
		mixed $bindValue,
		?string $modifier = null
	):void {
		switch(strtolower($bindProperty)) {
		case "text":
		case "innertext":
		case "inner-text":
		case "textcontent":
		case "text-content":
			$element->textContent = $bindValue;
			break;

		case "html":
		case "innerhtml":
		case "inner-html":
			$element->innerHTML = $bindValue;
			break;

		case "class":
			if($modifier) {
				$this->handleModifier(
					$element,
					"class",
					$modifier,
					$bindValue
				);
			}
			else {
				$element->classList->add($bindValue);
			}
			break;

		case "table":
			$this->tableBinder->bindTableData(
				$bindValue,
				$element,
				$element->getAttribute("data-bind:$bindProperty")
			);
			break;

		case "value":
			$element->value = $bindValue;
			break;

		case "list":
			$this->listBinder->bindListData($bindValue, $element);
			break;

		case "remove":
			$remove = $bindValue;
			if(str_contains($modifier, "!")) {
				$remove = !$remove;
			}

			if($remove) {
				$element->remove();
			}
			break;

		default:
			if($modifier) {
				$this->handleModifier(
					$element,
					$bindProperty,
					$modifier,
					$bindValue
				);
			}
			else {
				$element->setAttribute($bindProperty, $bindValue);
			}

			break;
		}
	}

	private function handleModifier(
		Element $element,
		string $attribute,
		string $modifier,
		mixed $bindValue
	):void {
		$modifierChar = $modifier[0];
		$modifierValue = substr($modifier, 1);
		if(false !== $spacePos = strpos($modifierValue, " ")) {
			$modifierValue = substr($modifierValue, $spacePos + 1);
		}

		switch($modifierChar) {
		case ":":
			$tokenList = $this->getTokenList($element, $attribute);
			if($bindValue) {
				$tokenList->add($modifierValue);
			}
			else {
				$tokenList->remove($modifierValue);
			}
			break;

		case "?":
			if($modifierValue[0] === "!") {
				$bindValue = !$bindValue;
			}

			if($bindValue) {
				$element->setAttribute($attribute, $bindValue);
			}
			else {
				if(!is_null($bindValue)) {
					$element->removeAttribute($attribute);
				}
			}
		}
	}

	private function getTokenList(
		Element $node,
		string $attribute
	):DOMTokenList {
		return DOMTokenListFactory::create(
			fn() => explode(" ", $node->getAttribute($attribute) ?? ""),
			fn(string...$tokens) => $node->setAttribute($attribute, implode(" ", $tokens)),
		);
	}
}
