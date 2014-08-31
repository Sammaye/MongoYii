<?php
/**
 * CDbMessageSource class file.
 *
 * @author Zoltan Rajcsanyi <rajcsanyiz@gmail.com>
 * @copyright 2013 Zoltan Rajcsanyi
 * @license New BSD License
 * @category Database
 * @version 1.0
 */

/**
 * EMongoMessageSource represents a message source that stores translated messages in MongoDB.
 *
 * PHP version 5.2+
 * MongoDB version >= 1.5.3
 * required extensions: MongoYii (for the configuration of the mongoDB connection)*
 *
 * The YiiMessages collection contains the following schema:
 * <pre>
 *   _id: mongoId(),
 *   category: string,
 *   message: string,
 *   translations: [language: string, message: string]
 * </pre>
 *
 * The 'YiiMessages' collection can be customized by setting {@link collectionName}.
 *
 * When {@link cachingDuration} is set as a positive number, message translations will be cached.
 *
 * @property EMongoConnection $emongoConnection The DB connection used for the message source.
 *
 * @author Zoltan Rajcsanyi <rajcsanyiz@gmail.com>
 * @copyright 2013 Zoltan Rajcsanyi
 * @license New BSD License
 * @category Database
 * @version 1.0
 */
class EMongoMessageSource extends CMessageSource
{
	const CACHE_KEY_PREFIX = 'Yii.EMongoMessageSource.';

	public $connectionID;

	/**
	 * @var string name of collection to store messages and translations
	 */
	public $collectionName = 'YiiMessages';

	/**
	 * @var integer the time in seconds that the messages can remain valid in cache.
	 * Defaults to 0, meaning the caching is disabled.
	 */
	public $cachingDuration = 0;
	
	/**
	 * @var string the ID of the cache application component that is used to cache the messages.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable caching the messages.
	 */
	public $cacheID = 'cache';

	/**
	 * @var EMongoClient the DB connection instance
	 */
	private $_db;

	/**
	 * Loads the message translation for the specified language and category.
	 * @param string $category the message category
	 * @param string $language the target language
	 * @return array the loaded messages
	 */
	protected function loadMessages($category,$language)
	{
		if($this->cachingDuration > 0 && $this->cacheID !== false && ($cache = Yii::app()->getComponent($this->cacheID)) !== null){
			$key = self::CACHE_KEY_PREFIX . '.messages.' . $category . '.' . $language;
			if(($data = $cache->get($key)) !== false){
				return unserialize($data);
			}
		}

		$messages = $this->loadMessagesFromDb($category, $language);

		if(isset($cache)){
			$cache->set($key, serialize($messages), $this->cachingDuration);
		}
		return $messages;
	}
	 
	/**
	 * @return EMongoClient the DB connection instance
	 * @throws CException if {@link connectionID} does not point to a valid application component.
	 */
	protected function getDbConnection()
	{
		if($this->_db !== null){
			return $this->_db;
		}elseif(($id = $this->connectionID) !== null){
			if(($this->_db = Yii::app()->getComponent($id)) instanceof CDbConnection){
				return $this->_db;
			}else{
				throw new CException(
					Yii::t(
						'yii', 
						'EMongoCache.connectionID "{id}" is invalid. Please make sure it refers to the ID of a EMongoClient application component.',
						array('{id}' => $id)
					)
				);
			}
		}else{
			return $this->_db = Yii::app()->getComponent('mongodb');
		}
	}

	/**
	 * Returns current MongoCollection object
	 *
	 * @return MongoCollection
	 */
	protected function getCollection()
	{
		return $this->getDbConnection()->{$this->collectionName};
	}

	/**
	 * Loads the messages from database.
	 * You may override this method to customize the message storage in the database.
	 * @param string $category the message category
	 * @param string $language the target language
	 * @return array the messages loaded from database
	 * @since 1.1.5
	 */
	protected function loadMessagesFromDb($category,$language)
	{
		$criteria = array('category' => $category, "translations.language" => $language);
		$fields = array('message' => true, 'translations.message' => true);
		$messages = $this->getCollection()->find($criteria, $fields);
		 
		$result = array();
		foreach($messages as $message){
			$result[$message['message']] = $message['translations'][0]['message'];
		}
		return $result;
	}
}