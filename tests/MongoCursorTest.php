<?php

require_once 'bootstrap.php';

class MongoCursorTest extends CTestCase{

	function testFind(){

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

	function testDirectInstantiation(){

		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = new EMongoCursor('User', array('username' => 'sammaye'));

		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count() > 0);
	}

	function testEMongoCriteria(){

		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$criteria = new EMongoCriteria(array('condition' => array('username' => 'sammaye'), 'limit' => 3, 'skip' => 1));
		$c = new EMongoCursor('User', $criteria);
		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count() > 0);

	}

	function testSkip_Limit(){
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$c = User::model()->find()->skip(1)->limit(3);

		$this->assertInstanceOf('EMongoCursor', $c);
		$this->assertTrue($c->count(true) == 3);
	}
}