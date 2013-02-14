<?php
class Other extends EMongoDocument{
	//public $_id;
	public $otherId;

	public $username;

	function rules(){
		return array(
//			array('addresses', 'subdocument', 'type' => 'many', 'rules' => array(
//				array('road', 'string'),
//				array('town', 'string'),
//				array('county', 'string'),
//				array('post_code', 'string'),
//				array('telephone', 'integer')
//			)),
			array('_id, otherId, username', 'safe', 'on'=>'search'),
		);
	}

	function collectionName(){
		return 'others';
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