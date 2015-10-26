<?php
/**
 * ownCloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@gmx.fr>
 * @copyright Julien Veyssier 2015
 */

namespace OCA\GpxPod\Controller;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {


	private $userId;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$params = ['user' => $this->userId];
		$response = new TemplateResponse('gpxpod', 'main', $params);  // templates/main.php
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*')  // chrome breaks on audio elements
			->addAllowedFrameDomain('https://youtube.com')
			->addAllowedFrameDomain('https://www.youtube.com')
			->addAllowedFrameDomain('https://player.vimeo.com')
			->addAllowedFrameDomain('https://www.player.vimeo.com');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Simply method that posts back the payload of the request
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function doEcho($echo) {
		return new DataResponse(['echo' => $echo]);
	}
	
	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getgeo($title, $folder) {
		$data_folder = getcwd().'/data/'.$this->userId.'/files/gpx';
		$response = new DataResponse(
			[
				'track'=>file_get_contents("$data_folder/$folder/$title.geojson")
				//'dd' => $data_folder
			]
		);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*')  // chrome breaks on audio elements
			->addAllowedFrameDomain('https://youtube.com')
			->addAllowedFrameDomain('https://www.youtube.com')
			->addAllowedFrameDomain('https://player.vimeo.com')
			->addAllowedFrameDomain('https://www.player.vimeo.com');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
	
	/**
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getgeocol($title, $folder) {
		$data_folder = getcwd().'/data/'.$this->userId.'/files/gpx';
		$response = new DataResponse(
			[
				'track'=>file_get_contents("$data_folder/$folder/$title.geojson.colored")
				//'dd' => $data_folder
			]
		);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*')  // chrome breaks on audio elements
			->addAllowedFrameDomain('https://youtube.com')
			->addAllowedFrameDomain('https://www.youtube.com')
			->addAllowedFrameDomain('https://player.vimeo.com')
			->addAllowedFrameDomain('https://www.player.vimeo.com');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
	
}
