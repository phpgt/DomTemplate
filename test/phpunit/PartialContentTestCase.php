<?php
namespace GT\DomTemplate\Test;

use Gt\Dom\HTMLDocument;
use GT\DomTemplate\PartialContent;
use GT\DomTemplate\PartialContentFileNotFoundException;
use PHPUnit\Framework\TestCase;

class PartialContentTestCase extends TestCase {
	/**
	 * @param array<string, string> $contentFiles Associative array where
	 * the key is the partial content's name, and the value is its content.
	 */
	protected function mockPartialContent(
		string $dirName,
		array $contentFiles = []
	):PartialContent {
		$mock = self::createStub(PartialContent::class);
		$mock->method("getContent")
			->willReturnCallback(
				function(string $name, string $extension = "html", ?string $src = null) use($contentFiles) {
					$nameParts = [$name];
					if($src) {
						array_push($nameParts, $src);
					}
					$name = implode("/", $nameParts);
					$content = $contentFiles[$name] ?? null;
					if(is_null($content)) {
						throw new PartialContentFileNotFoundException();
					}

					return $content;
				}
			);
		$mock->method("getHTMLDocument")
			->willReturnCallback(
				function(string $name) use($contentFiles) {
					$content = $contentFiles[$name] ?? null;
					if(is_null($content)) {
						throw new PartialContentFileNotFoundException();
					}

					return new HTMLDocument($content);
				}
			);
		return $mock;
	}
}
