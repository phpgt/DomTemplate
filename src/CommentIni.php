<?php
namespace GT\DomTemplate;

use Gt\Dom\Comment;
use Gt\Dom\Document;
use Gt\Dom\Element;
use Gt\Dom\NodeFilter;
use Throwable;

class CommentIni {
	/** @var array<string, array<string, string>|string>|null */
	private ?array $iniData;

	public function __construct(
		Document|Element $context
	) {
		if($context instanceof Document) {
			$context = $context->documentElement;
		}
		/** @var Element $context */

		$walker = $context->ownerDocument->createTreeWalker(
			$context,
			NodeFilter::SHOW_COMMENT
		);

		[$commentNodeToRemove, $ini] = $this->findIniComment($walker);
		$commentNodeToRemove?->parentNode->removeChild($commentNodeToRemove);
		$this->iniData = $ini ?: null;
	}

	public function get(string $variable):?string {
		$parts = explode(".", $variable);

		$var = $this->iniData;
		foreach($parts as $part) {
			$var = $var[$part] ?? null;
		}

		return $var;
	}

	/** @return array<string, string> */
	public function getVars():array {
		return $this->iniData["vars"] ?? [];
	}

	public function containsIniData():bool {
		return !empty($this->iniData);
	}

	/**
	 * @param iterable<int, Element|Comment> $walker
	 * @return array{0:?Comment,1:?array<string, array<string, string>|string>}
	 */
	private function findIniComment(iterable $walker):array {
		$commentNodeToRemove = null;
		$ini = null;

		/** @var Element|Comment $commentNode */
		foreach($walker as $commentNode) {
			if(!$commentNode instanceof Comment) {
				continue;
			}

			$ini = $this->parseCommentIni(trim($commentNode->data));
			if(!$ini) {
				break;
			}

			$this->assertCommentIsLeadingNode($commentNode);
			$commentNodeToRemove = $commentNode;
		}

		return [$commentNodeToRemove, $ini];
	}

	/** @return ?array<string, array<string, string>|string> */
	private function parseCommentIni(string $data):?array {
		set_error_handler(
			static fn() => true
		);

		try {
			$parsed = parse_ini_string($data, true);
		}
		catch(Throwable) {
			$parsed = false;
		}
		finally {
			restore_error_handler();
		}

		return is_array($parsed)
			? $parsed
			: null;
	}

	private function assertCommentIsLeadingNode(Comment $commentNode):void {
		$context = $commentNode;
		while($context = $context->previousSibling) {
			if(trim($context->textContent ?? "") !== "") {
				throw new CommentIniInvalidDocumentLocationException(
					"A Comment INI must only appear as the first node of the HTML."
				);
			}
		}
	}
}
