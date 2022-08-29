<?php

/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\GpxPod\Service;

use Exception;
use OCA\GpxPod\AppInfo\Application;
use OCP\Http\Client\IClientService;
use phpGPX\Models\GpxFile;

require_once __DIR__ . '/../../vendor/autoload.php';

class ElevationService {

	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	public function __construct (IClientService  $clientService) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param GpxFile $gpxFile
	 * @return GpxFile
	 * @throws Exception
	 */
	public function correctElevations(GpxFile $gpxFile): GpxFile {
		$coordinates = [];
		foreach ($gpxFile->tracks as $track) {
			foreach ($track->segments as $segment) {
				foreach ($segment->points as $point) {
					if ($point->longitude === null || $point->latitude === null) {
						$coordinates[] = [
							'latitude' => 0,
							'longitude' => 0,
						];
					} else {
						$coordinates[] = [
							'latitude' => $point->latitude,
							'longitude' => $point->longitude,
						];
					}
				}
			}
		}

		$correctedCoordinates = $this->request($coordinates);

		$i = 0;
		foreach ($gpxFile->tracks as $track) {
			foreach ($track->segments as $segment) {
				foreach ($segment->points as $point) {
					$point->elevation = $correctedCoordinates['results'][$i]['elevation'] ?? 0;
					$i++;
				}
			}
		}

		return $gpxFile;
	}

	/**
	 * @param array $coordinates
	 * @return string[]
	 * @throws Exception
	 */
	private function request(array $coordinates): array {
		$url = 'https://api.open-elevation.com/api/v1/lookup';
		$options = [
			'headers' => [
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'User-Agent' => Application::USER_AGENT,
			],
			'body' => json_encode(['locations' => $coordinates]),
		];

		$response = $this->client->post($url, $options);

		$body = $response->getBody();
		$respCode = $response->getStatusCode();

		if ($respCode >= 400) {
			return ['error' => 'elevation error'];
		} else {
			return json_decode($body, true);
		}
	}
}
