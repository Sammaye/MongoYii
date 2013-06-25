<?php
require_once 'bootstrap.php';

class MongofileTest extends CTestCase{
	
	function tearDown(){
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
	
	function testAddingFile(){
		// Hmm this is blank until I can figure out how best to unit test an upload
	}
	
	function testFndingFile(){
		
	}
	
	function testDeletingFile(){
		
	}
}