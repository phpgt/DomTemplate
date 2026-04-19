<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;

require __DIR__ . "/../../vendor/autoload.php";

$html = <<<HTML
<!doctype html>
<h1>Music library</h1>

<ul>
	<li>
		<h2 data-bind:text>Artist name</h2>
		
		<ul>
			<li>
				<h3 data-bind:text>Album name</h3>
				
				<ol>
					<li data-bind:text>Track name</li>
				</ol>
			</li>
		</ul>
	</li>
</ul>
HTML;

$musicData = [
	"A Band From Your Childhood" => [
		"This Album is Good" => [
			"The Best Song You‘ve Ever Heard",
			"Another Cracking Tune",
			"Top Notch Music Here",
			"The Best Is Left ‘Til Last",
		],
		"Adequate Collection" => [
			"Meh",
			"‘sok",
			"Sounds Like Every Other Song",
		],
	],
	"Bongo and The Bronks" => [
		"Salad" => [
			"Tomatoes",
			"Song About Cucumber",
			"Onions Make Me Cry (but I love them)",
		],
		"Meat" => [
			"Steak",
			"Is Chicken Really a Meat?",
			"Don‘t Look in the Sausage Factory",
			"Stop Horsing Around",
		],
		"SnaxX" => [
			"Crispy Potatoes With Salt",
			"Pretzel Song",
			"Pork Scratchings Are Skin",
			"The Peanut Is Not Actually A Nut",
		],
	],
	"Crayons" => [
		"Pastel Colours" => [
			"Egg Shell",
			"Cotton",
			"Frost",
			"Periwinkle",
		],
		"Different Shades of Blue" => [
			"Cobalt",
			"Slate",
			"Indigo",
			"Teal",
		],
	]
];

function example(DocumentBinder $binder, array $musicData):void {
	$binder->bindList($musicData);
}

$document = new HTMLDocument($html);
$binder = new DocumentBinder($document);

example($binder, $musicData);

echo $document;

/*
Output:

<!doctype html>
<h1>Music library</h1>

<ul>
	<li>
		<h2>A Band From Your Childhood</h2>

		<ul>
			<li>
				<h3>This Album is Good</h3>

				<ol>
					<li>The Best Song You‘ve Ever Heard</li>
					<li>Another Cracking Tune</li>
					<li>Top Notch Music Here</li>
					<li>The Best Is Left ‘Til Last</li>
				</ol>
			</li>
			<li>
				<h3>Adequate Collection</h3>

				<ol>
					<li>Meh</li>
					<li>‘sok</li>
					<li>Sounds Like Every Other Song</li>
				</ol>
			</li>
		</ul>
	</li>
	<li>
		<h2>Bongo and The Bronks</h2>

		<ul>
			<li>
				<h3>Salad</h3>

				<ol>
					<li>Tomatoes</li>
					<li>Song About Cucumber</li>
					<li>Onions Make Me Cry (but I love them)</li>
				</ol>
			</li>
			<li>
				<h3>Meat</h3>

				<ol>
					<li>Steak</li>
					<li>Is Chicken Really a Meat?</li>
					<li>Don‘t Look in the Sausage Factory</li>
					<li>Stop Horsing Around</li>
				</ol>
			</li>
			<li>
				<h3>SnaxX</h3>

				<ol>
					<li>Crispy Potatoes With Salt</li>
					<li>Pretzel Song</li>
					<li>Pork Scratchings Are Skin</li>
					<li>The Peanut Is Not Actually A Nut</li>
				</ol>
			</li>
		</ul>
	</li>
	<li>
		<h2>Crayons</h2>

		<ul>
			<li>
				<h3>Pastel Colours</h3>

				<ol>
					<li>Egg Shell</li>
					<li>Cotton</li>
					<li>Frost</li>
					<li>Periwinkle</li>
				</ol>
			</li>
			<li>
				<h3>Different Shades of Blue</h3>

				<ol>
					<li>Cobalt</li>
					<li>Slate</li>
					<li>Indigo</li>
					<li>Teal</li>
				</ol>
			</li>
		</ul>
	</li>
</ul>
*/
