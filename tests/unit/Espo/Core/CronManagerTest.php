<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace tests\unit\Espo\Core;

use tests\unit\ReflectionHelper;

class CronManagerTest extends \PHPUnit\Framework\TestCase
{
    protected $object;

    protected $objects;

    protected $filesPath= 'tests/unit/testData/EntryPoints';

    protected function setUp() : void
    {
        $this->objects['container'] = $this->getMockBuilder('\\Espo\\Core\\Container')->disableOriginalConstructor()->getMock();

        $this->objects['serviceFactory'] = $this->getMockBuilder('\\Espo\\Core\\ServiceFactory')->disableOriginalConstructor()->getMock();
        $this->objects['config'] = $this->getMockBuilder('\\Espo\\Core\\Utils\\Config')->disableOriginalConstructor()->getMock();
        $this->objects['fileManager'] = $this->getMockBuilder('\\Espo\\Core\\Utils\\File\\Manager')->disableOriginalConstructor()->getMock();
        $this->objects['scheduledJob'] = $this->getMockBuilder('\\Espo\\Core\\Utils\\ScheduledJob')->disableOriginalConstructor()->getMock();
        $this->objects['entityManager'] = $this->getMockBuilder('\\Espo\\Core\\ORM\\EntityManager')->disableOriginalConstructor()->getMock();

        $map = array(
          array('config', $this->objects['config']),
          array('fileManager', $this->objects['fileManager']),
          array('serviceFactory', $this->objects['serviceFactory']),
          array('entityManager', $this->objects['entityManager']),
          array('scheduledJob', $this->objects['scheduledJob']),
        );

        $this->objects['container']
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($map));

        $this->object = new \Espo\Core\CronManager($this->objects['container'] );

        $this->reflection = new ReflectionHelper($this->object);
    }

    protected function tearDown() : void
    {
        $this->object = null;
    }

    public function testConstructorWithJobsRunningInParallel()
    {
        $this->objects['config']
            ->expects($this->once())
            ->method('get')
            ->with('jobRunInParallel')
            ->will($this->returnValue(true));

        $jobRunInParallelCronManager = new \Espo\Core\CronManager($this->objects['container']);
    }

    public function testCheckLastRunTimeFileDoesnotExist()
    {
        $this->objects['fileManager']
            ->expects($this->once())
            ->method('getPhpContents')
            ->will($this->returnValue(false));

        $this->objects['config']
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue(50));

        $this->assertTrue($this->reflection->invokeMethod('checkLastRunTime', array()));
    }

    public function testCheckLastRunTime()
    {
        $this->objects['fileManager']
            ->expects($this->once())
            ->method('getPhpContents')
            ->will($this->returnValue(array(
                    'time' => time() - 60,
            )));

        $this->objects['config']
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue(50));

        $this->assertTrue($this->reflection->invokeMethod('checkLastRunTime', array()));
    }

    public function testCheckLastRunTimeTooFrequency()
    {
        $this->objects['fileManager']
            ->expects($this->once())
            ->method('getPhpContents')
            ->will($this->returnValue(array(
                    'time' => time() - 49,
            )));

        $this->objects['config']
            ->expects($this->exactly(1))
            ->method('get')
            ->will($this->returnValue(50));

        $this->assertFalse($this->reflection->invokeMethod('checkLastRunTime', array()));
    }

    public function testGetContainer()
    {
        $this->assertInstanceOf(\Espo\Core\Container::class, $this->reflection->invokeMethod("getContainer"));
    }

    public function testGetEntityManager()
    {
        $this->assertInstanceOf(\Espo\Orm\EntityManager::class, $this->reflection->invokeMethod("getEntityManager"));
    }

    public function testGetServiceFactory()
    {
        $this->assertInstanceOf(\Espo\Core\ServiceFactory::class, $this->reflection->invokeMethod("getServiceFactory"));
    }

    public function testGetScheduledJobUtil()
    {
        $this->assertInstanceOf(\Espo\Core\Utils\ScheduledJob::class, $this->reflection->invokeMethod("getScheduledJobUtil"));
    }

    public function testGetCronJobUtil()
    {
        $this->assertInstanceOf(\Espo\Core\Utils\Cron\Job::class, $this->reflection->invokeMethod("getCronJobUtil"));
    }

    public function testGetCronScheduledJobUtil()
    {
        $this->assertInstanceOf(\Espo\Core\Utils\Cron\ScheduledJob::class, $this->reflection->invokeMethod("getCronScheduledJobUtil"));
    }

    public function testSetLastRunTime()
    {
        $time = time();
        $this->objects['fileManager']->expects($this->once())->method("putPhpContents")->willReturn(4);
        $this->assertEquals(4, $this->reflection->invokeMethod('setLastRunTime', [$time]));
    }

    public function testUseProcessPool()
    {
        $this->assertFalse($this->reflection->invokeMethod("useProcessPool"));
        
        $this->object->setUseProcessPool(true);
        $this->assertTrue($this->reflection->invokeMethod("useProcessPool"));
    }

    public function testRunJobByIdWhenIdIsNotGiven()
    {
        $this->expectException(\Espo\Core\Exceptions\Error::class);
        $this->object->runJobById("");
    }

    public function testRunJobByIdWhenEntityIsNotFound()
    {
        $this->expectException(\Espo\Core\Exceptions\Error::class);
        $this->expectExceptionMessage("Job 1 not found.");
        $this->objects['entityManager']->expects($this->once())->method('getEntity')->with('Job', 1)->willReturn(null);

        $this->object->runJobById(1);
    }

    public function testRunJobByIdWhenJobEntityIsNotReady()
    {
        $this->expectException(\Espo\Core\Exceptions\Error::class);
        $this->expectExceptionMessage("Can't run job 1 with no status Ready.");

        $job = new \Espo\Entities\Job;
        $this->objects['entityManager']->expects($this->once())->method('getEntity')->with('Job', 1)->willReturn($job);

        $this->object->runJobById(1);
    }
}
