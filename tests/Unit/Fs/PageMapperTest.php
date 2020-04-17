<?php

namespace Unit\Fs;

use OC\Files\Node\Folder;
use OCA\Wiki\Fs\PageMapper;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use PHPUnit\Framework\TestCase;

class PageMapperTest extends TestCase {
	private $db;
	private $l10n;
	private $root;
	private $logger;
	private $appName = 'wiki';
	private $mapper;

	protected function setUp()
	{
		parent::setUp();

		$this->db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->root = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();

		$this->mapper = new PageMapper($this->db, $this->l10n, $this->root, $this->logger, $this->appName);
	}

	public function titleProvider(): array {
		return [
			['string with forbidden chars *|/\\:"<>?', 'string with forbidden chars'],
			["string with forbidden UTF-8 chars #1: \xF0\x90\x80\x80", 'string with forbidden UTF-8 chars #1'],
			["string with forbidden UTF-8 chars #2: \xF0\xBF\xBF\xBF", 'string with forbidden UTF-8 chars #2'],
			["string with forbidden UTF-8 chars #3: \xF1\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with forbidden UTF-8 chars #3: \xF4\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with allowed UTF-8 chars #1: \x61\xC3\xB6\x61", "string with allowed UTF-8 chars #1 \x61\xC3\xB6\x61"],
			['string with spaces', 'string with spaces'],
			[' string with leading space', 'string with leading space'],
			['.string with leading dot', 'string with leading dot'],
			['string with trailing space ', 'string with trailing space'],
			['', 'New Page']
		];
	}

	/**
	 * @dataProvider titleProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testSanitiseTitle(string $input, string $output): void {
		$this->l10n->method('t')
			->willReturn('New Page');

		$this->assertEquals($output, $this->mapper->sanitiseTitle($input));
		$this->mapper->sanitiseTitle('abc');

	}

	public function filenameProvider(): array {
		return [
			['File exists1', 'File exists1 (2)'],
			['File exists2', 'File exists2 (4)'],
			['File exists2 (3)', 'File exists2 (4)'],
			['File exists3', 'File exists3 (2)'],
			['File exists4 (9)', 'File exists4 (10)'],
			['File exists5 (1i)', 'File exists5 (1i) (2)'],
			['File new', 'File new'],
			[' (2)', ' (3)']
		];

	}

	/**
	 * @dataProvider filenameProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testGenerateFilename(string $input, string $output): void {
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('nodeExists')
			->willReturnMap([
					['File exists1.md', true],
					['File exists2.md', true],
					['File exists2 (2).md', true],
					['File exists2 (3).md', true],
					['File exists3.md', true],
					['File exists3 (1).md', true],
					['File exists4 (9).md', true],
					['File exists5 (1).md', true],
					[' (2).md', true],
					['File exists5 (1i).md', true]
			]);

		$this->assertEquals($output, PageMapper::generateFilename($folder, $input));
    }
}
