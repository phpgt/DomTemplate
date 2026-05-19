<?php
namespace GT\DomTemplate;

use GT\Dom\HTMLDocument;

abstract class PartialContentExpander {
	public function __construct(
		protected HTMLDocument $document,
		protected PartialContent $partialContent
	) {
	}

	/** @return array<int, mixed> */
	abstract public function expand():array;
}
