<?php

/**
 * EMongoCursor
 *
 * Represents the Yii edition to the MongoCursor and allows for lazy loading of objects.
 *
 * This class does not support eager loading by default, in order to use eager loading you should look into using this
 * classes reponse with iterator_to_array().
 *
 * I did try originally to make this into a active data provider and use this for two fold operations but the cactivedataprovider would extend
 * a lot for the cursor and the two took quite different constructors.
 */
class EMongoCursor implements Iterator, Countable{
	/**
	 * @var array|EMongoCriteria
	 */
	public $criteria = array();
	/**
	 * @var string
	 */
	public $modelClass;
	/**
	 * @var EMongoDocument
	 */
	public $model;
	/**
	 * @var array|MongoCursor|EMongoDocument[]
	 */
	private $cursor = array();
	/**
	 * @var EMongoDocument
	 */
	private $current;

	/**
	 * This denotes a partial cursor which in turn will transpose onto the active record
	 * to state a partial document. If any projection is supplied this will result in true since
	 * I cannot detect if you are projecting the whole document or not...THERE IS NO PRE-DEFINED SCHEMA
	 * @var boolean
	 */
	private $partial = false;

	/**
	 * The cursor constructor
	 * @param string|EMongoDocument $modelClass - The class name for the active record
	 * @param array|MongoCursor|EMongoCriteria $criteria -  Either a condition array (without sort,limit and skip) or a MongoCursor Object
	 * @param array $fields
	 */
	public function __construct($modelClass, $criteria = array(), $fields = array()) {
    	// If $fields has something in it
    	if(!empty($fields))
    		$this->partial = true;

    	if(is_string($modelClass)){
			$this->modelClass = $modelClass;
			$this->model = EMongoDocument::model($this->modelClass);
		} elseif ($modelClass instanceof EMongoDocument){
			$this->modelClass = get_class($modelClass);
			$this->model = $modelClass;
		}

    	if($criteria instanceof MongoCursor){
    		$this->cursor = $criteria;
        	$this->cursor->reset();
    	} elseif ($criteria instanceof EMongoCriteria){
    		$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection()->find($criteria->condition, $criteria->project)->sort($criteria->sort);
			if($criteria->skip > 0)
				$this->cursor->skip($criteria->skip);
			if($criteria->limit > 0)
				$this->cursor->limit($criteria->limit);
    	} else {
			// Then we are doing an active query
			$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection()->find($criteria, $fields);
        }
    }

    /**
     * If we call a function that is not implemented here we try and pass the method onto
     * the MongoCursor class, otherwise we produce the error that normally appears
     *
     * @param string $method
     * @param array $params
	 * @return mixed
	 * @throws EMongoException
	 */
	public function __call($method, $params = array()){
		if($this->cursor() instanceof MongoCursor && method_exists($this->cursor(), $method)){
			return call_user_func_array(array($this->cursor(), $method), $params);
		}
		throw new EMongoException(Yii::t('yii', 'Call to undefined function {method} on the cursor', array('{method}' => $method)));
    }

	/**
	 * Holds the MongoCursor
	 * @return array|MongoCursor
	 */
	public function cursor(){
    	return $this->cursor;
    }

	/**
	 * Get next doc in cursor
	 * @return EMongoDocument|null
	 */
	public function getNext(){
		if($c = $this->cursor()->getNext())
			return $this->current = $this->model->populateRecord($c, true, $this->partial);
		return null;
    }

    /**
     * Gets the active record for the current row
	 * @return EMongoDocument|mixed
	 * @throws EMongoException
	 */
	public function current() {
    	if($this->model === null)
			throw new EMongoException(Yii::t('yii', 'The MongoCursor must have a model'));
    	return $this->current = $this->model->populateRecord($this->cursor()->current(), true, $this->partial);
    }

	/**
	 * Counts the records returned by the criteria. By default this will not take skip and limit into account 
	 * you can add inject true as the first and only parameter to enable MongoDB to take those offsets into 
	 * consideration.
	 *  
	 * @param bool $takeSkip
	 * @return int
	 */
	public function count($takeSkip = false /* Was true originally but it was to change the way the driver worked which seemed wrong */){
    	return $this->cursor()->count($takeSkip);
    }

	/**
	 * Set SlaveOkay
	 * @param bool $val
	 * @return EMongoCursor
	 */
	public function slaveOkay($val = true){
        $this->cursor()->slaveOkay($val);
        return $this;
    }

	/**
	 * Set sort fields
	 * @param array $fields
	 * @return EMongoCursor
	 */
	public function sort(array $fields){
		$this->cursor()->sort($fields);
		return $this;
    }

	/**
	 * Set skip
	 * @param int $num
	 * @return EMongoCursor
	 */
	public function skip($num = 0){
		$this->cursor()->skip($num);
		return $this;
    }

	/**
	 * Set limit
	 * @param int $num
	 * @return EMongoCursor
	 */
	public function limit($num = 0){
		$this->cursor()->limit($num);
		return $this;
    }

	/**
	 * Reset the MongoCursor to the beginning
	 * @return EMongoCursor
	 */
	public function rewind() {
       	$this->cursor()->rewind();
        return $this;
    }

	/**
	 * Get the current key (_id)
	 * @return mixed|string
	 */
	public function key() {
       	return $this->cursor()->key();
    }

	/**
	 * Move the pointer forward
	 */
	public function next() {
       	$this->cursor()->next();
    }

	/**
	 * Check if this position is a valid one in the cursor
	 * @return bool
	 */
	public function valid() {
        return $this->cursor()->valid();
    }
}