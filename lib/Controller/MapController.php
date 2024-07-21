<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2015
 */

namespace OCA\GpxPod\Controller;

use OCA\GpxPod\Service\MapService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

class MapController extends Controller {

	public function __construct(
		$appName,
		IRequest $request,
		private MapService $mapService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}


	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string|null $s
	 * @return DataDisplayResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getRasterTile(string $service, int $x, int $y, int $z, ?string $s = null): DataDisplayResponse {
		try {
			$response = new DataDisplayResponse($this->mapService->getRasterTile($service, $x, $y, $z, $s));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception | Throwable $e) {
			$this->logger->debug('Raster tile not found', ['exception' => $e]);
			return new DataDisplayResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $fontstack
	 * @param string $range
	 * @param string|null $key
	 * @return DataDisplayResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTilerFont(string $fontstack, string $range, ?string $key = null): DataDisplayResponse {
		try {
			$response = new DataDisplayResponse($this->mapService->getMapTilerFont($fontstack, $range, $key));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception | Throwable $e) {
			$this->logger->debug('Font not found', ['exception' => $e]);
			return new DataDisplayResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @param string $q
	 * @param string $rformat
	 * @param int|null $polygon_geojson
	 * @param int|null $addressdetails
	 * @param int|null $namedetails
	 * @param int|null $extratags
	 * @param int $limit
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function nominatimSearch(
		string $q, string $rformat = 'json', ?int $polygon_geojson = null, ?int $addressdetails = null,
		?int $namedetails = null, ?int $extratags = null, int $limit = 10
	): DataResponse {
		$extraParams = [
			'polygon_geojson' => $polygon_geojson,
			'addressdetails' => $addressdetails,
			'namedetails' => $namedetails,
			'extratags' => $extratags,
		];
		$searchResults = $this->mapService->searchLocation($this->userId, $q, $rformat, $extraParams, 0, $limit);
		if (isset($searchResults['error'])) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$response = new DataResponse($searchResults);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}
}
