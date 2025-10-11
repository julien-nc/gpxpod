<?php

/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\GpxPod\Controller;

use OCA\GpxPod\AppInfo\Application;
use OCA\GpxPod\Db\Directory;
use OCA\GpxPod\Db\DirectoryMapper;
use OCA\GpxPod\Db\TileServerMapper;
use OCA\GpxPod\Db\Track;
use OCA\GpxPod\Db\TrackMapper;
use OCA\GpxPod\Service\ConversionService;
use OCA\GpxPod\Service\KmlConversionService;
use OCA\GpxPod\Service\MapService;
use OCA\GpxPod\Service\ProcessService;
use OCA\GpxPod\Service\SrtmGeotiffElevationService;
use OCA\GpxPod\Service\ToolsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

use OCP\Security\ICrypto;
use OCP\Share\IManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PageNUtilsControllerTest extends TestCase {

	private $appName;
	private $request;

	private $container;
	private $app;

	private $pageController;
	private $utilsController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$userManager = $c->get(IUserManager::class);
		// clear test users
		$user = $userManager->get('test');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test2');
		if ($user !== null) {
			$user->delete();
		}
		$user = $userManager->get('test3');
		if ($user !== null) {
			$user->delete();
		}

		// CREATE DUMMY USERS
		$u1 = $userManager->createUser('test', 'T0T0T0');
		$u1->setEMailAddress('toto@toto.net');
		$u2 = $userManager->createUser('test2', 'T0T0T0');
		$u3 = $userManager->createUser('test3', 'T0T0T0');
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->createGroup('group1test');
		$groupManager->get('group1test')->addUser($u1);
		$groupManager->createGroup('group2test');
		$groupManager->get('group2test')->addUser($u2);
	}

	protected function setUp(): void {
		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;
		$this->appName = 'gpxpod';
		$this->request = $c->get(IRequest::class);

		$this->pageController = new PageController(
			$this->appName,
			$this->request,
			$c->get(LoggerInterface::class),
			$c->get(IConfig::class),
			$c->get(IAppConfig::class),
			$c->get(IInitialState::class),
			$c->get(IRootFolder::class),
			$c->get(ProcessService::class),
			$c->get(ConversionService::class),
			$c->get(ToolsService::class),
			$c->get(SrtmGeotiffElevationService::class),
			$c->get(MapService::class),
			$c->get(DirectoryMapper::class),
			$c->get(TrackMapper::class),
			$c->get(TileServerMapper::class),
			$c->get(IManager::class),
			$c->get(IL10N::class),
			$c->get(IURLGenerator::class),
			$c->get(KmlConversionService::class),
			$c->get(ICacheFactory::class),
			'test'
		);

		$this->utilsController = new UtilsController(
			$this->appName,
			$this->request,
			$c->get(IConfig::class),
			$c->get(IAppConfig::class),
			$c->get(ICrypto::class),
			$c->get(IDBConnection::class),
			$c->get(TileServerMapper::class),
			'test'
		);
	}

	/*
	public static function tearDownAfterClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		$user = $userManager->get('test');
		$user->delete();
		$user = $userManager->get('test2');
		$user->delete();
		$user = $userManager->get('test3');
		$user->delete();
		$groupManager = $c->get(IGroupManager::class);
		$groupManager->get('group1test')->delete();
		$groupManager->get('group2test')->delete();
	}
	*/

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
	}

	public function testUtils() {
		// DELETE OPTIONS VALUES
		$resp = $this->utilsController->deleteOptionsValues();
		$data = $resp->getData();
		$done = $data['done'];
		$this->assertEquals($done, 1);

		// SET OPTIONS
		$resp = $this->utilsController->saveOptionValue('lala', 'lolo');
		$data = $resp->getData();
		$done = $data['done'];
		$this->assertEquals($done, 1);

		// GET OPTIONS
		$resp = $this->utilsController->getOptionsValues();
		$data = $resp->getData();
		$values = $data['values'];
		$this->assertEquals($values['lala'], 'lolo');
	}

	public function testPage() {
		// CLEAR OPTIONS
		$resp = $this->utilsController->deleteOptionsValues();
		$data = $resp->getData();
		$done = $data['done'];
		$this->assertEquals($done, 1);

		// clear DB
		$resp = $this->utilsController->cleanDB();
		$data = $resp->getData();
		$done = $data['done'];
		$this->assertEquals($done, 1);

		// create files
		$userfolder = $this->container->query('ServerContainer')->getUserFolder('test');
		$content1 = file_get_contents('tests/tracks/testFile1.gpx');
		$userfolder->newFile('testFile1.gpx')->putContent($content1);
		$content2 = file_get_contents('tests/tracks/testFile2.gpx');
		$userfolder->newFile('testFile2.gpx')->putContent($content2);
		$content3 = file_get_contents('tests/tracks/testFile3Route.gpx');
		$userfolder->newFile('testFile3Route.gpx')->putContent($content3);
		$content4 = file_get_contents('tests/tracks/testFile4MissingData.gpx');
		$userfolder->newFile('testFile4MissingData.gpx')->putContent($content4);
		$content5 = file_get_contents('tests/tracks/testFile5RouteMissingData.gpx');
		$userfolder->newFile('testFile5RouteMissingData.gpx')->putContent($content5);
		$content6 = file_get_contents('tests/tracks/testFile6Error.gpx');
		$userfolder->newFile('testFile6Error.gpx')->putContent($content6);
		$content7 = file_get_contents('tests/tracks/testFile7Empty.gpx');
		$userfolder->newFile('testFile7Empty.gpx')->putContent($content7);

		$contentPic1 = file_get_contents('tests/pictures/nc.jpg');
		$userfolder->newFile('nc.jpg')->putContent($contentPic1);
		$userfolder->newFile('nc2.jpg')->putContent($contentPic1);
		$contentPic2 = file_get_contents('tests/pictures/nut.jpg');
		$userfolder->newFile('nut.jpg')->putContent($contentPic2);
		$userfolder->newFile('nut2.jpg')->putContent($contentPic2);

		$userfolder->newFolder('subdir');
		$subfolder = $userfolder->get('subdir');
		$subfolder->newFile('subTestFile1.gpx')->putContent($content1);
		$subfolder->newFile('subTestFile2.gpx')->putContent($content2);

		$subfolder->newFile('nc.jpg')->putContent($contentPic1);
		$subfolder->newFile('nut.jpg')->putContent($contentPic2);
		$subfolder->newFile('nc2.jpg')->putContent($contentPic1);
		$subfolder->newFile('nut2.jpg')->putContent($contentPic2);

		$userfolder->newFolder('convertion');
		$convertfolder = $userfolder->get('convertion');
		// TODO remove this line
		//		$convertfolder->newFile('subTestFile1.gpx')->putContent($content1);
		$contentKml = file_get_contents('tests/tracks/testKml.kml');
		$convertfolder->newFile('testKml.kml')->putContent($contentKml);

		$contentIgc = file_get_contents('tests/tracks/testIgc.igc');
		$convertfolder->newFile('testIgc.igc')->putContent($contentIgc);

		$contentTcx = file_get_contents('tests/tracks/testTcx.tcx');
		$convertfolder->newFile('testTcx.tcx')->putContent($contentTcx);

		$contentFit = file_get_contents('tests/tracks/testFit.fit');
		$convertfolder->newFile('testFit.fit')->putContent($contentFit);

		$allDirs = $this->pageController->getDirectories('test');
		/** @var Directory[] $dirsByPath */
		$dirsByPath = [];
		foreach ($allDirs as $dir) {
			//			echo 'set $dirsByPath["' . $dir['path'] . '"]' . "\n";
			$dirsByPath[$dir['path']] = $dir;
		}

		if (isset($dirsByPath['/'])) {
			$resp = $this->pageController->deleteDirectory($dirsByPath['/']['id']);
			$status = $resp->getStatus();
			$this->assertEquals(200, $status);
		}
		// add top dir if needed
		$resp = $this->pageController->addDirectory('/');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// add dir which is already there
		$resp = $this->pageController->addDirectory('/');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// add dir which does not exist
		$resp = $this->pageController->addDirectory('/doesNotExist');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		if (isset($dirsByPath['/subdir'])) {
			$resp = $this->pageController->deleteDirectory($dirsByPath['/subdir']['id']);
			$status = $resp->getStatus();
			$this->assertEquals(200, $status);
		}
		// add sub dir
		$resp = $this->pageController->addDirectory('/subdir');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		$allDirs = $this->pageController->getDirectories('test');
		/** @var Directory[] $dirsByPath */
		$dirsByPath = [];
		foreach ($allDirs as $dir) {
			//			echo 'set $dirsByPath["' . $dir['path'] . '"]' . "\n";
			$dirsByPath[$dir['path']] = $dir;
		}

		$resp = $this->pageController->deleteDirectory($dirsByPath['/']['id']);
		$resp = $this->pageController->deleteDirectory($dirsByPath['/subdir']['id']);

		// test add recursive
		$resp = $this->pageController->addDirectoryRecursive('/');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$resp = $this->pageController->addDirectoryRecursive('/doesNotExist');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$allDirs = $this->pageController->getDirectories('test');
		$dirsByPath = [];
		foreach ($allDirs as $dir) {
			$dirsByPath[$dir['path']] = $dir;
		}
		$this->assertEquals(true, isset($dirsByPath['/subdir'], $dirsByPath['/']));

		// ============== get markers =========================
		$resp = $this->pageController->getTrackMarkersJson(-1, '/doesNotExist', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(Http::STATUS_NOT_FOUND, $status);

		$resp = $this->pageController->getTrackMarkersJson($dirsByPath['/']['id'], '/', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$tracks = $data['tracks'];
		$this->assertEquals(5, count($tracks));

		foreach ($tracks as $id => $track) {
			if ($track['name'] === 'testFile2.gpx') {
				// total distance
				$this->assertEquals(28034, (int)$track['total_distance']);
			}
			if ($track['name'] === 'testFile1.gpx') {
				// total distance
				$this->assertEquals(30878, (int)$track['total_distance']);
				// marker NSEW
				$this->assertEquals(72.8588831518, $track['north']);
				$this->assertEquals(2.8588831518, $track['south']);
				$this->assertEquals(70.1049597245, $track['east']);
				$this->assertEquals(0.1049597245, $track['west']);
			}
		}

		$resp = $this->pageController->getTrackMarkersJson($dirsByPath['/subdir']['id'], '/subdir', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		/** @var Track[] $tracks */
		$tracks = $data['tracks'];
		$this->assertEquals(2, count($tracks));

		foreach ($tracks as $id => $track) {
			if ($track['name'] === 'subTestFile2.gpx') {
				$this->assertEquals(28034, (int)$track['total_distance']);
			}
			if ($track['name'] === 'subTestFile1.gpx') {
				$this->assertEquals(30878, (int)$track['total_distance']);
			}
		}

		// test clean db from absent files
		$userfolder->get('/subdir/subTestFile2.gpx')->delete();
		$userfolder->get('/nut2.jpg')->delete();
		$userfolder->get('/nc2.jpg')->delete();
		$userfolder->get('/subdir/nut2.jpg')->delete();
		$userfolder->get('/subdir/nc2.jpg')->delete();

		$resp = $this->pageController->getTrackMarkersJson($dirsByPath['/subdir']['id'], '/subdir', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$tracks = $data['tracks'];
		$this->assertEquals(1, count($tracks));

		// touch files to process them again
		$userfolder->get('/testFile1.gpx')->touch();
		$userfolder->get('/nc.jpg')->touch();
		$userfolder->get('/nut.jpg')->touch();

		// // recursive
		// $resp = $this->pageController->getTrackMarkersJson($dirsByPath['/']['id'], '/', false);
		// $data = $resp->getData();
		// $status = $resp->getStatus();
		// $this->assertEquals(200, $status);
		// $tracks = $data['tracks'];
		// $this->assertEquals(10, count($tracks));
		// $pics = json_decode($data['pictures'], true);
		// $this->assertEquals(2, count($pics));

		// TODO check that conversion gives probable results

		$allDirs = $this->pageController->getDirectories('test');
		$dirsByPath = [];
		foreach ($allDirs as $dir) {
			$dirsByPath[$dir['path']] = $dir;
		}
		$resp = $this->pageController->getTrackMarkersJson($dirsByPath['/convertion']['id'], '/convertion', false);

		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testKml.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testIgc.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testTcx.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testFit.gpx'));

		// not recursive
		$resp = $this->pageController->getTrackMarkersJson($dirsByPath['/']['id'], '/', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$tracks = $data['tracks'];
		$this->assertEquals(5, count($tracks));
		$pics = $data['pictures'];
		// TODO check why that fails
		//		$this->assertEquals(1, count($pics));

		// test index
		$resp = $this->pageController->index();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);

		// test fallback conversion
		$resp = $this->utilsController->cleanDB();
		$userfolder->get('/convertion/testKml.gpx')->delete();
		$userfolder->get('/convertion/testIgc.gpx')->delete();
		$userfolder->get('/convertion/testTcx.gpx')->delete();
		$userfolder->get('/convertion/testFit.gpx')->delete();
		$oldPath = \getenv('PATH');
		putenv('PATH=""');

		$resp = $this->pageController->addDirectory('/convertion');
		$addedId = $resp->getData();
		$resp = $this->pageController->getTrackMarkersJson($addedId, '/convertion', false);
		$data = $resp->getData();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$tracks = $data['tracks'];
		$tracksByPath = [];
		foreach ($tracks as $track) {
			$tracksByPath[$track['trackpath']] = $track;
		}
		$this->assertEquals(4, count($tracks));
		$pics = $data['pictures'];
		$this->assertEquals(0, count($pics));

		// TODO check that conversion gives probable results

		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testKml.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testIgc.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testTcx.gpx'));
		$this->assertEquals(true, $userfolder->nodeExists('/convertion/testFit.gpx'));

		putenv('PATH="' . $oldPath . '"');

		// // delete tracks
		// $this->assertEquals(true, $userfolder->nodeExists('/testFile1.gpx'));
		// $resp = $this->pageController->deleteTracks(['/testFile1.gpx', '/doesNotExist.gpx']);
		// $status = $resp->getStatus();
		// $this->assertEquals(200, $status);
		// $data = $resp->getData();
		// $this->assertEquals('/testFile1.gpx', $data['deleted']);
		// $this->assertEquals('/doesNotExist.gpx', $data['notdeleted']);
		// $this->assertEquals(false, $userfolder->nodeExists('/testFile1.gpx'));

		// delete directories
		$resp = $this->pageController->deleteDirectory($dirsByPath['/']['id']);
		$resp = $this->pageController->deleteDirectory($dirsByPath['/']['id']);
	}
}
