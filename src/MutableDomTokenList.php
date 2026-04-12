<?php
namespace GT\DomTemplate;

use Gt\Dom\DOMTokenList;

class MutableDomTokenList extends DOMTokenList {
	public function __construct(
		callable $accessCallback,
		callable $mutateCallback,
	) {
		parent::__construct($accessCallback, $mutateCallback);
	}
}
