<?php
class versionedDocument extends EMongoDocument{
	
	function versioned(){
		return true;
	}
	
	function collectionName(){
		return 'versioned';
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