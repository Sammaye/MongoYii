<?php

/**
 * EMongoCriteria
 *
 * Yes it is here but I am not sure why. I have made this incase it proves to more suitable to beginners with
 * MongoDB to have this class and whether it makes for better programming in general.
 *
 * My personal opinion is that MongoDB has a natural and easy to understand language that doesn't really require a
 * criteria class to build moduler queries from. Not only that but unlike SQL where you can form the entire query here
 * in MongoDB you still have to do tests to find out why limit and skip should truly be used since the Cursor is, of course,
 * an object.
 *
 * For these reasons this class is not relied on anywhere and can easily be taken out.
 */
class EMongoCriteria{

	public $condition = array();
	public $sort = array();
	public $skip = 0;
	public $limit = 0;

    public function __construct($data) {
		foreach($data as $name=>$value)
			$this->$name=$value;
    }

	public function mergeWith($criteria){
		if(isset($criteria['condition']) && is_array($criteria['condition']))
			$this->condition = Yii::app()->monogdb->merge($this->condition, $criteria['condition']);

		if(isset($criteria['sort']) && is_array($criteria['sort']))
			$this->sort = Yii::app()->monogdb->merge($this->condition, $criteria['sort']);

		if(isset($criteria['skip'])&& is_numeric($criteria['skip']))
			$this->skip = $criteria['skip'];

		if(isset($criteria['limit'])&& is_numeric($criteria['limit']))
			$this->limit = $criteria['limit'];

		return $this;
	}
}