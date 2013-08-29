<?php
require_once 'bootstrap.php';

class MongoSubdocumentValidatorTest extends CTestCase{

	function setUp(){
		parent::setUp();
	}

	function tearDown(){
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}

	function userDataProvider() {
		return array(
			array(
				array(
					'username'=>'kate',
					'addresses'=>array(
						0 => array(
							'country' => 'Ukraine',
							'telephone' => 11111
						),
						1 => array(
							'country' => 'Russia',
							'telephone' => 'wrongString'
						)
					)
				)
			)
		);
	}

	/**
	 * @dataProvider userDataProvider
	 */
	function testProperErrorsIndexation($post) {
		$c=new User;
		$c->attributes = $post;
		$this->assertFalse($c->validate());
		$errors = $c->errors;
		$this->assertNotNull($errors['addresses'][1]['telephone']);
	}
}