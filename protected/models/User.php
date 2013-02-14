<?php

class User extends EMongoDocument{

	//public $_id;

	public $username;
	public $addresses = array();

	function rules(){
		return array(
//			array('addresses', 'subdocument', 'type' => 'many', 'rules' => array(
//				array('road', 'string'),
//				array('town', 'string'),
//				array('county', 'string'),
//				array('post_code', 'string'),
//				array('telephone', 'integer')
//			)),

			array('_id, username, addresses', 'safe', 'on'=>'search'),
		);
	}

	function collectionName(){
		return 'users';
	}


	function relations(){
		return array(
			'others' => array('many', 'Other', 'otherId')
		);
	}

	/**
	 * Returns the static model of the specified AR class.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}