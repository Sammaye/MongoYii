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
			$this->cursor = $class::model()->getCollection()->find($cursor);
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
}