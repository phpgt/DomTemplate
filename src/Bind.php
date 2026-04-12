<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
namespace GT\DomTemplate;

use Attribute;

/** @codeCoverageIgnore */
#[Attribute]
class Bind {
	public function __construct(
		public string $key
	) {
	}
}
