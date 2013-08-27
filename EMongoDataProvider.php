<?php

/**
 * EMongoDataProvider
 *
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

	/**
	 * @var array The criteria array
	 */
	private $_criteria;

	/**
	 * @var string The internal MongoDB cursor as a MongoCursor instance
	 */
	private $_cursor;

	private $_sort;

	/**
	 * Creates the EMongoDataProvider instance
	 * @param string|EMongoDocument $modelClass
	 * @param string $config
	 */
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

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/web/CActiveDataProvider::getCriteria()
	 */
	public function getCriteria(){
		return $this->_criteria;
	}

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/web/CActiveDataProvider::setCriteria()
	 */
	public function setCriteria($value){
		$this->_criteria=$value;
	}

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/web/CActiveDataProvider::fetchData()
	 */
	public function fetchData(){

		if ($this->criteria instanceof EMongoCriteria)
			$criteria=$this->criteria->toArray();
		else
			$criteria=$this->getCriteria();

		// I have not refactored this line considering that the condition may have changed from total item count to here, maybe.
		$this->_cursor = $this->model->find(
			isset($criteria['condition']) && is_array($criteria['condition']) ? $criteria['condition'] : array(),
			isset($criteria['project']) && !empty($criteria['project']) ? $criteria['project'] : array() 
		);

		// If we have sort and limit and skip setup within the incoming criteria let's set it
		if(isset($criteria['sort']) && is_array($criteria['sort']))
			$this->_cursor->sort($criteria['sort']);
		if(isset($criteria['skip']) && is_int($criteria['skip']))
			$this->_cursor->skip($criteria['skip']);
		if(isset($criteria['limit']) && is_int($criteria['limit']))
			$this->_cursor->limit($criteria['limit']);
		if(isset($criteria['hint']) && (is_array($criteria['hint']) || is_string($criteria['hint'])))
			$this->_cursor->hint($criteria['hint']);

		if(($pagination=$this->getPagination())!==false)
		{
			$pagination->setItemCount($this->getTotalItemCount());
			$this->_cursor->limit($pagination->getLimit());
			$this->_cursor->skip($pagination->getOffset());
		}

		if(($sort=$this->getSort())!==false)
		{
			$sort = $sort->getOrderBy();
			if(sizeof($sort)>0){
				$this->_cursor->sort($sort);
			}
		}
		return iterator_to_array($this->_cursor,false);
	}

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/web/CActiveDataProvider::fetchKeys()
	 */
	public function fetchKeys(){
		$keys=array();
		foreach($this->getData() as $i=>$data)
		{
			$key=$this->keyAttribute===null ? $data->{$data->primaryKey()} : $data->{$this->keyAttribute};
			$keys[$i]=is_array($key) ? implode(',',$key) : $key;
		}
		return $keys;
	}

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/web/CActiveDataProvider::calculateTotalItemCount()
	 */
	public function calculateTotalItemCount(){
		if(!$this->_cursor){
			$criteria=$this->getCriteria();
			$this->_cursor=$this->model->find(isset($criteria['condition']) && is_array($criteria['condition']) ? $criteria['condition'] : array());
		}
		return $this->_cursor->count();
	}

	/**
	 * Returns the sort object. We don't use the neweer getSort function because it does not have the same functionality
	 * between 1.1.10 and 1.1.13, the functionality we need is actually in 1.1.13 only
	 * @return CSort|EMongoSort|mixed the sorting object. If this is false, it means the sorting is disabled.
	 */
	public function getSort($className='EMongoSort')
	{
		if($this->_sort===null)
		{
			$this->_sort=new $className;
			if(($id=$this->getId())!='')
				$this->_sort->sortVar=$id.'_sort';
				$this->_sort->modelClass=$this->modelClass;
		}
		return $this->_sort;
	}
}
