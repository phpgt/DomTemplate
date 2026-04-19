<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindtable

$html = <<<HTML
<table>
	<thead>
		<tr>
			<th>Day</th>
			<th>Weather</th>
		</tr>
	</thead>
</table>
HTML;

function example(DocumentBinder $binder):void {
	$tableData = [
		"Day" => ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
		"Weather" => ["Rain", "Cloud", "Cloud", "Sun", "Sun", "Cloud", "Cloud"],
	];

	$binder->bindTable($tableData);
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
    <table>
        <thead>
            <tr>
                <th>Day</th>
                <th>Weather</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Mon</td>
                <td>Rain</td>
            </tr>
            <tr>
                <td>Tue</td>
                <td>Cloud</td>
            </tr>
            <tr>
                <td>Wed</td>
                <td>Cloud</td>
            </tr>
            <tr>
                <td>Thu</td>
                <td>Sun</td>
            </tr>
            <tr>
                <td>Fri</td>
                <td>Sun</td>
            </tr>
            <tr>
                <td>Sat</td>
                <td>Cloud</td>
            </tr>
            <tr>
                <td>Sun</td>
                <td>Cloud</td>
            </tr>
        </tbody>
    </table>
</body>

</html>
*/
