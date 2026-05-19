<?php
namespace GT\DomTemplate;

use GT\Dom\DOMTokenList;

class MutableDomTokenList extends DOMTokenList {
	public function __construct(
		callable $accessCallback,
		callable $mutateCallback,
	) {
		parent::__construct($accessCallback, $mutateCallback);
	}
}
