<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding#bindkeyvalue

$html = <<<HTML
<!doctype html>
<h1>User profile</h1>

<div>
	<h2 data-bind:text="username">Username</h2>
	<p>Full name: <span data-bind:text="fullName">Full Name</p>
	<p>Bio: <span data-bind:text="bio">Bio goes here</span></p>
</div>
HTML;

function example(DocumentBinder $binder):void {
	// In a real application, $data might be supplied from the database
	// and could contain model objects rather than associative arrays.
	$data = [
		"username" => "PhpNut",
		"fullName" => "Larry E. Masters",
		"bio" => "Christian - Dad - 4x Grandad - Co-Founder of @CakePHP - Developer - Open Source Advocate",
	];

	$binder->bindData($data);
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
    <h1>User profile</h1>

    <div>
        <h2>PhpNut</h2>
        <p>Full name: <span>Larry E. Masters</span></p>
        <p>Bio: <span>Christian - Dad - 4x Grandad - Co-Founder of @CakePHP - Developer - Open Source Advocate</span></p>
    </div>
</body>

</html>
*/
