<?php
namespace Gt\DomTemplate;

use Gt\Dom\Attr;
use Gt\Dom\Document;
use Gt\Dom\DOMTokenList;
use Gt\Dom\Element;

class HTMLAttributeBinder {
	private ListBinder $listBinder;
	private TableBinder $tableBinder;
	private ?string $debugSource = null;

	public function setDependencies(ListBinder $listBinder, TableBinder $tableBinder):void {
		$this->listBinder = $listBinder;
		$this->tableBinder = $tableBinder;
	}

	public function setDebugSource(?string $debugSource):void {
		$this->debugSource = $debugSource;
	}

	public function bind(
		?string $key,
		mixed $value,
		Document|Element $element
	):void {
		if(is_null($value)) {
			return;
		}

		if($element instanceof Document) {
			$element = $element->documentElement;
		}

		$bindValue = $this->normalizeBindValue($value);
		$attributesToRemove = [];
		foreach($element->attributes as $attributeName => $attribute) {
			/** @var Attr $attribute */
			$this->markBoundElement($element, $attribute, $key, $bindValue);
			if(!$this->shouldHandleAttribute($attributeName)) {
				continue;
			}

			$bindProperty = $this->getBindProperty($element, $attributeName);
			$modifier = $this->resolveModifier($key, $attribute->value);
			if($modifier === false) {
				continue;
			}

			$this->setBindProperty(
				$element,
				$bindProperty,
				$bindValue,
				$modifier,
			);
			$this->appendDebugInfo($element, $bindProperty);
			$element->setAttribute("data-bound", "");
			if(!$attribute->ownerElement->hasAttribute("data-rebind")) {
				$attributesToRemove[] = $attributeName;
			}
		}

		foreach($attributesToRemove as $attributeName) {
			$element->removeAttribute($attributeName);
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
					$attrValue = $this->defaultListBindingName($element);
					$element->setAttribute($attrName, $attrValue);
				}
			}

			if(strlen($attrValue) === 0) {
				continue;
			}

			$element->setAttribute(
				$attrName,
				$this->expandAttributeReferences($element, $attrValue)
			);
		}
	}

	private function normalizeBindValue(mixed $value):mixed {
		if(is_scalar($value) || is_iterable($value)) {
			return $value;
		}

		return new BindValue($value);
	}

	private function markBoundElement(
		Element $element,
		Attr $attribute,
		?string $key,
		mixed $value,
	):void {
		if($attribute->name === "data-element"
		&& $attribute->value === $key
		&& $value) {
			$element->setAttribute("data-bound", "");
		}
	}

	private function shouldHandleAttribute(string $attributeName):bool {
		return str_starts_with($attributeName, "data-bind:")
			|| $attributeName === "data-bind";
	}

	private function appendDebugInfo(Element $element, string $bindProperty):void {
		if(!$this->debugSource || !$this->isDebugEnabled($element)) {
			return;
		}

		$entry = $bindProperty . "=" . $this->debugSource;
		$debugAttribute = trim($element->getAttribute("data-bind-debug") ?? "");
		if($debugAttribute === "") {
			$element->setAttribute("data-bind-debug", $entry);
			return;
		}

		$element->setAttribute("data-bind-debug", $debugAttribute . "," . $entry);
	}

	private function isDebugEnabled(Element $element):bool {
		return !is_null($element->closest("[data-bind-debug]"));
	}

	private function getBindProperty(
		Element $element,
		string $attributeName,
	):string {
		if(!str_contains($attributeName, ":")) {
			$tag = $this->getHtmlTag($element);
			throw new InvalidBindPropertyException(
				"$tag Element has a data-bind attribute with missing "
				. "bind property - did you mean `data-bind:text`?"
			);
		}

		return substr($attributeName, strpos($attributeName, ":") + 1);
	}

	private function resolveModifier(
		?string $key,
		string $attributeValue,
	):string|false|null {
		if(is_null($key)) {
			return $attributeValue === ""
				? null
				: false;
		}

		$trimmedAttrValue = ltrim($attributeValue, ":!?");
		$trimmedAttrValue = strtok($trimmedAttrValue, " ");
		$bindKey = $this->extractBindKey($trimmedAttrValue);
		if($key !== $bindKey && $bindKey !== "@") {
			return false;
		}

		return $attributeValue !== $trimmedAttrValue
			? $attributeValue
			: null;
	}

	private function defaultListBindingName(Element $element):string {
		$tagName = $element->tagName;
		if(!str_contains($tagName, "-")) {
			return $tagName;
		}

		$listName = "";
		foreach(explode("-", $tagName) as $index => $part) {
			$listName .= $index === 0
				? $part
				: ucfirst($part);
		}

		return $listName;
	}

	private function getHtmlTag(Element $element):string {
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
		$normalizedProperty = strtolower($bindProperty);
		if($this->isTextBinding($normalizedProperty)) {
			$element->textContent = $bindValue;
			return;
		}

		if($this->isHtmlBinding($normalizedProperty)) {
			$element->innerHTML = $bindValue;
			return;
		}

		switch($normalizedProperty) {
		case "class":
			$this->bindClassProperty($element, $bindValue, $modifier);
			return;

		case "table":
			$this->tableBinder->bindTableData(
				$bindValue,
				$element,
				$element->getAttribute("data-bind:$bindProperty"),
			);
			return;

		case "value":
			$element->value = $bindValue;
			return;

		case "list":
			$this->listBinder->bindListData($bindValue, $element);
			return;

		case "remove":
			$this->bindRemoveProperty($element, $bindValue, $modifier);
			return;
		}

		$this->bindDefaultProperty($element, $bindProperty, $bindValue, $modifier);
	}

	private function isTextBinding(string $bindProperty):bool {
		return in_array($bindProperty, [
			"text",
			"innertext",
			"inner-text",
			"textcontent",
			"text-content",
		], true);
	}

	private function isHtmlBinding(string $bindProperty):bool {
		return in_array($bindProperty, [
			"html",
			"innerhtml",
			"inner-html",
		], true);
	}

	private function bindClassProperty(
		Element $element,
		mixed $bindValue,
		?string $modifier,
	):void {
		if($modifier) {
			$this->handleModifier($element, "class", $modifier, $bindValue);
			return;
		}

		$element->classList->add($bindValue);
	}

	private function bindRemoveProperty(
		Element $element,
		mixed $bindValue,
		?string $modifier,
	):void {
		$remove = $bindValue;
		if($modifier && str_contains($modifier, "!")) {
			$remove = !$remove;
		}

		if($remove) {
			$element->remove();
		}
	}

	private function bindDefaultProperty(
		Element $element,
		string $bindProperty,
		mixed $bindValue,
		?string $modifier,
	):void {
		if($modifier) {
			$this->handleModifier($element, $bindProperty, $modifier, $bindValue);
			return;
		}

		$element->setAttribute($bindProperty, $bindValue);
	}

	private function handleModifier(
		Element $element,
		string $attribute,
		string $modifier,
		mixed $bindValue
	):void {
		$modifierChar = $modifier[0];
		$modifierValue = substr($modifier, 1);
		$condition = null;
		if(false !== $spacePos = strpos($modifierValue, " ")) {
			$modifierValue = substr($modifierValue, $spacePos + 1);
		}

		if($expression = $this->extractModifierExpression($modifier)) {
			$condition = $this->extractCondition($expression);
		}

		if(!is_null($condition)) {
			$bindValue = $this->valueMatchesCondition($bindValue, $condition);
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
		return new MutableDomTokenList(
			fn() => explode(" ", $node->getAttribute($attribute) ?? ""),
			fn(string...$tokens) => $node->setAttribute($attribute, implode(" ", $tokens)),
		);
	}

	private function expandAttributeReferences(Element $element, string $attributeValue):string {
		return preg_replace_callback(
			'/@([a-zA-Z0-9:-]+)?/',
			function(array $matches)use($element):string {
				$otherAttrName = $matches[1] ?? null;
				if(!$otherAttrName) {
					$otherAttrName = "name";
				}
				return $element->getAttribute($otherAttrName);
			},
			$attributeValue,
		);
	}

	private function extractBindKey(string $bindExpression):string {
		[$bindKey] = explode("=", $bindExpression, 2);
		return $bindKey;
	}

	private function extractModifierExpression(string $modifier):string {
		$modifierValue = substr($modifier, 1);
		$modifierValue = ltrim($modifierValue, "!");
		return strtok($modifierValue, " ") ?: "";
	}

	private function extractCondition(string $bindExpression):?string {
		$parts = explode("=", $bindExpression, 2);
		return $parts[1] ?? null;
	}

	private function valueMatchesCondition(mixed $bindValue, string $condition):bool {
		if(is_bool($bindValue)) {
			$bindValue = (int)$bindValue;
		}

		if(is_scalar($bindValue) || $bindValue instanceof \Stringable) {
			return (string)$bindValue === $condition;
		}

		return false;
	}
}
