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
include_once 'ServiceTest.php';

/**
 * Class SearchFolderServiceTest
 *
 * @package OCA\GalleryPlus\Controller
 */
class SearchFolderServiceTest extends ServiceTest {

	/** @var SearchFolderService */
	protected $service;

	/**
	 * Test set up
	 */
	public function setUp() {
		parent::setUp();

		$this->service = new SearchFolderService (
			$this->appName,
			$this->environment,
			$this->logger
		);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Service\NotFoundServiceException
	 */
	public function testSendFolderWithNullFolder() {
		$path = '';
		$node = null;
		$locationHasChanged = false;

		self::invokePrivate($this->service, 'sendFolder', [$path, $node, $locationHasChanged]);
	}

	/**
	 * @expectedException \OCA\GalleryPlus\Service\ForbiddenServiceException
	 */
	public function testSendFolderWithNonAvailableFolder() {
		$path = '';
		$nodeId = 94875;
		$isReadable = false;
		$node = $this->mockFolder('home::user', $nodeId, [], $isReadable);
		$locationHasChanged = false;

		self::invokePrivate($this->service, 'sendFolder', [$path, $node, $locationHasChanged]);
	}

	public function testSendFolder() {
		$path = '';
		$nodeId = 94875;
		$files = [];
		$node = $this->mockFolder('home::user', $nodeId, $files);
		$locationHasChanged = false;

		$folder = [$path, $node, $locationHasChanged];

		$response = self::invokePrivate($this->service, 'sendFolder', $folder);

		$this->assertSame($folder, $response);
	}

	public function testSendExternalFolder() {
		$path = '';
		$nodeId = 94875;
		$files = [];
		$shared = $this->mockFolder('shared::12345', $nodeId, $files);
		$rootNodeId = 91919191;
		$rootFiles = [$shared];
		$sharedRoot = $this->mockFolder('shared::99999', $rootNodeId, $rootFiles);
		$this->mockGetVirtualRootFolderOfSharedFolder($sharedRoot);

		$locationHasChanged = false;
		$folder = [$path, $shared, $locationHasChanged];

		$response = self::invokePrivate($this->service, 'sendFolder', $folder);

		$this->assertSame($folder, $response);
	}

	public function providesNodesData() {
		$exception = new \OCA\GalleryPlus\Service\NotFoundServiceException('Boom');

		return [
			[0, $exception],
			[1, []]
		];
	}

	/**
	 * @dataProvider providesNodesData
	 *
	 * That's one way of dealing with mixed data instead of writing the same test twice ymmv
	 *
	 * @param $subDepth
	 * @param array|\Exception $nodes
	 */
	public function testGetNodesWithBrokenListing($subDepth, $nodes) {
		$files = null;
		$folder = $this->mockBrokenDirectoryListing();

		try {
			$response = self::invokePrivate($this->service, 'getNodes', [$folder, $subDepth]);
			$this->assertSame($nodes, $response);
		} catch (\Exception $exception) {
			$this->assertInstanceOf('\OCA\GalleryPlus\Service\NotFoundServiceException', $exception);
			$this->assertSame($nodes->getMessage(), $exception->getMessage());
		}
	}

	public function testIsAllowedAndAvailableWithNullFolder() {
		$node = null;
		$response = self::invokePrivate($this->service, 'isAllowedAndAvailable', [$node]);

		$this->assertFalse($response);
	}

	public function testIsAllowedAndAvailableWithBrokenSetup() {
		$node = $this->mockFolder('home::user', 909090, []);
		$node->method('isReadable')
			 ->willThrowException(new \Exception('Boom'));

		$response = self::invokePrivate($this->service, 'isAllowedAndAvailable', [$node]);

		$this->assertFalse($response);
	}

	public function providesIsPreviewAllowedData() {
		return [
			// Mounted, so looking at options
			[true, true, true],
			[true, false, false],
			// Not mounted, so OK
			[false, true, true],
			[false, false, true]
		];
	}

	/**
	 * @dataProvider providesIsPreviewAllowedData
	 *
	 * @param bool $mounted
	 * @param bool $previewsAllowedOnMountedShare
	 * @param bool $expectedResult
	 */
	public function testIsAllowedWithMountedFolder(
		$mounted, $previewsAllowedOnMountedShare, $expectedResult
	) {
		$nodeId = 12345;
		$files = [];
		$isReadable = true;
		$mount = $this->mockMountPoint($previewsAllowedOnMountedShare);
		$node = $this->mockFolder(
			'webdav::user@domain.com/dav', $nodeId, $files, $isReadable, $mounted, $mount
		);

		$response = self::invokePrivate($this->service, 'isAllowed', [$node]);

		$this->assertSame($expectedResult, $response);
	}

	public function providesLocationChangeData() {
		return [
			[0, false],
			[1, true],
		];
	}

	/**
	 * @dataProvider providesLocationChangeData
	 *
	 * @param int $depth
	 * @param bool $expectedResult
	 */
	public function testHasLocationChanged($depth, $expectedResult) {
		$response = self::invokePrivate($this->service, 'hasLocationChanged', [$depth]);

		$this->assertSame($expectedResult, $response);
	}

	public function providesValidateLocationData() {
		return [
			['folder1', 0, 'folder1'],
			['completely/bogus/set/of/folders/I/give/up', 4, ''],
		];
	}

	/**
	 * @dataProvider providesValidateLocationData
	 *
	 * @param string $location
	 * @param int $depth
	 * @param bool $expectedResult
	 */
	public function testValidateLocation($location, $depth, $expectedResult) {
		$response = self::invokePrivate($this->service, 'validateLocation', [$location, $depth]);

		$this->assertSame($expectedResult, $response);
	}

	public function testFindFolderWithFileLocation() {
		$location = 'folder/file1.jpg';
		$fileId = 99999;
		$file = $this->mockJpgFile($fileId);
		$folder = $this->mockFolder('home::user', 10101, [$file]);
		$file->method('getParent')
			 ->willReturn($folder);

		$this->mockGetFileNodeFromVirtualRoot($location, $file);
		$this->mockgetPathFromVirtualRoot($folder, $location);

		$locationHasChanged = false;
		$expectedResult = [$location, $folder, $locationHasChanged];

		$response = self::invokePrivate($this->service, 'findFolder', [$location]);

		$this->assertSame($expectedResult, $response);
	}

	private function mockBrokenDirectoryListing() {
		$folder = $this->getMockBuilder('OCP\Files\Folder')
					   ->disableOriginalConstructor()
					   ->getMock();
		$folder->method('getDirectoryListing')
			   ->willThrowException(new \Exception('Boom'));

		return $folder;
	}

	private function mockGetVirtualRootFolderOfSharedFolder($folder) {
		$this->environment->expects($this->once())
						  ->method('getVirtualRootFolder')
						  ->willReturn($folder);

	}

	private function mockMountPoint($previewsAllowed) {
		$mountPoint = $this->getMockBuilder('\OC\Files\Mount\MountPoint')
						   ->disableOriginalConstructor()
						   ->getMock();
		$mountPoint->method('getOption')
				   ->with(
					   'previews',
					   true
				   )
				   ->willReturn($previewsAllowed);

		return $mountPoint;
	}

	private function mockGetFileNodeFromVirtualRoot($location, $file) {
		$this->environment->expects($this->any())
						  ->method('getNodeFromVirtualRoot')
						  ->with(
							  $location
						  )
						  ->willReturn($file);
	}

	private function mockgetPathFromVirtualRoot($node, $path) {
		$this->environment->expects($this->any())
						  ->method('getPathFromVirtualRoot')
						  ->with(
							  $node
						  )
						  ->willReturn($path);
	}

}
