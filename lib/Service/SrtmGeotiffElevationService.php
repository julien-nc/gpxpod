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
use OCA\GpxPod\AppInfo\Application;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use phpGPX\Models\GpxFile;
use Psr\Log\LoggerInterface;
use ZipArchive;

class SrtmGeotiffElevationService {

	private IClient $client;

	public function __construct(IClientService $clientService,
		private IAppData $appData,
		private LoggerInterface $logger) {
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
					$coordinates[] = [
						'lat' => $point->latitude,
						'lng' => $point->longitude,
					];
				}
			}
		}
		foreach ($gpxFile->routes as $route) {
			foreach ($route->points as $point) {
				$coordinates[] = [
					'lat' => $point->latitude,
					'lng' => $point->longitude,
				];
			}
		}
		foreach ($gpxFile->waypoints as $point) {
			$coordinates[] = [
				'lat' => $point->latitude,
				'lng' => $point->longitude,
			];
		}

		$this->downloadNecessaryFiles($coordinates);
		$correctedElevations = $this->getSrtmElevations($coordinates);

		$i = 0;
		foreach ($gpxFile->tracks as $track) {
			foreach ($track->segments as $segment) {
				foreach ($segment->points as $point) {
					$point->elevation = (isset($correctedElevations[$i]) && is_numeric($correctedElevations[$i]))
						? (float) $correctedElevations[$i]
						: 0;
					$i++;
				}
			}
		}
		foreach ($gpxFile->routes as $route) {
			foreach ($route->points as $point) {
				$point->elevation = (isset($correctedElevations[$i]) && is_numeric($correctedElevations[$i]))
					? (float) $correctedElevations[$i]
					: 0;
				$i++;
			}
		}
		foreach ($gpxFile->waypoints as $point) {
			$point->elevation = (isset($correctedElevations[$i]) && is_numeric($correctedElevations[$i]))
				? (float) $correctedElevations[$i]
				: 0;
			$i++;
		}

		return $gpxFile;
	}

	/**
	 * @param array $coordinates
	 * @return array
	 * @throws NotPermittedException
	 */
	private function getSrtmElevations(array $coordinates): array {
		try {
			$folder = $this->appData->getFolder('srtm');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('srtm');
		}
		$reader = new SRTMGeoTIFFReader($folder, $this->logger);

		$flatCoords = [];
		foreach ($coordinates as $c) {
			$flatCoords[] = $c['lat'];
			$flatCoords[] = $c['lng'];
		}

		$corrected = $reader->getMultipleElevations($flatCoords, false, false);
		return $corrected;
	}

	/**
	 * @param array $coordinates
	 * @return void
	 * @throws NotPermittedException
	 */
	private function downloadNecessaryFiles(array $coordinates): void {
		try {
			$folder = $this->appData->getFolder('srtm');
		} catch (NotFoundException $e) {
			$folder = $this->appData->newFolder('srtm');
		}
		$sizesByName = [];
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file->getSize() === 0) {
				$file->delete();
			} else {
				//				$file->delete();
				$sizesByName[$file->getName()] = $file->getSize();
			}
		}

		foreach ($coordinates as $c) {
			$fileInfo = SRTMGeoTIFFReader::getTileInfo($c['lat'], $c['lng']);
			if ($fileInfo === null) {
				continue;
			}
			$horiz = str_pad($fileInfo['horiz'], 2, '0', STR_PAD_LEFT);
			$vert = str_pad($fileInfo['vert'], 2, '0', STR_PAD_LEFT);
			$fileName = 'srtm_' . $horiz . '_' .  $vert . '.tif';
			if (!isset($sizesByName[$fileName])) {
				$this->download($horiz, $vert, $folder, $fileName);
				$sizesByName[$fileName] = 'whatever';
			}
		}
	}

	/**
	 * @param string $horiz
	 * @param string $vert
	 * @param ISimpleFolder $folder
	 * @param string $fileName
	 * @return void
	 * @throws NotPermittedException
	 */
	private function download(string $horiz, string $vert, ISimpleFolder $folder, string $fileName): void {
		$response = $this->request($horiz, $vert);
		if (isset($response['error'])) {
			return;
		}
		$tempFile = tempnam(sys_get_temp_dir(), 'gpxpod_srtm_');
		file_put_contents($tempFile, $response['body']);
		$zip = new ZipArchive();
		$zip->open($tempFile);
		$index = $zip->locateName($fileName);
		$folder->newFile($fileName, $zip->getFromIndex($index));
	}

	/**
	 * @param string $horiz
	 * @param string $vert
	 * @return string[]
	 * @throws Exception
	 */
	private function request(string $horiz, string $vert): array {
		$url = 'https://srtm.csi.cgiar.org/wp-content/uploads/files/srtm_5x5/TIFF/srtm_' . $horiz . '_' . $vert . '.zip';
		$options = [
			'stream' => true,
			'headers' => [
				'User-Agent' => Application::USER_AGENT,
			],
			'timeout' => 500,
		];

		$response = $this->client->get($url, $options);
		$respCode = $response->getStatusCode();

		if ($respCode >= 400) {
			$this->logger->warning('Error downloading SRTM zip file. Response code:: ' . $respCode, ['app' => Application::APP_ID]);
			return ['error' => 'elevation error'];
		} else {
			return [
				'body' => $response->getBody(),
				'headers' => $response->getHeaders(),
			];
		}
	}
}
