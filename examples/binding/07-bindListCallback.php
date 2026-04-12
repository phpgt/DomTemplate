<?php
use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindlistcallback

$html = <<<HTML
<!doctype html>
<h1>Shopping list</h1>

<ul>
	<li data-template data-bind:text>Item</li>
</ul>
HTML;

function example(DocumentBinder $binder):void {
	$listData = [
		"Eggs",
		"Potatoes",
		"Butter",
		"Plain flour",
	];
	$binder->bindListCallback($listData, function(Element $element, $listItem, $listKey) {
		$element->classList->add("item-$listKey");
		return "$listItem (item $listKey)";
	});
}

// END OF EXAMPLE CODE.

$document = new HTMLDocument($html);
$binder = new DocumentBinder($document);
example($binder);
$binder->cleanupDocument();
echo $document;

/* Output:
<!DOCTYPE html>
<html>
<body>
    <h1>Shopping list</h1>

    <ul id="template-parent-62fe44ffc92e0">
        <li class="item-0">Eggs (item 0)</li>
        <li class="item-1">Potatoes (item 1)</li>
        <li class="item-2">Butter (item 2)</li>
        <li class="item-3">Plain flour (item 3)</li>
    </ul>
</body>

</html>
*/
