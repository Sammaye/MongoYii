<?php

require_once 'bootstrap.php';

class MongoClientTest extends CTestCase{

	/**
	 * @covers MongoClient::getConnection
	 */
	function testSettingUpConnection(){

		$mongo = Yii::app()->mongodb;

		$this->assertInstanceOf('EMongoClient', $mongo);

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertInstanceOf('Mongo', $mongo->getConnection());
		}else{
			$this->assertInstanceOf('MongoClient', $mongo->getConnection());
		}
	}

	/**
	 * @covers MongoClient::selectCollection
	 */
	function testSelectCollection(){

		$mongo = Yii::app()->mongodb;

		$this->assertTrue($mongo->new_collection instanceof MongoCollection);
		$this->assertInstanceOf('MongoCollection', $mongo->new_collection);
		$this->assertInstanceOf('MongoCollection', $mongo->selectCollection('new_collection'));
	}

	/**
	 * @covers MongoClient::getDB
	 */
	function testGetDB(){
		$mongo = Yii::app()->mongodb;
		$this->assertInstanceOf('MongoDB', $mongo->getDB());
	}

	/**
	 * @covers MongoClient::getDefaultWriteConcern
	 */
	function testWriteConcern(){
		$mongo = Yii::app()->mongodb;

		$w = $mongo->getDefaultWriteConcern();

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertTrue(isset($w['safe']));
		}else{
			$this->assertTrue(isset($w['w'],$w['j']));
		}

		$mongo->w = 1;
		$mongo->j = true;

		$w = null;
		$w = $mongo->getDefaultWriteConcern();

		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->assertTrue($w['safe']===true);
		}else{
			$this->assertTrue($w['w']==1 && $w['j']===true);
		}
	}

	/**
	 * @covers MongoClient::createMongoIdFromTimestamp
	 */
	function testCreateMongoIDFromTimestamp(){
		$mongo = Yii::app()->mongodb;
		$id = $mongo->createMongoIdFromTimestamp(time());
		$this->assertTrue($id instanceof MongoId);
	}

	function testArrayMerging(){
		$a = CMap::mergeArray(array('a' => 1, 'b' => array('c' => 2)), array('a' => 1, 'b' => array('c' => 2, 'd' => 3)));
		$this->assertTrue(isset($a['a'], $a['b'], $a['b']['c'], $a['b']['d']));
	}
}