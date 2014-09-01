<?php

/**
 * This is the extensions version of CDbCriteria.
 *
 * This class is by no means required however it can help in your programming.
 *
 * @property array $condition
 * @property array $sort
 * @property int $skip
 * @property int $limit
 * @property array $project
 */
class EMongoCriteria extends CComponent
{
	/**
	 * @var array
	 */
	private $_condition = array();
	
	/**
	 * @var array
	 */
	private $_sort = array();
	
	/**
	 * @var int
	*/
	private $_skip = 0;
	
	/**
	 * @var int
	 */
	private $_limit = 0;

	/**
	 * Holds information for what should be projected from the cursor
	 * into active models. The reason for this obscure name is because this
	 * is what it is called in MongoDB, basically it is SELECT though.
	 * @var array
	 */
	private $_project = array();

	/**
	 * Constructor.
	 * @param array $data - criteria initial property values (indexed by property name)
	*/
	public function __construct($data = array())
	{
		foreach($data as $name => $value){
			$this->$name = $value;
		}
	}

	/**
	 * Sets the condition
	 * @param array $condition
	 * @return EMongoCriteria
	 */
	public function setCondition(array $condition=array())
	{
		$this->_condition = CMap::mergeArray($condition, $this->_condition);
		return $this;
	}

	/**
	 * Gets the condition
	 * @return array
	 */
	public function getCondition()
	{
		return $this->_condition;
	}

	/**
	 * Sets the sort
	 * @param array $sort
	 * @return EMongoCriteria
	 */
	public function setSort(array $sort)
	{
		foreach($sort as $field => $order){
			if($order === 'asc'){
				$sort[$field] = 1;
			}elseif($order === 'desc'){
				$sort[$field] = -1;
			}
		}
		
		$this->_sort = CMap::mergeArray($sort, $this->_sort);
		return $this;
	}

	/**
	 * Gets the sort
	 * @return array
	 */
	public function getSort()
	{
		return $this->_sort;
	}

	/**
	 * Sets the skip
	 * @param int $skip
	 * @return EMongoCriteria
	 */
	public function setSkip($skip)
	{
		$this->_skip = (int)$skip;
		return $this;
	}

	/**
	 * Gets the skip
	 * @return int
	 */
	public function getSkip()
	{
		return $this->_skip;
	}

	/**
	 * Sets the limit
	 * @param int $limit
	 * @return EMongoCriteria
	 */
	public function setLimit($limit)
	{
		$this->_limit = (int)$limit;
		return $this;
	}

	/**
	 * Gets the limit
	 * @return int
	 */
	public function getLimit()
	{
		return $this->_limit;
	}

	/**
	 * Sets the projection (SELECT in MongoDB Lingo) of the criteria
	 * @param array $document - The document specification for projection
	 * @return EMongoCriteria
	 */
	public function setProject($document)
	{
		$this->_project = $document;
		return $this;
	}

	/**
	 * This means that the getters and setters for projection will be access like:
	 * $c->project(array('c'=>1,'d'=>0));
	 * @return array
	 */
	public function getProject()
	{
		return $this->_project;
	}

	/**
	 * An alias for those too used to select
	 * @see EMongoCriteria::setProject()
	 * @param array $document
	 * @return EMongoCriteria
	 */
	public function setSelect($document)
	{
		return $this->setProject($document);
	}

	/**
	 * An alias for those too used to select
	 * @see EMongoCriteria::getProject()
	 * @return array
	 */
	public function getSelect()
	{
		return $this->getProject();
	}

	/**
	 * Append condition to previous ones using the column name as the index
	 * This will overwrite columns of the same name
	 * @param string $column
	 * @param mixed $value
	 * @param string $operator
	 * @return EMongoCriteria
	 */
	public function addCondition($column, $value, $operator = null)
	{
		$this->_condition[$column] = $operator === null ? $value : array($operator => $value);
		return $this;
	}

	/**
	 * Adds an $or condition to the criteria, will overwrite other $or conditions
	 * @param array $condition
	 * @return EMongoCriteria
	 */
	public function addOrCondition($condition)
	{
		$this->_condition['$and'][] = array('$or' => $condition);
		return $this;
	}

	/**
	 * Base search functionality
	 * @param string $column
	 * @param string|null $value
	 * @param boolean $partialMatch
	 * @return EMongoCriteria
	 */
	public function compare($column, $value = null, $partialMatch = false)
	{
		$query = array();
		
		if($value === null){
			$query[$column] = null;
		}elseif(is_array($value)){
			$query[$column] = array('$in' => $value);
		}elseif(is_object($value)){
			$query[$column] = $value;
		}elseif(is_bool($value)){
			$query[$column] = $value;
		}elseif(preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)){
			$value = $matches[2];
			$op = $matches[1];
			if($partialMatch === true){
				$value = new MongoRegex("/$value/i");
			}else{
				if(
					!is_bool($value) && !is_array($value) && preg_match('/^([0-9]|[1-9]{1}\d+)$/' /* Will only match real integers, unsigned */, $value) > 0
					&& ( 
						(PHP_INT_MAX > 2147483647 && (string)$value < '9223372036854775807') /* If it is a 64 bit system and the value is under the long max */
						|| (string)$value < '2147483647' /* value is under 32bit limit */
					)
				){
					$value = (int)$value;
				}
			}

			switch($op){
				case "<>":
					$query[$column] = array('$ne' => $value);
					break;
				case "<=":
					$query[$column] = array('$lte' => $value);
					break;
				case ">=":
					$query[$column] = array('$gte' => $value);
					break;
				case "<":
					$query[$column] = array('$lt' => $value);
					break;
				case ">":
					$query[$column] = array('$gt' => $value);
					break;
				case "=":
				default:
					$query[$column] = $value;
					break;
			}
		}
		if(!$query){
			$query[$column] = $value;
		}
		$this->addCondition($column,  $query[$column]);
		return $this;
	}

	/**
	 * Merges either an array of criteria or another criteria object with this one
	 * @param array|EMongoCriteria $criteria
	 * @return EMongoCriteria
	 */
	public function mergeWith($criteria)
	{
		if($criteria instanceof EMongoCriteria){
			return $this->mergeWith($criteria->toArray());
		}
		if(is_array($criteria)){
			if(isset($criteria['condition']) && is_array($criteria['condition'])){
				$this->setCondition(CMap::mergeArray($this->condition, $criteria['condition']));
			}
			if(isset($criteria['sort']) && is_array($criteria['sort'])){
				$this->setSort(CMap::mergeArray($this->sort, $criteria['sort']));
			}
			if(isset($criteria['skip']) && is_numeric($criteria['skip'])){
				$this->setSkip($criteria['skip']);
			}
			if(isset($criteria['limit']) && is_numeric($criteria['limit'])){
				$this->setLimit($criteria['limit']);
			}
			if(isset($criteria['project']) && is_array($criteria['project'])){
				$this->setProject(CMap::mergeArray($this->project, $criteria['project']));
			}
		}
		return $this;
	}

	/**
	 * @param boolean $onlyCondition -  indicates whether to return only condition part or criteria.
	 * Should be "true" if the criteria is used in EMongoDocument::find() and other common find methods.
	 * @return array - native representation of the criteria
	 */
	public function toArray($onlyCondition = false)
	{
		$result = array();
		if($onlyCondition === true){
			$result = $this->condition;
		}else{
			foreach(array('_condition', '_limit', '_skip', '_sort', '_project') as $name){
				$result[substr($name, 1)] = $this->$name;
			}
		}
		return $result;
	}
}