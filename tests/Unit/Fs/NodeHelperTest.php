<?php

namespace Unit\Fs;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\NotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class NodeHelperTest extends TestCase {
	private IL10N $l10n;
	private NodeHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		$this->helper = new NodeHelper($db, $this->l10n);
	}

	public function nameProvider(): array {
		return [
			['string with slash /', 'string with slash -'],
			['string with forbidden chars *|\\:"<>?', 'string with forbidden chars'],
			["string with forbidden UTF-8 chars #1: \xF0\x90\x80\x80", 'string with forbidden UTF-8 chars #1'],
			["string with forbidden UTF-8 chars #2: \xF0\xBF\xBF\xBF", 'string with forbidden UTF-8 chars #2'],
			["string with forbidden UTF-8 chars #3: \xF1\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with forbidden UTF-8 chars #3: \xF4\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with allowed UTF-8 chars #1: \x61\xC3\xB6\x61", "string with allowed UTF-8 chars #1 \x61\xC3\xB6\x61"],
			['string with spaces', 'string with spaces'],
			[' string with leading space', 'string with leading space'],
			['.string with leading dot', 'string with leading dot'],
			['string with trailing space ', 'string with trailing space'],
			['', 'New File']
		];
	}

	/**
	 * @dataProvider nameProvider
	 *
	 * @param string $input
	 * @param string $output
	 */
	public function testSanitiseFilename(string $input, string $output): void {
		$this->l10n->method('t')
			->willReturnArgument(0);

		self::assertEquals($output, $this->helper->sanitiseFilename($input));
		self::assertEquals('New Page', $this->helper->sanitiseFilename('', 'New Page'));
	}

	public function testGetFileById(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getById')
			->willReturnMap([
				[1, [$file]],
				[2, []],
			]);

		self::assertEquals($file, $this->helper->getFileById($folder, 1));

		$this->expectException(NotFoundException::class);
		$this->helper->getFileById($folder, 2);
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
			[' (2)', ' (3)'] ];
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
				['File exists1', true],
				['File exists2', true],
				['File exists2 (2)', true],
				['File exists2 (3)', true],
				['File exists3', true],
				['File exists3 (1)', true],
				['File exists4 (9)', true],
				['File exists5 (1)', true],
				[' (2)', true],
				['File exists5 (1i)', true]
			]);

		self::assertEquals($output, NodeHelper::generateFilename($folder, $input));
	}
}
