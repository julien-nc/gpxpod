<?php

/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2023
 */

namespace OCA\GpxPod\Service;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\GpxPod\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use Throwable;

class MapService {

	private IClient $client;

	public function __construct (IClientService $clientService,
								 private LoggerInterface $logger,
								 private IL10N $l10n) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return string|null
	 * @throws Exception
	 */
	public function getRasterTile(string $service, int $x, int $y, int $z): ?string {
		if ($service === 'osm') {
			$s = 'abc'[mt_rand(0, 2)];
			$url = 'https://' . $s . '.tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'osm-highres') {
			$url = 'https://tile.osmand.net/hd/' . $z . '/' . $x . '/' . $y . '.png';
		} elseif ($service === 'esri-topo') {
			$url = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/' . $z . '/' . $y . '/' . $x;
		} elseif ($service === 'watercolor') {
			// $s = 'abc'[mt_rand(0, 2)];
			// $url = 'http://' . $s . '.tile.stamen.com/watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
			$s = 'abcd'[mt_rand(0, 3)];
			$url = 'https://stamen-tiles.' . $s . '.ssl.fastly.net/watercolor/' . $z . '/' . $x . '/' . $y . '.jpg';
		} else {
			$s = 'abc'[mt_rand(0, 2)];
			$url = 'https://' . $s . '.tile.openstreetmap.org/' . $z . '/' . $x . '/' . $y . '.png';
		}
		return $this->client->get($url)->getBody();
	}

	/**
	 * Search items
	 *
	 * @param string $userId
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array request result
	 */
	public function searchLocation(string $userId, string $query, int $offset = 0, int $limit = 5): array {
		// no pagination...
		$limitParam = $offset + $limit;
		$params = [
			'format' => 'json',
			'addressdetails' => 1,
			'extratags' => 1,
			'namedetails' => 1,
			'limit' => $limitParam,
		];
		$result = $this->request($userId, 'search/' . urlencode($query), $params);
		if (!isset($result['error'])) {
			return array_slice($result, $offset, $limit);
		}
		return $result;
	}

	/**
	 * Make an HTTP request to the Osm API
	 * @param string|null $userId
	 * @param string $endPoint The path to reach in api.github.com
	 * @param array $params Query parameters (key/val pairs)
	 * @param string $method HTTP query method
	 * @param bool $rawResponse
	 * @return array decoded request result or error
	 */
	public function request(?string $userId, string $endPoint, array $params = [], string $method = 'GET', bool $rawResponse = false): array {
		try {
			$url = 'https://nominatim.openstreetmap.org/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud OpenStreetMap integration',
//					'Authorization' => 'MediaBrowser Token="' . $token . '"',
					'Content-Type' => 'application/json',
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($rawResponse) {
					return [
						'body' => $body,
						'headers' => $response->getHeaders(),
					];
				} else {
					return json_decode($body, true) ?: [];
				}
			}
		} catch (ClientException | ServerException $e) {
			$responseBody = $e->getResponse()->getBody();
			$parsedResponseBody = json_decode($responseBody, true);
			if ($e->getResponse()->getStatusCode() === 404) {
				// Only log inaccessible github links as debug
				$this->logger->debug('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			} else {
				$this->logger->warning('Osm API error : ' . $e->getMessage(), ['response_body' => $parsedResponseBody, 'app' => Application::APP_ID]);
			}
			return [
				'error' => $e->getMessage(),
				'body' => $parsedResponseBody,
			];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Osm API error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
