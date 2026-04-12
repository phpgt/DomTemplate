<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Music;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class Album implements IteratorAggregate {
	/** @param array<Track> $trackList */
	public function __construct(
		public readonly string $name,
		public readonly array $trackList,
	) {}

	public function getIterator():Traversable {
		return new ArrayIterator($this->trackList);
	}
}
