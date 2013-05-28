<?php
/**
 * EMongoModelArray class file
 * @author Pavel Tetyaev <pahanini@gmail.com>
 */

/**
 * EMongoArrayModel class (automating subdocuments)
 *
 * Implements lazy convertation arrays to EMongoModel. Override subDocuments function of
 * EMongoModel class (see tests for Models examples) to turn on subdocuments automating
 *
 * class User {
 *
 * // No phones attribute here!
 * ...
 *
 * function subDocuments()
 * {
 *		return array(
 * 			'phones' => array('Phones')
 * 		)
 * }
 *
 * ...
 * }
 *
 *
 * $user = User::model()-> findBy_id($id);
 * echo get_class($user->phones[0]); // Outputs Phone
 * $user->phones[0]->num=911;
 *
 *
 * The second feature is lazy indexing subdocuments by attribute, e.g. we have this json in collection
 *
 * {
 *   _id:1,
 *   phones:[
 *     {num:111, comment:'Work phone'},
 *     {num:222, comment:'Home phone'},
 *   ]
 * }
 *
 * if we define next subDocuments function:
 *
 * function subDocuments()
 * {
 *		return array(
 * 			'phones' => array('Phones', 'index'=>'num')
 * 		)
 * }
 *
 * We can access Phone model using num now
 *
 * $user=User::model()->findBy_id(1)
 * echo $user->phones[111]->comment; // Outputs 'WorkPhone'
 *
 *
 * All index and create classes actions are lazy, so if you do not read
 * this attributes it will not be created and indexed to keep performance.
 *
 */
class EMongoArrayModel implements Iterator, Countable, ArrayAccess {

	private $_sortColumn;
	private $_sortOrder;

	/**
	 * @var array index=>key map
	 */
	private $map;

	/**
	 * @var string Model class
	 */
	public $modelClass;

	/**
	 * @var string Model instance
	 */
	public $model;

	/**
	 * @var int internal pointer
	 */
	public $pointer=0;

	/**
	 * @var array subducuments
	 */
	private $values = array();

	/**
	 * @var null|string index name
	 */
	private $index=null;

	/**
	 * The constructor
	 *
	 * @param $modelClass
	 * @param array $values
	 * @param null|string $index
	 */
	public function __construct($modelClass,array $values=array(),$index=null) {
		if($modelClass===null){
			$this->model=new EMongoModel();
			$this->modelClass='EMongoModel';
		}elseif($modelClass instanceof EMongoModel){
			$this->model=$modelClass;
			$this->modelClass=get_class($modelClass);
		}else{
			$this->model=new $modelClass;
			$this->modelClass=$modelClass;
		}
		$this->populate($values);
		$this->index=$index;
	}

	/**
	 * @return int returns number of subdocuments
	 */
	public function count(){
		return count($this->values);
	}

	/**
	 * @ignore
	 */
	public function createMap() {
		$this->map=array();
		foreach($this->values as $key=>$val)
			$this->map[$this->getIndex($val)]=$key;
	}


	/**
	 * @return \EMongoModel|mixed EMongoModel for the current row or false
	 */
	public function current() {
		return $this->getValueAt($this->pointer);
	}

	/**
	 * Return's value of index attribute
	 *
	 * @param EMongoModel|array $value
	 * @param mixed $defaultValue
	 * @throws EMongoException
	 * @return mixed
	 */
	public function getIndex(&$value, $defaultValue=false)
	{
		if (is_object($value))
			if (empty($value->{$this->index}))
				if ($defaultValue===false)
					throw new EMongoException(Yii::t('yii','class {className} has empty index attribute {index}',
						array('{$className}'=>$this->modelClass, '{index}' => $this->index)));
				else
					return $value->{$this->index}=$defaultValue;
			else
				return $value->{$this->index};
		if (is_array($value))
			if (empty($value[$this->index]))
				if ($defaultValue===false)
					throw new EMongoException(Yii::t('yii','array has empty key {index}',
						array('{index}' => $this->index)));
				else
					return $value[$this->index]=$defaultValue;
			else
				return $value[$this->index];
		throw new EMongoException(Yii::t('yii','Value of subDocument must have array or EMongoArrayModel type.'));
	}

	/**
	 * @returns null|key in subdocument array
	 */
	protected function getKey($offset) {
		if (!$this->index)
			return $offset;
		if ($this->map===null)
			$this->createMap();
		return isset($this->map[$offset]) ? $this->map[$offset] : null;
	}

	/**
	 * @param $key
	 * @return EMongoModel|false
	 */
	protected function getValueAt($key)
	{
		if (!isset($this->values[$key])) {
			return false;
		}
		if (!$this->values[$key] instanceof $this->modelClass){
			$val=new $this->modelClass;
			$val->setAttributes($this->values[$key],false);
			$this->values[$key]=$val;
		}
		return $this->values[$key];
	}

	/**
	 * This function returns array where some elements are arrays and some are type of EMongoModel
	 * @return array
	 */
	public function getRawValues() {
		return $this->values;
	}

	/**
	 * @return int current key
	 */
	public function key() {
		return $this->pointer;
	}

	/**
	 * Move to next element
	 */
	public function next() {
		return $this->pointer++;
	}

	/**
	 * @param mixed $offset
	 * @return bool wherther element exists
	 */
	public function offsetExists($offset) {
		return isset($this->values[$this->getKey($offset)]);
	}

	/**
	 * @param mixed $offset
	 * @return EMongoModel|false|mixed
	 */
	public function offsetGet($offset) {
		return $this->getValueAt($this->getKey($offset));
	}

	/**
	 * Set element at offset
	 * @param mixed $offset
	 * @param array|EMongoModel $value
	 * @throws EMongoException
	 */
	public function offsetSet($offset, $value) {
		if ($this->index){
			$offset=$this->getIndex($value,$offset);
			if($this->offsetExists($offset)){
				$this->values[$this->getKey($offset)]=$value;
			}else{
				if ($this->map!==null)
					$this->map[$offset]=count($this->values);
			}
		}
		$this->values[]=$value;
	}

	/**
	 * @param mixed $offset
	 */
	public function offsetUnset($offset) {
		$key=$this->getKey($offset);
		unset($this->values[$key]);
		$this->values=array_values($this->values);
		$this->pointer=0;
		if ($this->index)
			$this->map=null;
	}

	/**
	 * @param $val
	 */
	public function populate($val) {
		if ($this->index) foreach($val as $k=>$v) $this->getIndex($val[$k],$k);
		$this->values=array_values($val);
		$this->map=null;
		$this->pointer=0;
	}

	/**
	 * Resets intrnal pointer
	 */
	public function rewind() {
		$this->pointer=0;
	}

	/**
	 * @param $array Sets subdocument value
	 */
	public function setValues($array) {
		$this->values=$array;
		$this->isIndexed=$this->index===null;
	}

	/**
	 * Sorts subdocuments
	 * @param $val
	 * @return EMongoArrayModel
	 */
	public function sort($val) {
		list($this->_sortColumn, $this->_sortOrder)=each($val);
		$values=$this->getRawValues();
		usort($values, array($this, 'sortCallback'));
		$this->populate($values);
		return $this;
	}

	/**
	 * @ignore
	 * @param $a
	 * @param $b
	 * @return int
	 */
	public function sortCallback($a, $b) {
		$a = is_object($a) ? $a->{$this->_sortColumn} : $a[$this->_sortColumn];
		$b = is_object($b) ? $b->{$this->_sortColumn} : $b[$this->_sortColumn];
		if ($a==$b) return 0;
		return $this->_sortOrder * (($a < $b) ? -1 : 1);
	}

	/**
	 * @return bool wthether internal pointer points to existing element
	 */
	public function valid() {
		return isset($this->values[$this->pointer]);
	}
}