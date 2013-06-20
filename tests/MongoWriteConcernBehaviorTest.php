<?php

require_once 'bootstrap.php';

class MongoWriteConcernBehaviorTest extends CTestCase{

	function testAll(){
		/** @var EMongoClient $db  */
		$db = Yii::app()->mongodb;
		$db->setWriteConcernAlias('default');

		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$db->setWriteConcernAlias('logs');
		$this->assertEquals(0, $db->w);
		$this->assertFalse($db->j);

		$db->setWriteConcernAlias('default');
		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$db->pushWriteConcernAlias('files');
		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$db->pushWriteConcernAlias('critical');
		$this->assertEquals(1, $db->w);
		$this->assertTrue($db->j);

		$db->popWriteConcernAlias();	// back to 'files'
		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$db->popWriteConcernAlias();	// back to 'default'
		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$db->popWriteConcernAlias();	// do nothing
		$this->assertEquals(1, $db->w);
		$this->assertFalse($db->j);

		$b = $db->asa('writeConcern');
		$db->setWriteConcernAlias('critical');
		$this->assertEquals(1, $db->w);
		$this->assertTrue($db->j);


	}
}