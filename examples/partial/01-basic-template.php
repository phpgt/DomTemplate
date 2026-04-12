<?php
use Gt\Dom\HTMLDocument;
use GT\DomTemplate\DocumentBinder;
use GT\DomTemplate\PartialContent;
use GT\DomTemplate\PartialExpander;

require(__DIR__ . "/../../vendor/autoload.php");
// EXAMPLE CODE: https://github.com/PhpGt/DomTemplate/wiki/Partials#pre-binding-using-template-variables

$partialContent = <<<HTML
<!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	<title>My website :: {{site-heading ?? Home}}</title>
</head>
<body>
	<h1 data-bind:text="site-heading">My website!</h1>
	<main data-partial>
	</main>

	<footer>
		<p>Thanks for visiting</p>
	</footer>
</body>
</html>
HTML;

$pageAbout = <<<HTML
<!--
extends=main-template

[vars]
site-heading=About me
-->

<h2>About me</h2>
<p>This is my about me page on my amazing website!</p>
HTML;

$baseDirectory = sys_get_temp_dir() . "/phpgt-domtemplate-example";
$partialDirectory = "$baseDirectory/_partial";
mkdir($partialDirectory, 0775, true);
file_put_contents("$partialDirectory/main-template.html", $partialContent);

$document = new HTMLDocument($pageAbout);
$partial = new PartialContent($partialDirectory);
$expander = new PartialExpander($document, $partial);
$binder = new DocumentBinder($document);
$binder->cleanupDocument();
$expander->expand(binder: $binder);

echo $document;

// Remove the temporary files:
foreach(new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($baseDirectory, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
) as $file) {
	if($file->isDir()) rmdir($file->getRealPath());
	else unlink($file->getRealPath());
}
