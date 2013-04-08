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

	public $criteria = array();

	public $modelClass;
	public $model;

	private $cursor = array();
	private $current;

	/**
	 * The cursor constructor
	 * @param array|MongoCursor $condition Either a condition array (without sort,limit and skip) or a MongoCursor Object
	 * @param string $class the class name for the active record
	 */
    public function __construct($modelClass,$criteria=array()) {

    	if(is_string($modelClass)){
			$this->modelClass=$modelClass;
			$this->model=EMongoDocument::model($this->modelClass);
		}elseif($modelClass instanceof EMongoDocument){
			$this->modelClass=get_class($modelClass);
			$this->model=$modelClass;
		}

    	if($criteria instanceof MongoCursor){
    		$this->cursor = $criteria;
        	$this->cursor->reset();
    	}elseif($criteria instanceof EMongoCriteria){
    		$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection(EMongoClient::READ)->find($criteria->condition)->sort($criteria->sort);
			if($criteria->skip != 0)
				$this->cursor->skip($criteria->skip);
			if($criteria->limit!=0)
				$this->cursor->limit($criteria->limit);
    	}else{
			// Then we are doing an active query
			$this->criteria = $criteria;
			$this->cursor = $this->model->getCollection(EMongoClient::READ)->find($criteria);
        }

        return $this; // Maintain chainability
    }

    /**
     * If we call a function that is not implemented here we try and pass the method onto
     * the MongoCursor class, otherwise we produce the error that normally appears
     *
     * @param $method
     * @param $params
     */
    public function __call($method, $params = array()){
		if($this->cursor() instanceof MongoCursor && method_exists($this->cursor(), $method)){
			return call_user_func_array(array($this->cursor(), $method), $params);
		}
		throw new EMongoException(Yii::t('yii', "Call to undefined function {$method} on the cursor"));
    }

    /**
     * Holds the MongoCursor
     */
    public function cursor(){
    	return $this->cursor;
    }

    /**
     * Gets the active record for the current row
     */
    public function current() {
    	if($this->model === null)
			throw new EMongoException(Yii::t('yii', "The MongoCursor must have a model"));
    	return $this->current=$this->model->populateRecord($this->cursor()->current());
    }

    public function count($takeSkip = false /* Was true originally but it was to change the way the driver worked which seemed wrong */){
    	return $this->cursor()->count($takeSkip);
    }

    public function sort(array $fields){
		$this->cursor()->sort($fields);
		return $this;
    }

    public function skip($num = 0){
		$this->cursor()->skip($num);
		return $this;
    }

    public function limit($num = 0){
		$this->cursor()->limit($num);
		return $this;
    }

    public function rewind() {
       	$this->cursor()->rewind();
        return $this;
    }

    public function key() {
       	return $this->cursor()->key();
    }

    public function next() {
       	return $this->cursor()->next();
    }

    public function valid() {
        return $this->cursor()->valid();
    }
}
