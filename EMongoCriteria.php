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
     * Base search functionality
     * @param string $column
     * @param [null|string] $value
     * @param boolean $strong
     * @return EMongoCriteria
     */
    public function compare($column, $value = null, $strong = false) {
        if (!$value)
            return $this;
        $query = array();
        if (preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/', $value, $matches)) {
            $value = $matches[2];
            $op = $matches[1];
            if (!$strong && !preg_match('/^[0-9]+$/', $value))
                $value = new MongoRegex("/$value/i");
            else {
                if (preg_match('/^[0-9]+$/', $value))
                    $value = (int) $value;
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
                $this->_sort = CMap::mergeArray($this->condition, $criteria->sort);

            if (isset($criteria->skip) && is_numeric($criteria->skip))
                $this->_skip = $criteria->skip;

            if (isset($criteria->limit) && is_numeric($criteria->limit))
                $this->_limit = $criteria->limit;
            return $this;
        } elseif (is_array($criteria)) {
            if (isset($criteria['condition']) && is_array($criteria['condition']))
                $this->_condition = CMap::mergeArray($this->condition, $criteria['condition']);

            if (isset($criteria['sort']) && is_array($criteria['sort']))
                $this->_sort = CMap::mergeArray($this->condition, $criteria['sort']);

            if (isset($criteria['skip']) && is_numeric($criteria['skip']))
                $this->_skip = $criteria['skip'];

            if (isset($criteria['limit']) && is_numeric($criteria['limit']))
                $this->_limit = $criteria['limit'];

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
    		foreach (array('_condition', '_limit', '_skip', '_sort') as $name)
    			$result[substr($name, 1)] = $this->$name;
    	}
    	return $result;
    }
}