<?php
/**
 * EMongoCacheDependency represents a dependency based on the query result of a Mongo Query.
 *
 * If the query result (a scalar) changes, the dependency is considered as changed.
 * To specify the Mongo Cursor, set {@link cursor} property.
 */
class EMongoCacheDependency extends CCacheDependency
{
	/**
	 * @var string the ID of a {@link EMongoClient} application component. Defaults to 'mongodb'.
	 */
	public $connectionID = 'mongodb';
	
	public $collection = null;
	
	public $query = array();
	
	private $_db;

	/**
	 * Constructor.
	 * @param string $cursor the Mongo Cursor whose result is used to determine if the dependency has been changed.
	 */
	public function __construct($collection=null, $query = null)
	{
		$this->collection = $collection;
		$this->query = $query;
	}

	/**
	 * PHP sleep magic method.
	 * This method ensures that the database instance is set null because it contains resource handles.
	 * @return array
	 */
	public function __sleep()
	{
		$this->_db = null;
		return array_keys((array)$this);
	}

	/**
	 * Generates the data needed to determine if dependency has been changed.
	 * This method returns the value of the global state.
	 * @throws CException if {@link cursor} is empty
	 * @return mixed the data needed to determine if dependency has been changed.
	 */
	protected function generateDependentData()
	{
		if($this->query !== null){
			
			$db = $this->getDbConnection();
			
			if($db->queryCachingDuration > 0){
				// temporarily disable and re-enable query caching
				$duration=$db->queryCachingDuration;
				$db->queryCachingDuration = 0;
				$result = iterator_to_array($this->createCursor());
				$db->queryCachingDuration = $duration;
			}else{
				$result = iterator_to_array($this->createCursor());
			}
			return $result;
		}else{
			throw new EMongoException(Yii::t('yii','EMongoCacheDependency.query cannot be empty.'));
		}
	}
	
	protected function createCursor()
	{
		$query = array();
		if(isset($this->query[0])){
			$query = $this->query[0];
		}
		if (empty($this->collection)) {
			throw new EMongoException(Yii::t('yii','EMongoCacheDependency.collection cannot be empty.'));
		}
		$cursor = $this->getDbConnection()->{$this->collection}->find($query);
		
		if(isset($this->query['sort'])){
			$cursor->sort($this->query['sort']);
		}
		
		if(isset($this->query['skip'])){
			$cursor->limit($this->query['skip']);
		}		
		
		if(isset($this->query['limit'])){
			$cursor->limit($this->query['limit']);
		}
		
		return $cursor;
	}
	
	/**
	 * @return CDbConnection the DB connection instance
	 * @throws CException if {@link connectionID} does not point to a valid application component.
	 */
	protected function getDbConnection()
	{
		if($this->_db!==null){
			return $this->_db;
		}else{
			if(($this->_db=Yii::app()->getComponent($this->connectionID)) instanceof EMongoClient){
				return $this->_db;
			}else{
				throw new EMongoException(
					Yii::t(
						'yii', 
						'EMongoCacheDependency.connectionID "{id}" is invalid. Please make sure it refers to the ID of a EMongoClient application component.',
						array('{id}' => $this->connectionID)
					)
				);
			}
		}
	}
}
