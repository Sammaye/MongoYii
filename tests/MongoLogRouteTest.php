<?php
require_once 'bootstrap.php';

class MongoLogRouteTest extends CTestCase
{
	public function testLogRouteGetCollection()
	{
		$router = new EMongoLogRoute();
		$router->connectionId = 'mongodb_neu';
		$this->assertEquals('mongodb_neu', $router->connectionId);

		$router->logCollectionName = 'yii_mongo_log';
		$this->assertEquals('yii_mongo_log', $router->logCollectionName);

		// set back again
		$router->connectionId = 'mongodb';

		$collection = $router->getMongoConnection();
		$this->assertInstanceOf('MongoCollection', $collection);
	}

	public function testInsertIntoLog()
	{
		$router = new EMongoLogRoute();
		$logs = array(
			array('message1', 'level1', 'category1', microtime(true)),
			array('message2', 'level2', 'category2', microtime(true)),
			array('message3', 'level3', 'category3', microtime(true)),
		);

		$router->processLogs($logs);
		$collection = $router->getMongoConnection();

		foreach ($logs as $log) {
			$this->assertNull($collection->findOne(array('message' => 'IAmNotThere')));
			$this->assertArrayHasKey('message', $collection->findOne(array('message' => $log[0])));
			$this->assertArrayHasKey('level', $collection->findOne(array('level' => $log[1])));
			$this->assertArrayHasKey('category', $collection->findOne(array('category' => $log[2])));
		}
	}

	public function tearDown()
	{
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
}