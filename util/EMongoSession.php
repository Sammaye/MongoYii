<?php

/**
 * EMongoSession extends {@link CHttpSession} by using database as session data storage.
 *
 * EMongoSession stores session data in a DB collection named 'YiiSession'. The collection name
 * can be changed by setting {@link sessionTableName}.
 *
 * @property boolean $useCustomStorage Whether to use custom storage.
 */
class EMongoSession extends CHttpSession
{
	/**
	 * @var string the ID of a {@link CDbConnection} application component.
	 */
	public $connectionID;
	
	/**
	 * @var string the name of the DB table to store session content.
	 */
	public $sessionTableName = 'YiiSession';
	
	/**
	 * @var EMongoClient the DB connection instance
	 */
	private $_db;

	/**
	 * Returns a value indicating whether to use custom session storage.
	 * This method overrides the parent implementation and always returns true.
	 * @return boolean whether to use custom storage.
	 */
	public function getUseCustomStorage()
	{
		return true;
	}

	/**
	 * Updates the current session id with a newly generated one.
	 * Please refer to {@link http://php.net/session_regenerate_id} for more details.
	 * @param boolean $deleteOldSession Whether to delete the old associated session file or not.
	 * @since 1.1.8
	 */
	public function regenerateID($deleteOldSession = false)
	{
		$oldID=session_id();

		// if no session is started, there is nothing to regenerate
		if(empty($oldID)){
			return;
		}
		
		parent::regenerateID(false);
		$newID = session_id();
		$db = $this->getDbConnection();

		$row = $db->{$this->sessionTableName}->findOne(array('id' => $oldID));
		if($row){ // $row should either be a truey value or a falsey value
			if($deleteOldSession){
				$db->{$this->sessionTableName}->update(array('id' => $oldID), array('$set' => array('id' => $newID)));
			}else{
				unset($row['_id']);
				$row['id'] = $newID;
				$db->{$this->sessionTableName}->insert($row);
			}
		}else{
			// shouldn't reach here normally
			$db->{$this->sessionTableName}->insert(array(
				'id' => $newID,
				'expire' => time() + $this->getTimeout()
			));
		}
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
			if(($this->_db = Yii::app()->getComponent($id)) instanceof EMongoClient){
				return $this->_db;
			}else{
				throw new CException(
					Yii::t(
						'yii', 
						'EMongoSession.connectionID "{id}" is invalid. Please make sure it refers to the ID of a EMongoClient application component.',
						array('{id}' => $id)
					)
				);
			}
		}else{
			return $this->_db = Yii::app()->getComponent('mongodb');
		}
	}

	/**
	 * Session open handler.
	 * Do not call this method directly.
	 * @param string $savePath session save path
	 * @param string $sessionName session name
	 * @return boolean whether session is opened successfully
	 */
	public function openSession($savePath, $sessionName)
	{
		return true; // Do not need to explicitly create tables in MongoDB
	}

	/**
	 * Session read handler.
	 * Do not call this method directly.
	 * @param string $id session ID
	 * @return string the session data
	 */
	public function readSession($id)
	{
		$data = $this->getDbConnection()->{$this->sessionTableName}->findOne(array(
			'expire' => array('$gt' => time()),
			'id' => $id
		));
		return $data === null ? '' : $data['data'];
	}

	/**
	 * Session write handler.
	 * Do not call this method directly.
	 * @param string $id session ID
	 * @param string $data session data
	 * @return boolean whether session write is successful
	 */
	public function writeSession($id, $data)
	{
		// exception must be caught in session write handler
		// http://us.php.net/manual/en/function.session-set-save-handler.php
		try{
			$expire = time() + $this->getTimeout();
			$db = $this->getDbConnection();
			$res = $db->{$this->sessionTableName}->update(array('id' => $id), array('$set' => array(
				'data' => $data,
				'expire' => $expire
			)), array('upsert' => true));
			return ((isset($res['upserted']) && $res['upserted']) || (isset($res['updatedExisting']) && $res['updatedExisting']));
		}catch(Exception $e){
			if(YII_DEBUG){
				echo $e->getMessage();
			}
			// it is too late to log an error message here
			return false;
		}
		return true;
	}

	/**
	 * Session destroy handler.
	 * Do not call this method directly.
	 * @param string $id session ID
	 * @return boolean whether session is destroyed successfully
	 */
	public function destroySession($id)
	{
		$this->getDbConnection()->{$this->sessionTableName}->remove(array('id' => $id));
		return true;
	}

	/**
	 * Session GC (garbage collection) handler.
	 * Do not call this method directly.
	 * @param integer $maxLifetime the number of seconds after which data will be seen as 'garbage' and cleaned up.
	 * @return boolean whether session is GCed successfully
	 */
	public function gcSession($maxLifetime)
	{
		$this->getDbConnection()->{$this->sessionTableName}->remove(array('expire' => array('$lt' => time())));
		return true;
	}
}