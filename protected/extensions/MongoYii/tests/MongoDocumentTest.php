<?php
class MongoDocumentTest extends CTestCase{
	
	function setUp(){
		parent::setUp();
	}
	
	function setUpRelationalModel(){
		$parentDocs = array(
			array('username' => 'sam', 'job_title' => 'awesome guy'),
			array('username' => 'john', 'job_title' => 'co-awesome guy'),
			array('username' => 'dan', 'job_title' => 'programmer'),
			array('username' => 'lewis', 'job_title' => 'programmer'),
			array('username' => 'ant', 'job_title' => 'programmer')
		);
		
		$childDocs = array(
			array('name' => 'jogging'),
			array('name' => 'computers'),
			array('name' => 'biking'),
			array('name' => 'drinking'),
			array('name' => 'partying'),
			array('name' => 'cars')
		);
		
		// Lets save all the child docs
		foreach($childDocs as $doc){
			$i = Interest::model();
			$i->setAttributes($doc);
			$this->assertTrue($i->save());			
		}
		
		// Lets make sure those child docs actually went in
		$c=Interest::model()->find();
		$this->assertTrue($c->count()>0);
		
		// Let's build an array of the all the _ids of the child docs
		$interest_ids = array();
		foreach($c as $row){
			$interest_ids[] = $row->_id;
		}

		// Create the users with each doc having the value of the interest ids
		$user_ids = array();
		foreach($parentDocs as $doc){
			$u = User::model();
			$u->setAttributes($doc);
			$u->interests = $interest_ids;
			$this->assertTrue($u->save());
			
			$user_ids[] = $u->_id;
		}
		
		// Now 50^6 times re-insert each interest with a parnt user _id
		// So we have two forms of the document in interests, one without the parent user and one with
		for($i=0;$i<50;$i++){
			foreach($c as $row){
				$randPos = rand(0, sizeof($user_ids));
				$row->i_id = $user_ids[$randPos];
				
				$row->setIsNewRecord(true);
				$row->_id = null;
				$row->setScenario('insert');
				
				$this->assertTrue($row->save());
			}	
		}
		
		// we will assume the set up was successful and we will leave it to further testing to see 
		// whether it really was.
	}
	
	function tearDown(){
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
	
	
	function testModel(){
		$c=User::model();
		$this->assertInstanceOf('EMongoDocument', $c);
	}	
	
	function testSaving(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());
		
		$r=User::model()->findOne();
		$this->assertTrue($r->count()>0);
		
		foreach($r as $doc){
			$doc->username="dan";
			$this->assertTrue($doc->save());			
		}
	}
	
	function testDeleting(){
		
	}
	
	function testFindOne(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());
		
		$r=User::model()->findOne();
		$this->assertTrue($r->count()>0);		
	}
	
	function testFindBy_id(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());
		
		$r=User::model()->findBy_id($c->_id);
		$this->assertTrue($r->count()>0);	

		$r=User::model()->findBy_id((string)$c->_id);
		$this->assertTrue($r->count()>0);		
	}
	
	function testUpdateByPk(){
		
	}
	
	function testDeleteByPk(){
		
	}

	function testUpdateAll(){
	
	}	
	
	function testDeleteAll(){
		
	}
	
	function testSaveAttributes(){
		
	}
	
	function testOneRelation(){
		
	}
	
	function testManyRelation(){
		
	}
	
	function testBehaviour(){
		
	}
	
	function testUniqueValidator(){
		
	}
	
	function testSubdocumentValidator(){
		
	}
	
	function testExists(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());		
		$this->assertTrue(User::model()->exists(array('name' => 'sammaye')));
	}
	
	function testEquals(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());	
		
		$d=User::model()->findOne(array('name' => 'sammaye'));
		$this->assertTrue($c->equals($d));
	}
	
	function testScopes(){
		
	}
	
	function testClean_Refresh(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$this->assertTrue($c->clean());
		$this->assertNull($c->username);
		
		$r=User::model()->findOne();
		$this->assertTrue($r->count()>0);
		$this->assertInstanceOf('EMongoDocument',$r);
		
		$r->username = 'fgfgfg';
		$r->refresh();
		$this->assertEquals('sammaye', $r->username);
	}
	
	function testGetAttributeLabel(){
		$c=User::model();
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$this->assertEquals('name', $r->getAttributeLabel('username'));
	}
}