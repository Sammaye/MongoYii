<?php

/**
 * A data Provider helper for interacting with the EMongoCursor
 */
class EMongoDataProvider extends CActiveDataProvider{

	/**
	 * @var string the primary ActiveRecord class name. The {@link getData()} method
	 * will return a list of objects of this class.
	 */
	public $modelClass;
	/**
	 * @var CActiveRecord the AR finder instance (eg <code>Post::model()</code>).
	 * This property can be set by passing the finder instance as the first parameter
	 * to the constructor. For example, <code>Post::model()->published()</code>.
	 */
	public $model;
	/**
	 * @var string the name of key attribute for {@link modelClass}. If not set,
	 * it means the primary key of the corresponding database table will be used.
	 */
	public $keyAttribute='_id';

	private $_criteria;

	private $_cursor;

	public function __construct($modelClass,$config=array()){

		if(is_string($modelClass))
		{
			$this->modelClass=$modelClass;
			$this->model=EMongoDocument::model($this->modelClass);
		}
		elseif($modelClass instanceof EMongoDocument)
		{
			$this->modelClass=get_class($modelClass);
			$this->model=$modelClass;
		}
		$this->setId($this->modelClass);
		foreach($config as $key=>$value)
			$this->$key=$value;

	}

	public function getCriteria(){
		return $this->_criteria;
	}

	public function setCriteria($value){
		$this->_criteria=$value;
	}

	function fetchData(){
		$criteria=$this->getCriteria();
		$this->_cursor = $this->model->find(isset($criteria['condition']) ? $criteria['condition'] : array());


		if(($pagination=$this->getPagination())!==false)
		{
			$pagination->setItemCount($this->getTotalItemCount());
			$this->_cursor->limit($pagination->getLimit());
			$this->_cursor->skip($pagination->getOffset());
		}

		if(($sort=$this->getSort())!==false && ($order=$sort->getOrderBy())!='')
		{
			$sort=array();
			foreach($this->getSortDirections($order) as $name=>$descending)
			{
				$sort[$name]=$descending ? '-1' : 1;
			}
			$this->_cursor->sort($sort);
		}

		return iterator_to_array($this->_cursor);
	}

	function fetchKeys(){
		$keys=array();
		foreach($this->getData() as $i=>$data)
		{
			$key=$this->keyAttribute===null ? $data->{$data->primaryKey()} : $data->{$this->keyAttribute};
			$keys[$i]=is_array($key) ? implode(',',$key) : $key;
		}
		return $keys;
	}

	function calculateTotalItemCount(){
		return $this->_cursor->count();
	}

	protected function getSortDirections($order)
	{
		$segs=explode(',',$order);
		$directions=array();
		foreach($segs as $seg)
		{
			if(preg_match('/(.*?)(\s+(desc|asc))?$/i',trim($seg),$matches))
			$directions[$matches[1]]=isset($matches[3]) && !strcasecmp($matches[3],'desc');
			else
			$directions[trim($seg)]=false;
		}
		return $directions;
	}
}