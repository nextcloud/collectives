<?php

namespace Unit\Migration;

use OCA\Collectives\Migration\EmojiSplitter;
use PHPUnit\Framework\TestCase;

class EmojiSplitterTest extends TestCase {
	public function getEmojiNames(): array {
		return [
			['name', ['name', null]],
			['name 9', ['name 9', '']],
			['😜', ['😜', '']],
			[' 😉', [' 😉', '']],
			['a 😉', ['a 😉', '']],
			['name 😜', ['name', '😜']],
			['name 😜 suffix', ['name 😜 suffix', '']],
			['name 😜 name 😜', ['name 😜 name', '😜']],
			['道 🚵‍♂️', ['道', '🚵‍♂️']],
			['道 👩‍❤️‍👩', ['道', '👩‍❤️‍👩']],
			['دوجو', ['دوجو', null]],
		];
	}

	/**
	 * @dataProvider getEmojiNames
	 */
	public function testEmoji(string $name, array $array): void {
		self::assertEquals($array, EmojiSplitter::split($name));
	}
}
