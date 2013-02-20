<?php

require_once 'bootstrap.php';

class MongoActiveDataProviderTest extends CTestCase{

	/**
	 * I am only testing my public API, not that of the CActiveDataProvider in general
	 */
	function testFetchData(){

		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$d = new EMongoDataProvider('User', array(
			'criteria' => array(
				'condition' => array('name' => 'sammaye'),
				'sort' => array('name' => '-1'),
				'skip' => 1,
				'limit' => 3
			)
		));
		$data = $d->fetchData();

		$this->assertTrue(sizeof($data) == 3);
		$this->assertContainsOnlyInstancesOf('User', $data);
	}
}