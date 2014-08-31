<?php

/**
 * EMongoAuthManager
 *
 * Represents an authorization manager that stores authorization information in terms of a mongodb document.
 * The authorization hierarchy is stored in a collection named "acl" by default.
 *
 * Usage:
 *
 * Add the following to your config/main.php
 *
 * 'components' => array(
 *     ...
 *     'authManager' => array(
 *         'class' => 'EMongoAuthManager'
 *     )
 * )
 *
 */
class EMongoAuthManager extends CPhpAuthManager
{
	/**
	 *
	 * @var string the name of the mongodb collection that contains the authorization data.
	 * If not set, it will be using 'acl' as the collection.
	 * @see loadFromFile
	 * @see saveToFile
	 */
	public $collectionName = 'acl';
	
	/**
	 *
	 * @var string the connectionId of the EMongoClient component
	 */
	public $connectionId = 'mongodb';
	
	/**
	 *
	 * @var string the MongoId of the current auth record
	 */
	private $_id;
	
	/**
	 * Get a MongoCollection object
	 * 
	 * @return Instance of MongoCollection
	 */
	public function getMongoConnection($collection = null)
	{
		return Yii::app()->{$this->connectionId}->{$collection === null ? $this->collectionName : $collection};
	}
	
	/**
	 * Initializes the application component.
	 * This method overrides parent implementation by loading the authorization data
	 * from mongodb collection.
	 */
	public function init()
	{
		// For compatibility reasons, collection name must be stored in "authFile"
		$this->authFile = $this->collectionName;
		$this->load();
	}
	
	/**
	 * Loads the authorization data from mongodb collection.
	 * @param string $collection the collection name.
	 * @return array the authorization data
	 * @see saveToFile
	 */
	protected function loadFromFile($collection)
	{
		$mongoCollection = $this->getMongoConnection($collection);
		$data = $mongoCollection->findOne();
		if($data === null){
			return array();
		}
		
		$this->_id = $data['_id'];
		unset($data['_id']);
		return $data;
	}
	
	/**
	 * Saves the authorization data to a mongodb collection.
	 * @param array $data the authorization data
	 * @param string $collection the collection name
	 * @see loadFromFile
	 */
	protected function saveToFile($data, $collection)
	{
		if($this->_id !== null){
			$data['_id'] = $this->_id;
		}
		$this->getMongoConnection($collection)->save($data);
	}
}