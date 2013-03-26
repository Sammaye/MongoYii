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
	private $_criteria = array();

	/**
	 * Sets up our model and set the field cache just like in EMongoModel
	 *
	 * It will also set the default scope on the model so be aware that if you want the default scope to not be applied you will
	 * need to run resetScope() straight after making this model
	 *
	 * @param string $scenario
	 */
	public function __construct($scenario='insert')
	{

		// Run reflection and cache it if not already there
		if(!$this->getDbConnection()->getObjCache(get_class($this)) && get_class($this) != 'EMongoDocument' /* We can't cache the model */){
			$virtualFields = array();
			$documentFields = array();

			$reflect = new ReflectionClass(get_class($this));
			$class_vars = $reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED); // Pre-defined doc attributes

			foreach ($class_vars as $prop) {

				if($prop->isStatic())
					continue;

				$docBlock = $prop->getDocComment();

				// If it is not public and it is not marked as virtual then assume it is document field
				if($prop->isProtected() || preg_match('/@virtual/i', $docBlock) <= 0){
					$documentFields[] = $prop->getName();
				}else{
					$virtualFields[] = $prop->getName();
				}
			}
			$this->getDbConnection()->setObjectCache(get_class($this),
				sizeof($virtualFields) > 0 ? $virtualFields : null,
				sizeof($documentFields) > 0 ? $documentFields : null
			);
		}

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
	public function resetScope()
	{
		$this->_criteria = array();
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
	 * @param string|MongoId $value
	 * @return MongoId
	 */
	public function getMongoId($value){
		return $value instanceof MongoId ? $value : new MongoId($value);
	}

	/**
	 * Returns the value of the primary key
	 */
	public function getPrimaryKey(){
		return $this->{$this->primaryKey()};
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
	public function populateRecord($attributes,$callAfterFind=true)
	{
		if($attributes!==false)
		{
			$record=$this->instantiate($attributes);
			$record->setScenario('update');
			$record->setIsNewRecord(false);
			$record->init();
			foreach($attributes as $name=>$value)
			{
				$record->$name=$value;
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
			if(!isset($this->_id) || $this->_id===null)
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

			if(!isset($this->_id)) $this->_id = new MongoId;
			if($this->getCollection()->insert($this->getRawDocument(), $this->getDbConnection()->getDefaultWriteConcern())){
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
			if($this->_id===null)
				throw new CDbException(Yii::t('yii','The active record cannot be updated because it has no _id.'));

			if($attributes!==null){
				$attributes=$this->getAttributes($attributes);
				unset($attributes['_id']);
				$this->updateByPk($this->{$this->primaryKey()}, array('$set' => $attributes));
			}else
				$this->getCollection()->save($this->getAttributes($attributes));
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
	public function findOne($criteria=array()){
		$this->trace(__FUNCTION__);

		$c=$this->getDbCriteria();
		if((
			$record=$this->getCollection()->findOne($this->mergeCriteria(
										isset($c['condition']) ? $c['condition'] : array(), $criteria
		)))!==null){

			$this->resetScope();
			return $this->populateRecord($record);
		}else
			return null;
	}

	/**
	 * Find some records
	 * @param array $criteria
	 */
    public function find($criteria=array()){
    	$this->trace(__FUNCTION__);

    	$c=$this->getDbCriteria();
    	if($c!==array()){
    		$cursor = new EMongoCursor($this, $this->mergeCriteria(isset($c['condition']) ? $c['condition'] : array(), $criteria));
			if(isset($c['sort'])) $cursor->sort($c['sort']);
    		if(isset($c['skip'])) $cursor->skip($c['skip']);
    		if(isset($c['limit'])) $cursor->limit($c['limit']);

    		$this->resetScope();
	   		return $cursor;
    	}else{
    		return new EMongoCursor($this, $criteria);
    	}
    }

    /**
     * Finds one by _id
     * @param $_id
     */
    public function findBy_id($_id){
    	$this->trace(__FUNCTION__);
		$_id = $this->getMongoId($_id);
		return $this->findOne(array('_id' => $_id));
    }

    /**
     * An alias for findBy_id() that relates to Yiis own findByPk
     * @param $pk
     */
    public function findByPk($pk){
    	$this->trace(__FUNCTION__);
		return $this->findBy_id($pk);
    }

    /**
     * Delete record by pk
     * @param $pk
     * @param $criteria
     * @param $options
     */
	public function deleteByPk($pk,$criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);

		$pk = $this->getMongoId($pk);
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

		$pk = $this->getMongoId($pk);
		return $this->getCollection()->update($this->mergeCriteria($criteria, array($this->primaryKey() => $pk)),$updateDoc,
				array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * Update all records matching a criteria
	 * @param array $criteria
	 * @param array $updateDoc
	 * @param array $options
	 */
	public function updateAll($criteria=array(),$updateDoc=array(),$options=array('multi'=>true)){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->update($criteria, $updateDoc, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	/**
	 * Delete all records matching a criteria
	 * @param array $criteria
	 * @param array $options
	 */
	public function deleteAll($criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->remove($criteria, array_merge($this->getDbConnection()->getDefaultWriteConcern(), $options));
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
			if($value !== null && $value !== ''){
				if(is_array($value) || is_object($value)){
					$query[$attribute] = $value;
				}elseif(preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/',$value,$matches)){
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
     * Refreshes the data from the database
     */
    public function refresh(){

		$this->trace(__FUNCTION__);
		if(!$this->getIsNewRecord() && ($record=$this->getCollection()->findOne(array('_id' => $this->_id)))!==null){
			$this->clean();

			foreach($record as $name=>$column)
				$this->$name=$record[$name];
			return true;
		}
		else
			return false;
    }

    /**
     * gets and if null sets the db criteria for this model
     * @param $createIfNull
     */
	public function getDbCriteria($createIfNull=true)
	{
		if(empty($this->_criteria))
		{
			if(($c=$this->defaultScope())!==array() || $createIfNull)
				$this->_criteria=$c;
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