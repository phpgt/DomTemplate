<?php
namespace GT\DomTemplate;

use GT\Dom\HTMLDocument;

class PartialContent {
	public function __construct(
		private readonly string $dirPath
	) {
		if(!is_dir($this->dirPath)) {
			throw new PartialContentDirectoryNotFoundException(
				"The partial content path does not exist: $this->dirPath"
			);
		}
	}

	public function getContent(
		string $name,
		string $extension = "html",
		?string $src = null,
	):string {
		if($src) {
			$name = "$name/$src";
		}
		$filePath = $this->dirPath . "/" . $name . ".$extension";
		if(!is_file($filePath)) {
			throw new PartialContentFileNotFoundException(
				"The partial content file does not exist: $filePath"
			);
		}

		return file_get_contents($filePath);
	}

	public function getHTMLDocument(string $name):HTMLDocument {
		return new HTMLDocument($this->getContent($name));
	}
}
