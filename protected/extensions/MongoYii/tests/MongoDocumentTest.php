<?php
class MongoDocumentTest extends CTestCase{
	
	function setUp(){
		parent::setUp();
	}
	
	function setUpRelationalModel(){
		$parentDocs = array(
			array('username' => 'sam', 'job_title' => 'awesome guy', 'interests' => array()),
			array('username' => 'john', 'job_title' => 'co-awesome guy', 'interests' => array()),
			array('username' => 'dan', 'job_title' => 'programmer', 'interests' => array()),
			array('username' => 'lewis', 'job_title' => 'programmer', 'interests' => array()),
			array('username' => 'ant', 'job_title' => 'programmer', 'interests' => array())
		);
		
		$childDocs = array(
			array('name' => 'jogging'),
			array('name' => 'computers'),
			array('name' => 'biking'),
			array('name' => 'drinking'),
			array('name' => 'partying'),
			array('name' => 'cars')
		);
		
		foreach($childDocs as $doc){
			$u = Interest::model();
			$u->setAttributes($doc);
			$this->assertTrue($u->save());			
		}

		foreach($parentDocs as $doc){
			$u = User::model();
			$u->setAttributes($doc);
			$this->assertTrue($u->save());
		}
	}
	
	function tearDown(){
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
	
	
	function testModel(){
	
	}	
	
	function testSaving(){
		
	}
	
	function testUpdating(){
		
	}
	
	function tstDeleting(){
		
	}
	
	function testFindOne(){
		
	}
	
	function testFindBy_id(){
		
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
		
	}
	
	function testScopes(){
		
	}
	
	function testClean_Refresh(){
		
	}
	
	function testGetAttributeLabel(){
		
	}
}