<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\Bind;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Binding-objects

class Customer {
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly DateTime $createdAt,
	) {}

	#[Bind("years-active")]
	public function calculateYearsSinceCreation():int {
		$now = new DateTime();
		$diff = $now->diff($this->createdAt);
		return $diff->y;
	}
}

$html = <<<HTML
<h1>Welcome back, <span data-bind:text="name">your name</span>!</h1>
<p>You have been a customer for <time data-bind:text="createdAt">0</time> years.</p>
HTML;

function example(DocumentBinder $binder):void {
// The $customer variable could come from a database in a real-world project.
	$customer = new Customer(
		id: "abc123",
		name: "Cody",
		createdAt: new DateTime("2016-07-06 15:04:00"),
	);
	$binder->bindData($customer);
}

$document = new HTMLDocument($html);
$binder = new DocumentBinder($document);

example($binder);

echo $document;
