<?php
class Account extends EMongoModel{

	public $bank;
	public $swiftCode;

	function rules(){
		return array();
	}

	public function subDocuments(){
		return array('dummies'=>array('Dummy'));
	}

}