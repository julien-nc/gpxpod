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

        // create files
        $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');
        $content1 = file_get_contents('tests/tracks/testFile1.gpx');
        $userfolder->newFile('testFile1.gpx')->putContent($content1);
        $content2 = file_get_contents('tests/tracks/testFile2.gpx');
        $userfolder->newFile('testFile2.gpx')->putContent($content2);

        // add top dir
        $resp = $this->pageController->addDirectory('/');
        $status = $resp->getStatus();
        //$this->assertEquals(200, $status);

        // get markers
        $resp = $this->pageController->getmarkers('/', 'false', '0');
        $data = $resp->getData();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $markers = \json_decode($data['markers'], true);
        $markers = $markers['markers'];
        $this->assertEquals(2, count($markers));

        foreach ($markers as $id => $marker) {
            if ($marker[3] === 'testFile2.gpx') {
                // total distance
                $this->assertEquals(28034, intval($marker[4]));
            }
            if ($marker[3] === 'testFile1.gpx') {
                // total distance
                $this->assertEquals(30878, intval($marker[4]));
            }
        }
    }

}
