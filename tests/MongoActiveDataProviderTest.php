<?php

require_once 'bootstrap.php';

class MongoActiveDataProviderTest extends CTestCase
{
	public function tearDown()
	{
		// drop the database after every test
		Yii::app()->mongodb->drop();
	}

	/**
	 * I am only testing my public API, not that of the CActiveDataProvider in general
	 * @covers EMongoDataProvider
	 */
	public function testFetchData()
	{
		for($i=0;$i<=4;$i++){
			$u = new User();
			$u->username = 'sammaye';
			$u->save();
		}

		$d = new EMongoDataProvider('User', array(
			'criteria' => array(
				'condition' => array('username' => 'sammaye'),
				'sort' => array('username' => -1),
			)
		));

		$this->assertTrue($d->getTotalItemCount() == 5);
		$data = $d->fetchData();
		$this->assertTrue($d->getTotalItemCount() == 5);

		// default page size is ten which means the skip and limit become useless atm
		// However that does not matter because there is only 5 there lol
		$this->assertTrue(sizeof($data) == 5);
		$this->assertContainsOnlyInstancesOf('User', $data);
	}
}