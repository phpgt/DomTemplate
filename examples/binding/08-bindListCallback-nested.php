<?php
use Gt\Dom\Element;
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindlistcallback

$html = <<<HTML
<!doctype html>
<h1>Menu</h1>

<ul>
	<li data-template>
		<h2 data-bind:text="title">Menu item title</h2>
		<p>Ingredients:</p>
		<ul>
			<li data-template data-bind:text>Ingredient goes here</li>
		</ul>	
	</li>
</ul>
HTML;

function example(DocumentBinder $binder):void {
	$listData = [
		[
			"title" => "Roast king oyster mushroom",
			"ingredients" => ["hazelnut", "summer truffle", "black garlic"],
		],
		[
			"title" => "Cornish skate wing",
			"ingredients" => ["borlotti cassoulet", "lilliput caper", "baby gem", "orange oil"],
		],
		[
			"title" => "Aged Derbyshire beef",
			"ingredients" => ["turnip", "pickled mustard", "bone marrow mash", "rainbow chard"],
		],
	];
	$binder->bindListCallback($listData, function(Element $element, $listItem, $listKey) use($binder) {
		$binder->bindKeyValue("title", $listItem["title"]);
		$binder->bindList($listItem["ingredients"], $element);
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
    <h1>Menu</h1>

    <ul id="template-parent-62fe445401332">
        <li>
            <h2>Roast king oyster mushroom</h2>
            <p>Ingredients:</p>
            <ul id="template-parent-62fe44540144e">
                <li>hazelnut</li>
                <li>summer truffle</li>
                <li>black garlic</li>
            </ul>
        </li>
        <li>
            <h2>Cornish skate wing</h2>
            <p>Ingredients:</p>
            <ul id="template-parent-62fe44540144e">
                <li>borlotti cassoulet</li>
                <li>lilliput caper</li>
                <li>baby gem</li>
                <li>orange oil</li>
            </ul>
        </li>
        <li>
            <h2>Aged Derbyshire beef</h2>
            <p>Ingredients:</p>
            <ul id="template-parent-62fe44540144e">
                <li>turnip</li>
                <li>pickled mustard</li>
                <li>bone marrow mash</li>
                <li>rainbow chard</li>
            </ul>
        </li>
    </ul>
</body>
</html>
 */
