<?php
namespace GT\DomTemplate;

use Gt\Dom\Attr;
use Gt\Dom\Document;
use Gt\Dom\Element;
use Gt\Dom\Node;
use Gt\Dom\Text;

class PlaceholderBinder {
	public function bind(
		?string $key,
		mixed $value,
		Node|Element|Document $context
	):void {
		if($context instanceof Document) {
			$context = $context->documentElement;
		}

// The XPath query is split into two, separated by the pipe character (|).
// The first query: //text()[contains(.,'{{')] finds any Text nodes that contain
// two opening curly braces.
// The second query: //@*[contains(.,'{{')] finds any Attr nodes that contain
// two opening curly braces.
// NOTE: An Attr node's value is represented by a Text node.
		$xpathResult = $context->ownerDocument->evaluate(
			".//text()[contains(.,'{{')] | .//@*[contains(.,'{{')]",
			$context
		);

		foreach($xpathResult as $attributeOrText) {
			/** @var Text|Attr $text */
			$text = $attributeOrText;
			if($text instanceof Attr) {
				/** @var Text $text */
				$text = $text->lastChild;
			}

			$nodeValue = $text->nodeValue;

			$keyRegex = is_null($key)
				? ""
				: preg_quote($key, "/");
			$regex = "/{{ *(?P<KEY>$keyRegex) *(?:\?\? *(?P<DEFAULT>.*?))? *}}/";
			preg_match_all($regex, $nodeValue, $matches);

			foreach($matches[0] as $i => $subjectToReplace) {
				if($key != $matches["KEY"][$i]) {
					continue;
				}

				$valueToUse = $matches["DEFAULT"][$i];
				if(!is_null($value) && $value !== "") {
					$valueToUse = $value;
				}

				$nodeValue = str_replace(
					$subjectToReplace,
					$valueToUse,
					$nodeValue
				);
			}
			$text->nodeValue = $nodeValue;
		}
	}
}
