<?php

/**
 * EMongoCursor
 *
 * Represents the Yii edition to the MongoCursor and allows for lazy loading of objects.
 *
 * This class does not support eager loading by default, in order to use eager loading you should look into using this
 * classes reponse with iterator_to_array().
 */
class EMongoCursor implements Iterator, Countable{

	public $condition;
	public $class;

	/**
	 * Most of these properties are redundant atm, I might re-add the code related to them
	 * in future versions, it depends upon the need.
	 */
	public $sort;
	public $skip = 0;
	public $limit;

	private $cursor = array();
	private $current;
	private $ok;

	private $queried = false;

	/**
	 * The cursor constructor
	 * @param array|MongoCursor $condition Either a condition array (without sort,limit and skip) or a MongoCursor Object
	 * @param string $class the class name for the active record
	 */
    public function __construct($condition, $class) {

    	$this->class = $class;

    	if($condition instanceof MongoCursor){
    		$this->cursor = $condition;
        	$this->cursor->reset();
    	}elseif($class){
			// Then we are doing an active query
			$this->condition = $condition;
			$this->cursor = $class::model()->getCollection()->find($condition);
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
    function __call($method, $params = array()){
		if($this->cursor() instanceof \MongoCursor && method_exists($this->cursor(), $method)){
			return call_user_func_array(array($this->cursor(), $method), $params);
		}
		throw new EMongoException(Yii::t('yii', "Call to undefined function {$method} on the cursor"));
    }

    /**
     * Holds the MongoCursor
     */
    function cursor(){
    	return $this->cursor;
    }

    /**
     * Gets the active record for the current row
     */
    function current() {
    	if($this->class === null){
			throw new EMongoException(Yii::t('yii', "The MongoCursor must have a class name"));
    	}

    	$className = $this->class;
    	return $className::model()->populateRecord($this->cursor()->current());
    }

	/**
	 * Counts the elements
	 */
    function count(){
    	if($this->cursor() instanceof MongoCursor)
    		return $this->cursor()->count();
    	elseif($this->cursor())
    		return sizeof($this->cursor);
    }

    /**
     * I refuse to do client side sorting at this minute
     *
     * @param $fields
     */
    function sort(array $fields){
    	if($this->cursor() instanceof MongoCursor)
			$this->cursor()->sort($fields);
		return $this;
    }

    /**
     * If the cursor is not a server-side cursor this will perform an in-memory
     * slice of the array
	 *
     * @param int $num
     */
    function skip($num = 0){
    	if($this->cursor() instanceof MongoCursor)
			$this->cursor()->skip($num);
		elseif($this->cursor())
			$this->skip = $num;
		return $this;
    }

    /**
     * This will either perform a limit on the MongoDB cursor or a
     * in-memory limit
     *
     * @param int $num
     */
    function limit($num = 0){
    	if($this->cursor() instanceof MongoCursor)
			$this->cursor()->limit($num);
		elseif($this->cursor())
			$this->limit = $num;

		return $this;
    }

    function rewind() {
    	if($this->cursor() instanceof MongoCursor)
        	$this->cursor()->rewind();
        elseif($this->cursor()){
        	reset($this->cursor);
        }

        return $this;
    }

    function key() {
    	if($this->cursor() instanceof MongoCursor)
        	return $this->cursor()->key();
        elseif($this->cursor())
        	return key($this->cursor);
    }

    function next() {
    	if($this->cursor() instanceof MongoCursor)
        	return $this->cursor()->next();
        elseif($this->cursor())
        	return next($this->cursor);
    }

    /**
     * This is the first function always run when you start to iterate through a foreach as such
     * this is the natural place to put code that can be used to lazy load certain processing like
     * the slicing of arrays after in-memory operators were added
     */
    function valid() {
    	if($this->cursor() instanceof MongoCursor)
        	return $this->cursor()->valid();
        elseif($this->cursor()){

        	// If this is the first time we have run this iterator then let us do in memory aggregation operations now
        	if(!$this->queried){
        		if($this->skip > 0)
        			$this->cursor = array_values(array_slice($this->cursor, $this->skip, $this->limit));
        		else
        			$this->cursor = array_slice($this->cursor, $this->skip, $this->limit);
        	}

        	$this->queried = true;
        	return !is_null(key($this->cursor));
        }
    }
}