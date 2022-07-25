<?php
namespace OCA\Collectives\Search\FileSearch;

/**
 * This tokenizer is based on the ProductTokenizer which splits on whole words. But then tokenizes on pairs of words
 * similarly to the bi-gram tokenizer.
 */
class ClauseTokenizer extends WordTokenizer
{
	public function tokenize($text, $stopwords = []): array {
		$words = parent::tokenize($text, $stopwords);

		$tokens = [];
		$lastWord = '';
		foreach ($words as $word) {
			if (strlen($word) <= 3) {
				continue;
			}

			if ($lastWord) {
				$tokens[] = $lastWord . ' ' . $word;
			}
			$lastWord = $word;
		}

		return $tokens ?: explode(' ', $text, 2);
	}
}
