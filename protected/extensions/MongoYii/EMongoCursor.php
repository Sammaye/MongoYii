<?php

class EMongoCursor implements Iterator, Countable{

	public $condition;
	public $sort;
	public $skip = 0;
	public $limit;

	public $class;

	private $cursor = array();
	private $current;
	private $ok;

	private $queried = false;

	private $_db;

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

    function cursor(){
    	return $this->cursor;
    }

    function current() {
    	if($this->class === null){
			throw new EMongoException(Yii::t('yii', "The MongoCursor must have a class name"));
    	}

    	$className = $this->class;
    	$o = new $className('update');
		$o->setIsNewRecord(false);

    	//if(!$this->current->onBeforeFind()) return null; // Raise event of before find

    	$o->setAttributes($this->cursor()->current());

    	//$this->current->onAfterFind(); // Raise after find event

        return $this->current = $o;
    }
}