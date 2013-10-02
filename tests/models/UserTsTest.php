<?php

/**
* Testing behaviors/EMongoTimestampBehaviour
*/
class UserTsTest extends EMongoDocument {
	
	public $username;

	function behaviors(){
		return array(
			'EMongoTimestampBehaviour' => array(
	  			'class' => 'EMongoTimestampBehaviour',
	           	'onScenario' => array('testMe'),
  		)
		);
	}

	function collectionName(){
		return 'users';
	}

}

/**
* Testing behaviors/EMongoTimestampBehaviour whereas here its broken
*/
class UserTsTestBroken extends EMongoDocument {
	
	public $username;

	function behaviors(){
		return array(
			'EMongoTimestampBehaviour' => array(
	  			'class' => 'EMongoTimestampBehaviour',
	           	'onScenario' => 'testMeFalse',
  		)
		);
	}

	function collectionName(){
		return 'users';
	}

}

/**
* Testing behaviors/EMongoTimestampBehaviour whereas here its broken.
* This time onScenario and notOnScenario are defined
*/
class UserTsTestBroken2 extends EMongoDocument {
	
	public $username;

	function behaviors(){
		return array(
			'EMongoTimestampBehaviour' => array(
	  			'class' => 'EMongoTimestampBehaviour',
	           	'onScenario' => array('testMeFalseOn'),
	           	'notOnScenario' => array('testMeFalseOn'),
  		)
		);
	}

	function collectionName(){
		return 'users';
	}

}

?>