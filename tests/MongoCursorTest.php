<?php

require_once 'bootstrap.php';

class MongoCursorTest extends CTestCase
{
	public function testFind()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = User::model()->find();

		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count() > 0);

		foreach($c as $doc){
			$this->assertTrue($doc instanceof EMongoDocument);
			$this->assertEquals('update', $doc->getScenario());
			$this->assertFalse($doc->getIsNewRecord());
			$this->assertInstanceOf('MongoId', $doc->_id);
			break;
		}
	}

	/**
	 * @covers EMongoCursor::__construct
	 */
	public function testDirectInstantiation()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = new EMongoCursor('User', array('username' => 'sammaye'));

		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count() > 0);
	}

	/**
	 * @covers EMongoCriteria
	 */
	public function testEMongoCriteria()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$criteria = new EMongoCriteria(array('condition' => array('username' => 'sammaye'), 'limit' => 3, 'skip' => 1));
		$c = new EMongoCursor('User', $criteria);
		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count() > 0);
		// see also $this->testSkipLimit()
		$this->assertEquals(3, $c->count(true));

	}

	public function testSkipLimit()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = User::model()->find()->skip(1)->limit(3);

		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count(true) == 3);
	}

	public function tearDown()
	{
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
}