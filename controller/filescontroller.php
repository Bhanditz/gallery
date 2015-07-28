<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Olivier Paroz 2014-2015
 * @copyright Robin Appelman 2012-2014
 */

namespace OCA\GalleryPlus\Controller;

use OCP\IRequest;
use OCP\Files\Folder;
use OCP\ILogger;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;

use OCA\GalleryPlus\Service\SearchFolderService;
use OCA\GalleryPlus\Service\ConfigService;
use OCA\GalleryPlus\Service\SearchMediaService;

/**
 * Class FilesController
 *
 * @package OCA\GalleryPlus\Controller
 */
class FilesController extends Controller {

	use PathManipulation;
	use JsonHttpError;

	/**
	 * @var SearchFolderService
	 */
	private $searchFolderService;
	/**
	 * @var ConfigService
	 */
	private $configService;
	/**
	 * @var SearchMediaService
	 */
	private $searchMediaService;
	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param SearchFolderService $searchFolderService
	 * @param ConfigService $configService
	 * @param SearchMediaService $searchMediaService
	 * @param ILogger $logger
	 */
	public function __construct(
		$appName,
		IRequest $request,
		SearchFolderService $searchFolderService,
		ConfigService $configService,
		SearchMediaService $searchMediaService,
		ILogger $logger
	) {
		parent::__construct($appName, $request);

		$this->searchFolderService = $searchFolderService;
		$this->configService = $configService;
		$this->searchMediaService = $searchMediaService;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Returns a list of all media files available to the authenticated user
	 *
	 *    * Authentication can be via a login/password or a token/(password)
	 *    * For private galleries, it returns all media files, with the full path from the root
	 *     folder For public galleries, the path starts from the folder the link gives access to
	 *     (virtual root)
	 *    * An exception is only caught in case something really wrong happens. As we don't test
	 *     files before including them in the list, we may return some bad apples
	 *
	 * @param string $location a path representing the current album in the app
	 * @param string $features the list of supported features
	 * @param string $etag the last known etag in the client
	 *
	 * @return array <string,array<string,string|int>>|Http\JSONResponse
	 */
	public function getFiles($location, $features, $etag) {
		$features = explode(',', $features);
		$mediaTypesArray = explode(';', $this->request->getParam('mediatypes'));
		$files = [];
		try {
			/** @var Folder $folderNode */
			list($folderPathFromRoot, $folderNode, $locationHasChanged) =
				$this->searchFolderService->getCurrentFolder(rawurldecode($location), $features);
			$albumInfo =
				$this->configService->getAlbumInfo($folderNode, $folderPathFromRoot, $features);

			if ($albumInfo['etag'] !== $etag) {
				$files = $this->searchMediaService->getMediaFiles(
					$folderNode, $mediaTypesArray, $features
				);
				$files = $this->fixPaths($files, $folderPathFromRoot);
			}

			return $this->formatResults($files, $albumInfo, $locationHasChanged);
		} catch (\Exception $exception) {
			return $this->error($exception);
		}
	}

	/**
	 * Generates shortened paths to the media files
	 *
	 * We only want to keep one folder between the current folder and the found media file
	 * /root/folder/sub1/sub2/file.ext
	 * becomes
	 * /root/folder/file.ext
	 *
	 * @param $files
	 * @param $folderPathFromRoot
	 *
	 * @return array
	 */
	private function fixPaths($files, $folderPathFromRoot) {
		if (!empty($files)) {
			foreach ($files as &$file) {
				$file['path'] = $this->getReducedPath($file['path'], $folderPathFromRoot);
			}
		}

		return $files;
	}

	/**
	 * Simply builds and returns an array containing the list of files, the album information and
	 * whether the location has changed or not
	 *
	 * @param array <string,string|int> $files
	 * @param array $albumInfo
	 * @param bool $locationHasChanged
	 *
	 * @return array
	 */
	private function formatResults($files, $albumInfo, $locationHasChanged) {
		return [
			'files'              => $files,
			'albuminfo'          => $albumInfo,
			'locationhaschanged' => $locationHasChanged
		];
	}

}
