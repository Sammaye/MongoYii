<?php

/**
 * EMongoDocument
 *
 * The active record itself
 */
class EMongoDocument extends EMongoModel{

	/**
	 * Holds a set of cached models for the active record to instantiate from
	 *
	 * Whenever you call ::model() it will either find the class in this cache arrray and use it or will
	 * make a whole new class and cache it into this array
	 *
	 * @var array
	 */
	private static $_models=array();

	/**
	 * Whether or not the document is new
	 * @var boolean
	 */
	private $_new=false;

	/**
	 * Holds criteria information for scopes
	 */
	private $_criteria;

	/**
	 * Contains a list of fields that were projected, will only be taken into consideration
	 * should _partial be true
	 * @var array
	 */
	private $_projected_fields = array();

	/**
	 * A bit deceptive, this var actually holds the last response from the server. The reason why it is called this
	 * is because this is what MongoDB calls it.
	 * @var mixed
	 */
	private $lastError;

	/**
	 * Sets up our model and set the field cache just like in EMongoModel
	 *
	 * It will also set the default scope on the model so be aware that if you want the default scope to not be applied you will
	 * need to run resetScope() straight after making this model
	 *
	 * @param string $scenario
	 */
	public function __construct($scenario='insert'){

		$this->getDbConnection()->setDocumentCache($this);

		if($scenario===null) // internally used by populateRecord() and model()
			return;

		$this->setScenario($scenario);
		$this->setIsNewRecord(true);

		$this->init();

		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	/**
	 * This, in addition to EMongoModels edition, will also call scopes on the model
	 * @see protected/extensions/MongoYii/EMongoModel::__call()
	 */
	public function __call($name,$parameters){

		if(array_key_exists($name, $this->relations())){
			if(empty($parameters))
				return $this->getRelated($name,false);
			else
				return $this->getRelated($name,false,$parameters[0]);
		}

		$scopes=$this->scopes();
		if(isset($scopes[$name])){
			$this->setDbCriteria($this->mergeCriteria($this->_criteria, $scopes[$name]));
			return $this;
		}
		return parent::__call($name,$parameters);
	}

	/**
	 * The scope attached to this model
	 *
	 * It is very much like how Yii normally uses scopes except the params are slightly different.
	 *
	 * @example
	 *
	 * array(
	 * 	'10_recently_published' => array(
	 * 		'condition' => array('published' => 1),
	 * 		'sort' => array('date_published' => -1),
	 * 		'skip' => 5,
	 * 		'limit' => 10
	 * 	)
	 * )
	 *
	 * Not all params need to be defined they are all just there above to give an indea of how to use this
	 *
	 * @return An array of scopes
	 */
	public function scopes()
	{
		return array();
	}

	/**
	 * Sets the default scope
	 *
	 * @example
	 *
	 * array(
	 * 	'condition' => array('published' => 1),
	 * 	'sort' => array('date_published' => -1),
	 * 	'skip' => 5,
	 * 	'limit' => 10
	 * )
	 *
	 * @return an array which represents a single scope within the scope() function
	 */
	public function defaultScope()
	{
		return array();
	}

	/**
	 * Resets the scopes applied to the model clearing the _criteria variable
	 * @return $this
	 */
	public function resetScope($resetDefault=true)
	{
		if($resetDefault)
			$this->_criteria = array();
		else
			$this->_criteria = null;
		return $this;
	}

	/**
	 * Returns the collection name as a string
	 *
	 * @example
	 *
	 * return 'users';
	 */
	function collectionName(){  }

	/**
	 * Returns MongoId based on $value
	 *
	 * @deprecated This function will become deprecated in favour of consistently
	 * using the getPrimaryKey() function instead. Atm, however, the getPrimaryKey
	 * function actually chains onto this method. If you see this and are wondering
	 * about what you should do if you want custom primary keys etc just use the getPrimaryKey
	 * function as you would the getMongoId function. These two functions should never have been separate
	 * for they are the same essentially.
	 *
	 * As to what version this will become deprecated:- I dunno. It will not be soon since it will be a
	 * functionality breaker...
	 *
	 * @param string|MongoId $value
	 * @return MongoId
	 */
	public function getMongoId($value=null){
		return $value instanceof MongoId ? $value : new MongoId($value);
	}

	/**
	 * Returns the value of the primary key
	 */
	public function getPrimaryKey($value=null){
		if($value===null)
			$value=$this->{$this->primaryKey()};
		return $this->getMongoId($value);
	}

	/**
	 * Returns if the current record is new.
	 * @return boolean whether the record is new and should be inserted when calling {@link save}.
	 * This property is automatically set in constructor and {@link populateRecord}.
	 * Defaults to false, but it will be set to true if the instance is created using
	 * the new operator.
	 */
	public function getIsNewRecord(){
		return $this->_new;
	}

	/**
	 * Sets if the record is new.
	 * @param boolean $value whether the record is new and should be inserted when calling {@link save}.
	 * @see getIsNewRecord
	 */
	public function setIsNewRecord($value){
		$this->_new=$value;
	}

	/**
	 * Gets a list of the projected fields for the model
	 */
	public function getProjectedFields(){
		return $this->_projected_fields;
	}

	/**
	 * Sets the projected fields of the model
	 * @param array $a
	 */
	public function setProjectedFields($a){
		$this->_projected_fields=$a;
	}

	/**
	 * Sets the attribute of the model
	 * @param string $name
	 * @param mixed $value
	 */
	public function setAttribute($name,$value){
		// At the moment the projection is restricted to only fields returned in result set
		// Uncomment this to change that
		//if($this->getIsPartial())
		//	$this->_projected_fields[$name] = 1;
		return parent::setAttribute($name, $value);
	}

	/**
	 * Returns the static model of the specified AR class.
	 * The model returned is a static instance of the AR class.
	 * It is provided for invoking class-level methods (something similar to static class methods.)
	 *
	 * EVERY derived AR class must override this method as follows,
	 * <pre>
	 * public static function model($className=__CLASS__)
	 * {
	 *     return parent::model($className);
	 * }
	 * </pre>
	 *
	 * @param string $className active record class name.
	 * @return EMongoDocument active record model instance.
	 */
	public static function model($className=__CLASS__){
		if(isset(self::$_models[$className]))
			return self::$_models[$className];
		else{
			$model=self::$_models[$className]=new $className(null);
			$model->attachBehaviors($model->behaviors());
			return $model;
		}
	}

	/**
	 * Instantiates a model from an array
	 * @param array $document
	 */
    protected function instantiate($document){
		$class=get_class($this);
		$model=new $class(null);
		return $model;
    }

	/**
	 * Returns the text label for the specified attribute.
	 * This method overrides the parent implementation by supporting
	 * returning the label defined in relational object.
	 * In particular, if the attribute name is in the form of "post.author.name",
	 * then this method will derive the label from the "author" relation's "name" attribute.
	 * @param string $attribute the attribute name
	 * @return string the attribute label
	 * @see generateAttributeLabel
	 */
	public function getAttributeLabel($attribute)
	{
		$labels=$this->attributeLabels();
		if(isset($labels[$attribute]))
			return $labels[$attribute];
		elseif(strpos($attribute,'.')!==false)
		{
			$segs=explode('.',$attribute);
			$name=array_pop($segs);
			$model=$this;
			foreach($segs as $seg)
			{
				$relations=$model->getMetaData()->relations;
				if(isset($relations[$seg]))
					$model=EMongoDocument::model($relations[$seg]->className);
				else
					break;
			}
			return $model->getAttributeLabel($name);
		}
		else
			return $this->generateAttributeLabel($attribute);
	}

	/**
	 * Creates an active record with the given attributes.
	 * This method is internally used by the find methods.
	 * @param array $attributes attribute values (column name=>column value)
	 * @param boolean $callAfterFind whether to call {@link afterFind} after the record is populated.
	 * @return CActiveRecord the newly created active record. The class of the object is the same as the model class.
	 * Null is returned if the input data is false.
	 */
	public function populateRecord($attributes,$callAfterFind=true,$partial=false)
	{
		if($attributes!==false)
		{
			$record=$this->instantiate($attributes);
			$record->setScenario('update');
			$record->setIsNewRecord(false);
			$record->init();

			$labels=array();
			foreach($attributes as $name=>$value)
			{
				$labels[$name]=1;
				$record->$name=$value;
			}

			if($partial){
				$record->setIsPartial(true);
				$record->setProjectedFields($labels);
			}
			//$record->_pk=$record->primaryKey();
			$record->attachBehaviors($record->behaviors());
			if($callAfterFind)
				$record->afterFind();
			return $record;
		}
		else
			return null;
	}

	/**
	 * Events
	 */
	public function onBeforeSave($event){ $this->raiseEvent('onBeforeSave',$event); }

	public function onAfterSave($event){ $this->raiseEvent('onAfterSave',$event); }

	public function onBeforeDelete($event){ $this->raiseEvent('onBeforeDelete',$event); }

	public function onAfterDelete($event){ $this->raiseEvent('onAfterDelete',$event); }

	public function onBeforeFind($event){ $this->raiseEvent('onBeforeFind',$event); }

	public function onAfterFind($event){ $this->raiseEvent('onAfterFind',$event); }

	protected function beforeSave()
	{
		if($this->hasEventHandler('onBeforeSave'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterSave()
	{
		if($this->hasEventHandler('onAfterSave'))
			$this->onAfterSave(new CEvent($this));
	}

	protected function beforeDelete()
	{
		if($this->hasEventHandler('onBeforeDelete'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterDelete()
	{
		if($this->hasEventHandler('onAfterDelete'))
			$this->onAfterDelete(new CEvent($this));
	}

	protected function beforeFind()
	{
		if($this->hasEventHandler('onBeforeFind'))
		{
			$event=new CModelEvent($this);
			// for backward compatibility
			$event->criteria=func_num_args()>0 ? func_get_arg(0) : null;
			$this->onBeforeFind($event);
		}
	}

	protected function afterFind()
	{
		if($this->hasEventHandler('onAfterFind'))
			$this->onAfterFind(new CEvent($this));
	}

	/**
	 * Saves this record
	 *
	 * If an attributes specification is sent in it will only validate and save those attributes
	 *
	 * @param boolean $runValidation
	 * @param array $attributes
	 */
	public function save($runValidation=true,$attributes=null){
		if(!$runValidation || $this->validate($attributes))
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		else
			return false;
	}

	/**
	 * Saves only a specific subset of attributes as defined by the param
	 * @param array $attributes
	 * @throws CDbException
	 */
	public function saveAttributes($attributes)
	{
		if(!$this->getIsNewRecord())
		{
			$this->trace(__FUNCTION__);
			$values=array();
			foreach($attributes as $name=>$value)
			{
				if(is_integer($name)){
					$v = $this->$value;
					if(is_array($this->$value)){
						$v = $this->filterRawDocument($this->$value);
					}
					$values[$value]=$v;
				}else
					$values[$name]=$this->$name=$value;
			}
			if(!isset($this->{$this->primaryKey()}) || $this->{$this->primaryKey()}===null)
				throw new CDbException(Yii::t('yii','The active record cannot be updated because its _id is not set!'));

			return $this->updateByPk($this->{$this->primaryKey()},$values);
		}
		else
			throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
	}

	/**
	 * Inserts this record
	 * @param array $attributes
	 * @throws CDbException
	 */
	public function insert($attributes=null){
		if(!$this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);

			if(!isset($this->{$this->primaryKey()})) $this->{$this->primaryKey()} = new MongoId;
			if($this->lastError=$this->getCollection()->insert($this->getRawDocument(), $this->getDbConnection()->getDefaultWriteConcern())){
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;
	}

	/**
	 * Updates this record
	 * @param array $attributes
	 * @throws CDbException
	 */
	public function update($attributes=null){
		if($this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);
			if($this->{$this->primaryKey()}===null) // An _id is required
				throw new CDbException(Yii::t('yii','The active record cannot be updated because it has no _id.'));

			if($attributes!==null)
				$attributes=$this->getRawAttributes($attributes);
			elseif($this->getIsPartial()){
				foreach($this->_projected_fields as $field => $v)
					$attributes[$field] = $this->$field;
				$attributes=$this->filterRawDocument($attributes);
			}else
				$attributes=$this->getRawDocument();
			unset($attributes['_id']); // Unset the _id before update
			$this->lastError=$this->updateByPk($this->{$this->primaryKey()}, array('$set' => $attributes));

			$this->afterSave();
			return true;
		}
		else
			return false;
	}

	/**
	 * Deletes this record
	 * @throws CDbException
	 */
	public function delete(){
		if(!$this->getIsNewRecord()){
			$this->trace(__FUNCTION__);
			if($this->beforeDelete()){
				$result=$this->deleteByPk($this->{$this->primaryKey()});
				$this->afterDelete();
				return $result;
			}
			else
				return false;
		}
		else
			throw new CDbException(Yii::t('yii','The active record cannot be deleted because it is new.'));
	}

	/**
	 * Checks if a record exists in the database
	 * @param array $criteria
	 */
	public function exists($criteria=array()){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		return $this->getCollection()->findOne($criteria)!==null;
	}

	/**
	 * Compares current active record with another one.
	 * The comparison is made by comparing table name and the primary key values of the two active records.
	 * @param EMongoDocument $record record to compare to
	 * @return boolean whether the two active records refer to the same row in the database table.
	 */
	public function equals($record)
	{
		return $this->collectionName()===$record->collectionName() && (string)$this->getPrimaryKey()===(string)$record->getPrimaryKey();
	}

	/**
	 * Find one record
	 * @param array $criteria
	 */
	public function findOne($criteria=array(),$fields=array()){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		$c=$this->getDbCriteria();
		if((
			$record=$this->getCollection()->findOne($this->mergeCriteria(isset($c['condition']) ? $c['condition'] : array(), $criteria),
				$this->mergeCriteria(isset($c['project']) ? $c['project'] : array(), $fields))
		)!==null){
			$this->resetScope();
			return $this->populateRecord($record,true,$fields===array()?false:true);
		}else
			return null;
	}

	/**
	 * Alias of find
	 * @param array $criteria
	 */
    public function findAll($criteria=array(),$fields=array()){
    	return $this->find ($criteria,$fields);
    }

	/**
	 * Find some records
	 * @param array $criteria
	 */
    public function find($criteria=array(),$fields=array()){
    	$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria){
			$c = $criteria->mergeWith($this->getDbCriteria())->toArray();
			$criteria=array();
		}else{
			$c=$this->getDbCriteria();
		}

    	if($c!==array()){
    		$cursor = new EMongoCursor($this, $this->mergeCriteria(isset($c['condition']) ? $c['condition'] : array(), $criteria),
    			$this->mergeCriteria(isset($c['project']) ? $c['project'] : array(), $fields));
			if(isset($c['sort'])) $cursor->sort($c['sort']);
    		if(isset($c['skip'])) $cursor->skip($c['skip']);
    		if(isset($c['limit'])) $cursor->limit($c['limit']);

    		$this->resetScope();
	   		return $cursor;
    	}else{
    		return new EMongoCursor($this, $criteria, $fields);
    	}
    }

    /**
     * Finds one by _id
     * @param $_id
     */
    public function findBy_id($_id,$fields=array()){
    	$this->trace(__FUNCTION__);
		$_id = $this->getPrimaryKey($_id);
		return $this->findOne(array($this->primaryKey() => $_id),$fields);
    }

    /**
     * An alias for findBy_id() that relates to Yiis own findByPk
     * @param $pk
     */
    public function findByPk($pk,$fields=array()){
    	$this->trace(__FUNCTION__);
		return $this->findBy_id($pk,$fields);
    }

    /**
     * Delete record by pk
     * @param $pk
     * @param $criteria
     * @param $options
     */
	public function deleteByPk($pk,$criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		$pk = $this->getPrimaryKey($pk);
		return $this->getCollection()->remove(array_merge(array($this->primaryKey() => $pk), $criteria),
					array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * Update record by PK
	 *
	 * @param string $pk
	 * @param array $updateDoc
	 * @param array $options
	 */
	public function updateByPk($pk, $updateDoc = array(), $criteria = array(), $options = array()){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		$pk = $this->getPrimaryKey($pk);
		return $this->getCollection()->update($this->mergeCriteria($criteria, array($this->primaryKey() => $pk)),$updateDoc,
				array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * Update all records matching a criteria
	 * @param array $criteria
	 * @param array $updateDoc
	 * @param array $options
	 */
	public function updateAll($criteria=array(),$updateDoc=array(),$options=array('multiple'=>true)){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		return $this->getCollection()->update($criteria, $updateDoc, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * Delete all records matching a criteria
	 * @param array $criteria
	 * @param array $options
	 */
	public function deleteAll($criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);

		if($criteria instanceof EMongoCriteria)
			$criteria = $criteria->getCondition();
		return $this->getCollection()->remove($criteria, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * (non-PHPdoc)
	 * @see http://www.yiiframework.com/doc/api/1.1/CActiveRecord#saveCounters-detail
	 */
	public function saveCounters(array $counters) {
		$this->trace(__FUNCTION__);

		if ($this->getIsNewRecord())
			throw new EMongoException(Yii::t('yii', 'The active record cannot be updated because it is new.'));

		if(sizeof($counters)>0){
			foreach($counters as $k => $v) $this->$k=$this->$k+$v;
			return $this->updateByPk($this->{$this->primaryKey()}, array('$inc' => $counters));
		}
		return true; // Assume true since the action did run it just had nothing to update...
	}

	/**
	 * Count() allows you to count all the documents returned by a certain condition, it is analogous
	 * to $db->collection->find()->count() and basically does exactly that...
	 * @param EMongoCriteria|array $criteria
	 */
	public function count($criteria = array()){
	    $this->trace(__FUNCTION__);

	    // If we provide a manual criteria via EMongoCriteria or an array we do not use the models own DbCriteria
	    $criteria = !empty($criteria) || $criteria instanceof EMongoCriteria ? $criteria : $this->getDbCriteria();
	    if($criteria instanceof EMongoCriteria)
	        $crtieria = $criteria->getCondition();
	    return $this->getCollection()->find(isset($criteria) ? $criteria : array())->count();
	}

	/**
	 * Gives basic searching abilities for things like CGridView
	 *
	 * @param $query allows you to specify a query which should always take hold along with the searched fields
	 */
	public function search($query=array()){
		$this->trace(__FUNCTION__);

		foreach($this->getSafeAttributeNames() as $attribute){

			$value = $this->{$attribute};

			if($value !== null && $value !== '' && (!$value instanceof EMongoArrayModel)){
				if((is_array($value) && count($value)) || is_object($value)){
					$query[$attribute] = $value;
				}elseif(is_string($value)&&preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/',$value,$matches)){
					$value=$matches[2];
					$op=$matches[1];

					switch($op){
						case "<>":
							$query[$attribute] = array('$ne' => $value);
							break;
						case "<=":
							$query[$attribute] = array('$lte' => $value);
							break;
						case ">=":
							$query[$attribute] = array('$gte' => $value);
							break;
						case "<":
							$query[$attribute] = array('$lt' => $value);
							break;
						case ">":
							$query[$attribute] = array('$gt' => $value);
							break;
						case "=":
						default:
							$query[$attribute] = $value;
							break;
					}
				}
			}
		}
		return new EMongoDataProvider(get_class($this), array('criteria' => array('condition' => $query)));
	}

	/**
	 * This is an aggregate helper on the model
	 * Note: This does not return the model but instead the result array directly from MongoDB.
	 * @param array $pipeline
	 */
	public function aggregate($pipeline){
		$this->trace(__FUNCTION__);
		return Yii::app()->mongodb->aggregate($this->collectionName(),$pipeline);
	}

	/**
	 * A distinct helper on the model, this is not the same as the aggregation framework
	 * distinct
	 * @link http://docs.mongodb.org/manual/reference/command/distinct/
	 * @param string $key
	 * @param array $query
	 */
	public function distinct($key, $query = array()){
		$this->trace(__FUNCTION__);
		$c=$this->getDbCriteria();
		if(is_array($c) && isset($c['condition']) && !empty($c['condition']))
			$query=CMap::mergeArray($query, $c['condition']);

		return Yii::app()->mongodb->command(array(
			'distinct' => $this->collectionName(),
			'key' => $key,
			'query' => $query
		));
	}

    /**
     * Refreshes the data from the database
     */
    public function refresh(){

		$this->trace(__FUNCTION__);
		if(!$this->getIsNewRecord() && ($record=$this->getCollection()->findOne(array($this->primaryKey() => $this->getPrimaryKey())))!==null){
			$this->clean();

			foreach($record as $name=>$column)
				$this->$name=$record[$name];
			return true;
		}
		else
			return false;
    }

    /**
     * A bit deceptive, this actually gets the last response from either save() or update(). The reason it is called this
     * is because MongoDB calls it this and so it seems better to have unity on that front.
     */
    public function getLastError(){
		return $this->lastError;
    }

    /**
     * gets and if null sets the db criteria for this model
     * @param $createIfNull
     */
	public function getDbCriteria($createIfNull=true)
	{
		if($this->_criteria===null)
		{
			if(($c=$this->defaultScope())!==array() || $createIfNull)
				$this->_criteria=$c;
			else
				return array();
		}
		return $this->_criteria;
	}

	/**
	 * Sets the db criteria for this model
	 * @param array $criteria
	 */
	public function setDbCriteria($criteria){
		return $this->_criteria=$criteria;
	}

	/**
	 * Merges the currrent DB Criteria with the inputted one
	 * @param array $newCriteria
	 */
	public function mergeDbCriteria($newCriteria){
		 return $this->_criteria=$this->mergeCriteria($this->getDbCriteria(), $newCriteria);
	}

    /**
     * Gets the collection for this model
     */
    public function getCollection(){
		return $this->getDbConnection()->{$this->collectionName()};
    }

    /**
     * Merges two criteria objects. Best used for scopes
     * @param $oldCriteria
     * @param $newCriteria
     */
    public function mergeCriteria($oldCriteria, $newCriteria){
		return CMap::mergeArray($oldCriteria, $newCriteria);
    }

    /**
     * Produces a trace message for functions in this class
     * @param string $func
     */
    public function trace($func){
    	Yii::trace(get_class($this).'.'.$func.'()','extensions.MongoYii.EMongoDocument');
    }
}
