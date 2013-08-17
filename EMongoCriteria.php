<?php

/**
 * This is the extensions version of CDbCriteria.
 *
 * This class is by no means required however it can help in your programming.
 */
class EMongoCriteria extends CComponent {

    private $_condition = array();
    private $_sort = array();
    private $_skip = 0;
    private $_limit = 0;

	/**
	 * Holds information for what should be projected from the cursor
	 * into active models
	 * @var array
	 */
	private $_project = array();

    /**
     * Constructor.
     * @param array $data criteria initial property values (indexed by property name)
     */
    public function __construct($data=array()){
        foreach($data as $name=>$value)
            $this->$name=$value;
    }


    /**
    * Sets the condition
    * @param array $condition
    */
    public function setCondition(array $condition=array()) {
        $this->_condition = CMap::mergeArray($condition, $this->_condition);
        return $this;
    }

    /**
     * Gets the condition
     * @return array
     */
    public function getCondition() {
        return $this->_condition;
    }

    /**
     * Gets the sort
     * @return array
     */
    public function getSort() {
        return $this->_sort;
    }

    /**
     * Gets the skip
     * @return int
     */
    public function getSkip() {
        return $this->_skip;
    }

    /**
     * Gets the limit
     * @return int
     */
    public function getLimit() {
        return $this->_limit;
    }

    /**
     * This means that the getters and setters for projection will be access like:
     * $c->project(array('c','d'));
     */
    public function getProject(){
		return $this->_project;
    }

    /**
     * Sets the sort
     * @param array $sort
     * @return EMongoCriteria
     */
    public function setSort(array $sort) {
        $this->_sort = CMap::mergeArray($sort, $this->_sort);
        return $this;
    }

    /**
     * Sets the skip
     * @param int $skip
     * @return EMongoCriteria
     */
    public function setSkip($skip) {
        $this->_skip = (int)$skip;
        return $this;
    }

    /**
     * Sets the limit
     * @param int $limit
     * @return EMongoCriteria
     */
    public function setLimit($limit) {
        $this->_limit = (int)$limit;
        return $this;
    }

    /**
     * Sets the projection of the criteria
     * @param $document The document specification for projection
     */
    public function setProject($document){
		$this->_project=$document;
		return $this;
    }

    /**
     * Append condition to previous ones
     * @param string $column
     * @param mixin $value
     * @param string $operator
     * @return EMongoCriteria
     */
    public function addCondition($column, $value, $operator = null) {
        $this->_condition[$column] = $operator === null ? $value : array($operator => $value);
        return $this;
    }
	
	/**
	 * Append date comparison condition to previous ones
	 * @param string $column
	 * @param string $value
	 * @return EMongoCriteria
	 */
	public function addDateCondition($column, $value)
	{
        $start_date = new MongoDate(strtotime($value));
        $end_date = new MongoDate(strtotime($value . ' + 1 day'));
        $this->_condition[$column] = array('$gte' => $start_date, '$lt' => $end_date);
        return $this;
	}

    /**
     * Adds an $or condition to the criteria
     * @param array $condition
     */
    public function addOrCondition($condition){
    	$this->_condition['$or'] = $condition;
    	return $this;
    }

    /**
     * Base search functionality
     * @param string $column
     * @param [null|string] $value
     * @param boolean $strong
     * @return EMongoCriteria
     */
    public function compare($column, $value = null, $partialMatch = false) {
        if ($value===null)
            return $this;
        $query = array();
        if(is_array($value)||is_object($value)){
			$query[$column]=array('$in'=>$value);
        }elseif(preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)) {
            $value = $matches[2];
            $op = $matches[1];
            if ($partialMatch===true)
                $value = new MongoRegex("/$value/i");
            else {
				if(
					!is_bool($value) && !is_array($value) && preg_match('/^([0-9]|[1-9]{1}\d+)$/' /* Will only match real integers, unsigned */, $value) > 0
					&& ( (PHP_INT_MAX > 2147483647 && (string)$value < '9223372036854775807') /* If it is a 64 bit system and the value is under the long max */
					|| (string)$value < '2147483647' /* value is under 32bit limit */)
				)
					$value=(int)$value;
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
        if (!$query)
            $query[$column] = $value;
        $this->_condition = CMap::mergeArray($query, $this->_condition);
        return $this;
    }

    /**
     * Meges either an array of criteria or another criteria object with this one
     * @param [array|EMongoCriteria] $criteria
     * @return EMongoCriteria
     */
    public function mergeWith($criteria) {
        if ($criteria instanceof EMongoCriteria) {
            if (isset($criteria->condition) && is_array($criteria->condition))
                $this->_condition = CMap::mergeArray($this->condition, $criteria->condition);

            if (isset($criteria->sort) && is_array($criteria->sort))
                $this->_sort = CMap::mergeArray($this->sort, $criteria->sort);

            if (isset($criteria->skip) && is_numeric($criteria->skip))
                $this->_skip = $criteria->skip;

            if (isset($criteria->limit) && is_numeric($criteria->limit))
                $this->_limit = $criteria->limit;

            if (isset($criteria->project) && is_numeric($criteria->project))
                $this->_project = CMap::mergeArray($this->project,$criteria->project);
            return $this;
        } elseif (is_array($criteria)) {
            if (isset($criteria['condition']) && is_array($criteria['condition']))
                $this->_condition = CMap::mergeArray($this->condition, $criteria['condition']);

            if (isset($criteria['sort']) && is_array($criteria['sort']))
                $this->_sort = CMap::mergeArray($this->sort, $criteria['sort']);

            if (isset($criteria['skip']) && is_numeric($criteria['skip']))
                $this->_skip = $criteria['skip'];

            if (isset($criteria['limit']) && is_numeric($criteria['limit']))
                $this->_limit = $criteria['limit'];

            if (isset($criteria['project']) && is_numeric($criteria['project']))
                $this->_project = CMap::mergeArray($this->project,$criteria['project']);

            return $this;
        }
    }

    /**
     * @param boolean $onlyCondition indicates whether to return only condition part or criteria.
     * Should be setted in "true" if criteria it is used at EMongoDocument::find() and common find methods.
     * @return array native representation of the criteria
     */
    public function toArray($onlyCondition = false) {
    	$result = array();
    	if ($onlyCondition === true) {
    		$result = $this->condition;
    	} else {
    		foreach (array('_condition', '_limit', '_skip', '_sort', '_project') as $name)
    			$result[substr($name, 1)] = $this->$name;
    	}
    	return $result;
    }
}