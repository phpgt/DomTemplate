<?php
namespace GT\DomTemplate\Test\TestHelper\Model\IteratorAggregate\Music;

class MusicFactory {
	/**
	 * @param array<string, array<string, array<string>>> $input
	 * @return array<Artist>
	 */
	public function buildArtistArray(array $input):array {
		$artistArray = [];

		foreach($input as $artistName => $inputAlbums) {
			$albumList = [];
			foreach($inputAlbums as $albumName => $inputTracks) {
				$trackList = [];
				foreach($inputTracks as $trackName) {
					$trackList[$trackName] = new Track($trackName, rand(20, 300));
				}

				$albumList[$albumName] = new Album($albumName, $trackList);
			}

			$artistArray[$artistName] = new Artist($artistName, $albumList);
		}

		return $artistArray;
	}
}
