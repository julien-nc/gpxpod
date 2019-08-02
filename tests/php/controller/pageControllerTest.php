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

use \OCA\GpxPod\AppInfo\Application;

class PageNUtilsControllerTest extends \PHPUnit\Framework\TestCase {

    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $pageController;
    private $pageController2;
    private $utilsController;

    public static function setUpBeforeClass(): void {
        $app = new Application();
        $c = $app->getContainer();

        // clear test users
        $user = $c->getServer()->getUserManager()->get('test');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test2');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test3');
        if ($user !== null) {
            $user->delete();
        }

        // CREATE DUMMY USERS
        $u1 = $c->getServer()->getUserManager()->createUser('test', 'T0T0T0');
        $u1->setEMailAddress('toto@toto.net');
        $u2 = $c->getServer()->getUserManager()->createUser('test2', 'T0T0T0');
        $u3 = $c->getServer()->getUserManager()->createUser('test3', 'T0T0T0');
        $c->getServer()->getGroupManager()->createGroup('group1test');
        $c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
        $c->getServer()->getGroupManager()->createGroup('group2test');
        $c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
    }

    protected function setUp(): void {
        $this->appName = 'gpxpod';
        $this->request = $this->getMockBuilder('\OCP\IRequest')
            ->disableOriginalConstructor()
            ->getMock();
        $this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->pageController = new PageController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->query('ServerContainer')->getLogger(),
            $c->query('ServerContainer')->getL10N($c->query('AppName'))
        );

        $this->pageController2 = new PageController(
            $this->appName,
            $this->request,
            'test2',
            $c->query('ServerContainer')->getUserFolder('test2'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->query('ServerContainer')->getLogger(),
            $c->query('ServerContainer')->getL10N($c->query('AppName'))
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager()
        );
    }

    public static function tearDownAfterClass(): void {
        $app = new Application();
        $c = $app->getContainer();
        $user = $c->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test2');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test3');
        $user->delete();
        $c->getServer()->getGroupManager()->get('group1test')->delete();
        $c->getServer()->getGroupManager()->get('group2test')->delete();
    }

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
        $resp = $this->utilsController->getOptionsValues([]);
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
        $contentKml = file_get_contents('tests/tracks/testKml.kml');
        $convertfolder->newFile('testKml.kml')->putContent($contentKml);

        $dirs = $this->pageController->getDirectories('test');

        if (in_array('/', $dirs)) {
            $resp = $this->pageController->delDirectory('/');
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

        if (in_array('/subdir', $dirs)) {
            $resp = $this->pageController->delDirectory('/subdir');
            $status = $resp->getStatus();
            $this->assertEquals(200, $status);
        }
        // add sub dir
        $resp = $this->pageController->addDirectory('/subdir');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->pageController->delDirectory('/');
        $resp = $this->pageController->delDirectory('/subdir');

        // test add recursive
        $resp = $this->pageController->addDirectoryRecursive('/');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $resp = $this->pageController->addDirectoryRecursive('/doesNotExist');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $dirs = $this->pageController->getDirectories('test');
        $this->assertEquals(true, in_array('/subdir', $dirs) and in_array('/', $dirs));

        // ============== get markers =========================
        $resp = $this->pageController->getmarkers('/doesNotExist', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->pageController->getmarkers('/', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(5, count($markers));

        foreach ($markers as $id => $marker) {
            if ($marker[3] === 'testFile2.gpx') {
                // total distance
                $this->assertEquals(28034, intval($marker[4]));
            }
            if ($marker[3] === 'testFile1.gpx') {
                // total distance
                $this->assertEquals(30878, intval($marker[4]));
                // marker NSEW
                $this->assertEquals(72.858883, floatval($marker[17]));
                $this->assertEquals(2.858883, floatval($marker[18]));
                $this->assertEquals(70.104960, floatval($marker[19]));
                $this->assertEquals(0.104960, floatval($marker[20]));
            }
        }

        $resp = $this->pageController->getmarkers('/subdir', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(2, count($markers));

        foreach ($markers as $id => $marker) {
            if ($marker[3] === 'subTestFile2.gpx') {
                // total distance
                $this->assertEquals(28034, intval($marker[4]));
            }
            if ($marker[3] === 'subTestFile1.gpx') {
                // total distance
                $this->assertEquals(30878, intval($marker[4]));
            }
        }

        // test clean db from absent files
        $userfolder->get('/subdir/subTestFile2.gpx')->delete();
        $userfolder->get('/nut2.jpg')->delete();
        $userfolder->get('/nc2.jpg')->delete();
        $userfolder->get('/subdir/nut2.jpg')->delete();
        $userfolder->get('/subdir/nc2.jpg')->delete();

        $resp = $this->pageController->getmarkers('/subdir', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(1, count($markers));

        // touch files to process them again
        $userfolder->get('/testFile1.gpx')->touch();
        $userfolder->get('/nc.jpg')->touch();
        $userfolder->get('/nut.jpg')->touch();

        // recursive
        $resp = $this->pageController->getmarkers('/', 'false', '1');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(7, count($markers));
        $pics = \json_decode($data['pictures'], true);
        $this->assertEquals(2, count($pics));

        // not recursive
        $resp = $this->pageController->getmarkers('/', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(5, count($markers));
        $pics = \json_decode($data['pictures'], true);
        $this->assertEquals(1, count($pics));

        // test index
        $resp = $this->pageController->index();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);

        $resp = $this->utilsController->cleanDB();
        $oldPath = \getenv('PATH');
        //\setenv('PATH', '');

        $resp = $this->pageController->addDirectory('/convertion');
        $resp = $this->pageController->getmarkers('/convertion', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(1, count($markers));
        $pics = \json_decode($data['pictures'], true);
        $this->assertEquals(0, count($pics));

        //\setenv('PATH', $oldPath);

        // delete tracks
        $this->assertEquals(true, $userfolder->nodeExists('/testFile1.gpx'));
        $resp = $this->pageController->deleteTracks(['/testFile1.gpx', '/doesNotExist.gpx']);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('/testFile1.gpx', $data['deleted']);
        $this->assertEquals('/doesNotExist.gpx', $data['notdeleted']);
        $this->assertEquals(false, $userfolder->nodeExists('/testFile1.gpx'));

        // delete directories
        $resp = $this->pageController->delDirectory('/');
        $resp = $this->pageController->delDirectory('/subdir');
    }

}
