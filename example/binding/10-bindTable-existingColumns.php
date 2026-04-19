<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding-tables#binding-to-a-table-with-existing-headings

$html = <<<HTML
<h1>The planets</h1>
<table data-bind:table>
	<thead>
	<tr>
		<th>Name</th>
		<th>Distance from Sun</th>
	</tr>
	</thead>
</table>
HTML;

function example(DocumentBinder $binder):void {
	$data = [
		"Name" => ["Mercury", "Venus", "Earth", "Mars", "Jupiter", "Saturn", "Uranus", "Neptune"],
		"Symbol" => ["☿", "♀", "🜨", "♂", "♃", "♄", "⛢", "♆"],
		"Diameter" => [4879, 12104, 12756, 6792, 142984, 120536, 51118, 49528],
		"Distance from Sun" => [59.7, 108.2, 149.6, 227.9, 778.6, 1433.5, 2872.5, 4495.1],
	];
	$binder->bindTable($data);
}

// END OF EXAMPLE CODE.

$document = new HTMLDocument($html);
$binder = new DocumentBinder($document);
example($binder);
$binder->cleanupDocument();
echo $document;

/* Output:
<!doctype html>
<html>

<head></head>

<body>
    <h1>The planets</h1>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Distance from Sun</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Mercury</td>
                <td>59.7</td>
            </tr>
            <tr>
                <td>Venus</td>
                <td>108.2</td>
            </tr>
            <tr>
                <td>Earth</td>
                <td>149.6</td>
            </tr>
            <tr>
                <td>Mars</td>
                <td>227.9</td>
            </tr>
            <tr>
                <td>Jupiter</td>
                <td>778.6</td>
            </tr>
            <tr>
                <td>Saturn</td>
                <td>1433.5</td>
            </tr>
            <tr>
                <td>Uranus</td>
                <td>2872.5</td>
            </tr>
            <tr>
                <td>Neptune</td>
                <td>4495.1</td>
            </tr>
        </tbody>
    </table>
</body>

</html>
