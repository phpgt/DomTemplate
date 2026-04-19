<?php
namespace GT\DomTemplate;

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

		$bindValue = $this->standardiseBindValue($value);
		$attributesToRemove = [];
		$attributeUpdates = [];
		foreach($element->attributes as $attributeName => $attribute) {
			/** @var Attr $attribute */
			$this->markBoundElement($element, $attribute, $key, $bindValue);
			if(!$this->shouldHandleAttribute($attributeName)) {
				continue;
			}

			$bindProperty = $this->getBindProperty($element, $attributeName);
			[$modifier, $remainingExpressions] = $this->resolveModifier($key, $attribute->value);
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
				if($remainingExpressions === []) {
					$attributesToRemove[] = $attributeName;
				}
				else {
					$attributeUpdates[$attributeName] = implode("; ", $remainingExpressions);
				}
			}
		}

		foreach($attributeUpdates as $attributeName => $attributeValue) {
			$element->setAttribute($attributeName, $attributeValue);
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

	private function standardiseBindValue(mixed $value):mixed {
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

	/** @return array{0:string|false|null, 1:array<int, string>} */
	private function resolveModifier(
		?string $key,
		string $attributeValue,
	):array {
		if(is_null($key)) {
			return [
				$attributeValue === ""
					? null
					: false,
				[],
			];
		}

		$matchingExpression = null;
		$remainingExpressions = [];
		foreach($this->splitBindExpressions($attributeValue) as $expression) {
			$trimmedExpression = ltrim($expression, ":!?");
			$trimmedExpression = strtok($trimmedExpression, " ");
			$bindKey = $this->extractBindKey($trimmedExpression);
			if(is_null($matchingExpression) && ($key === $bindKey || $bindKey === "@")) {
				$matchingExpression = $expression;
				continue;
			}

			$remainingExpressions[] = $expression;
		}

		if(is_null($matchingExpression)) {
			return [false, []];
		}

		return [
			$matchingExpression !== ltrim($matchingExpression, ":!?")
				? $matchingExpression
				: null,
			$remainingExpressions,
		];
	}

	/** @return array<int, string> */
	private function splitBindExpressions(string $attributeValue):array {
		return array_values(
			array_filter(
				array_map("trim", explode(";", $attributeValue)),
				fn(string $expression):bool => $expression !== "",
			),
		);
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

		foreach($this->prepareTokenListValues($bindValue) as $className) {
			$element->classList->add($className);
		}
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
		$modifierChar = $this->getModifierType($modifier);
		$modifierValue = $this->getModifierBody($modifier);
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
			$tokenNames = $this->resolveTokenNames($modifier, $bindValue);
			if($this->isInverseModifier($modifier)) {
				$bindValue = !$bindValue;
			}
			if($bindValue) {
				$tokenList->add(...$tokenNames);
			}
			else {
				$tokenList->remove(...$tokenNames);
			}
			break;

		case "?":
			if($this->isInverseModifier($modifier)) {
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
		$modifierValue = $this->getModifierBody($modifier);
		return strtok($modifierValue, " ") ?: "";
	}

	/** @return array<int, string> */
	private function resolveTokenNames(string $modifier, mixed $bindValue):array {
		$tokenNames = $this->extractModifierTokens($modifier);
		if($tokenNames) {
			return $tokenNames;
		}

		if(is_bool($bindValue)) {
			$bindExpression = $this->extractModifierExpression($modifier);
			return [$this->extractBindKey($bindExpression)];
		}

		return $this->prepareTokenListValues($bindValue);
	}

	/** @return array<int, string> */
	private function extractModifierTokens(string $modifier):array {
		$modifierValue = $this->getModifierBody($modifier);
		$spacePos = strpos($modifierValue, " ");
		if($spacePos === false) {
			return [];
		}

		return $this->prepareTokenListValues(substr($modifierValue, $spacePos + 1));
	}

	private function getModifierType(string $modifier):string {
		foreach(str_split($modifier) as $char) {
			if($char === ":" || $char === "?") {
				return $char;
			}
		}

		return $modifier[0];
	}

	private function isInverseModifier(string $modifier):bool {
		return str_contains($modifier, "!");
	}

	private function getModifierBody(string $modifier):string {
		$modifierType = $this->getModifierType($modifier);
		$modifierValue = ltrim($modifier, "!");
		if(str_starts_with($modifierValue, $modifierType)) {
			return ltrim(substr($modifierValue, 1), "!");
		}

		return ltrim(substr($modifier, 1), "!");
	}

	private function extractCondition(string $bindExpression):?string {
		$parts = explode("=", $bindExpression, 2);
		return $parts[1] ?? null;
	}

	/** @return array<int, string> */
	private function prepareTokenListValues(mixed $bindValue):array {
		if(is_iterable($bindValue)) {
			$tokenList = [];
			foreach($bindValue as $tokenValue) {
				array_push($tokenList, ...$this->prepareTokenListValues($tokenValue));
			}

			return array_values(array_unique($tokenList));
		}

		if(!is_scalar($bindValue) && !$bindValue instanceof \Stringable) {
			return [];
		}

		$tokenList = preg_split('/\s+/', trim((string)$bindValue)) ?: [];
		return array_values(array_filter($tokenList, fn(string $token):bool => $token !== ""));
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
