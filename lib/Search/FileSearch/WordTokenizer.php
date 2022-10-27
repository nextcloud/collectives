<?php

namespace OCA\Collectives\Search\FileSearch;

use TeamTNT\TNTSearch\Support\ProductTokenizer;

/**
 * This tokenizer is based on the ProductTokenizer but strips away non-letters and non-numbers characters.
 */
class WordTokenizer extends ProductTokenizer {
	/**
	 * @param string $text
	 * @param array $stopwords
	 *
	 * @return array|false|string[]
	 */
	public function tokenize($text, $stopwords = []) {
		$regexNotCharacters = '/([^\p{L}\p{N}@])+/u';
		$text = preg_replace($regexNotCharacters, ' ', $text);

		return parent::tokenize($text, $stopwords);
	}
}
