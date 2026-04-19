<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindlist

$html = <<<HTML
<!doctype html>
<h1>Shopping list</h1>

<ul>
	<li data-template data-bind:text>Item name</li>
</ul>
HTML;

function example(DocumentBinder $binder):void {
	$listData = [
		"Eggs",
		"Potatoes",
		"Butter",
		"Plain flour",
	];
	$binder->bindList($listData);
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

    <ul id="template-parent-62fe45171c32e">

        <li>Eggs</li>
        <li>Potatoes</li>
        <li>Butter</li>
        <li>Plain flour</li>
    </ul>
</body>
</html>
*/
