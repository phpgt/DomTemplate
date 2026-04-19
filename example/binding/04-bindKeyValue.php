<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindkeyvalue

$html = <<<HTML
<!doctype html>
<h1>Hello, <span data-bind:text="name">you</span>!</h1>
HTML;

function example(DocumentBinder $binder):void {
	$binder->bindKeyValue("name", "Cody");
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
	<h1>Hello, <span>Cody</span>!</h1>
</body>
</html>
*/
