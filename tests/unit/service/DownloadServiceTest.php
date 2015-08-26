<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Olivier Paroz 2015
 */
namespace OCA\GalleryPlus\Service;
include_once 'FilesServiceTest.php';

use OCP\Files\File;

/**
 * Class DownloadServiceTest
 *
 * @package OCA\GalleryPlus\Controller
 */
class DownloadServiceTest extends FilesServiceTest {

	use Base64Encode;

	/** @var DownloadService */
	protected $service;

	/**
	 * Test set up
	 */
	public function setUp() {
		parent::setUp();

		$this->service = new DownloadService (
			$this->appName,
			$this->environment,
			$this->logger
		);
	}

	public function testDownloadRawFile() {
		/** @type File $file */
		$file = $this->mockFile(12345);

		$download = [
			'preview'  => $file->getContent(),
			'mimetype' => $file->getMimeType()
		];

		$downloadResponse = $this->service->downloadFile($file);

		$this->assertSame($download['mimetype'], $downloadResponse['mimetype']);
		$this->assertSame($download['preview'], $downloadResponse['preview']);
	}

	public function testDownloadBase64EncodedFile() {
		/** @type File $file */
		$file = $this->mockFile(12345);

		$download = [
			'preview'  => $this->encode($file->getContent()),
			'mimetype' => $file->getMimeType()
		];

		$downloadResponse = $this->service->downloadFile($file, true);

		$this->assertSame($download['mimetype'], $downloadResponse['mimetype']);
		$this->assertSame($download['preview'], $downloadResponse['preview']);
	}

	public function testDownloadNonExistentFile() {
		$file = $this->mockBadFile(12345);

		$downloadResponse = $this->service->downloadFile($file);

		$this->assertFalse($downloadResponse);
	}

}
