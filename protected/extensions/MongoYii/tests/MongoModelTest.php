<?php

require_once 'bootstrap.php';

/**
 * Validation is not tested here since I have not changed it so it is not part of my API.
 * Instead I will only test certain validators within the MongoDocumentTest.php file and
 * consider validation working.
 */
class MongoModelTest extends CTestCase{

	function testModelCreation(){
		$d = new Dummy();
		$this->assertInstanceOf('EMongoModel', $d);
	}

	function testMagics(){
		$d = new Dummy();
		$d->username = 'sammaye';
		$this->assertEquals('sammaye', $d->username);
		$this->assertTrue(isset($d->username));
		unset($d->username);
		$this->assertFalse(isset($d->username));
	}

	function testAttributes(){

		$d = new Dummy();
		$d->dum = 'dum-dum';
		$this->assertTrue($d->hasAttribute('dum'));

		$an = $d->attributeNames();
		$this->assertTrue(array_key_exists('dum',array_flip($an)));

		$d->username = 'sammaye';
		$attr = $d->getAttributes();
		$this->assertTrue(array_key_exists('username', $attr));
		$this->assertTrue(array_key_exists('dum', $attr));
	}

	function testGetDBConnection(){
		$d = new Dummy();
		$dbc = $d->getDbConnection();
		$this->assertInstanceOf('EMongoClient', $dbc);
	}

	function testGetDocument(){
		$d = new Dummy();
		$d->dum = 'dum-dum';
		$d->username = 'sammaye';

		$doc = $d->getDocument();
		$this->assertTrue(array_key_exists('username', $doc));
		$this->assertTrue(array_key_exists('dum', $doc));
	}

	function testGetRawDocument(){
		$d = new Dummy();
		$d->dum = 'dum-dum';
		$d->username = 'sammaye';

		$doc = $d->getRawDocument();
		$this->assertTrue(array_key_exists('username', $doc));
		$this->assertTrue(array_key_exists('dum', $doc));
	}

	function testGetJSONDocument(){
		$d = new Dummy();
		$d->dum = 'dum-dum';
		$d->username = 'sammaye';

		$doc = $d->getJSONDocument();
		$this->assertTrue(array_key_exists('username', json_decode($doc)));
		$this->assertTrue(array_key_exists('dum', json_decode($doc)));
	}

	function testGetBSONDocument(){
		$d = new Dummy();
		$d->dum = 'dum-dum';
		$d->username = 'sammaye';

		$doc = $d->getBSONDocument();
		$this->assertTrue(array_key_exists('username', bson_decode($doc)));
		$this->assertTrue(array_key_exists('dum', bson_decode($doc)));
	}
}