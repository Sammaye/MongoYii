<?php

class EMongoDocument extends EMongoModel{

	private static $_models=array();			// class name => model

	private $_new=false;						// whether this instance is new or not
	//private $_pk;								// old primary key value // THERE CAN NOLY BE ONE!! YARRRRR

	public function __construct($scenario='insert')
	{
		if($scenario===null) // internally used by populateRecord() and model()
			return;

		$this->setScenario($scenario);
		$this->setIsNewRecord(true);

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

		$this->init();

		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}


	public function scopes()
	{
		return array();
	}

	public function defaultScope()
	{
		return array();
	}

	public function resetScope()
	{
		//$this->_criteria
		return $this;
	}

	function collectionName(){  }

	function primaryKey(){
		return '_id';
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
	 * @return CActiveRecord active record model instance.
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

    protected function instantiate($document){
		$class=get_class($this);
		$model=new $class(null);
		return $model;
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
			$record->init();
			foreach($attributes as $name=>$value)
			{
				$record->$name=$value;
			}
			$record->_pk=$record->primaryKey();
			$record->attachBehaviors($record->behaviors());
			if($callAfterFind)
				$record->afterFind();
			return $record;
		}
		else
			return null;
	}

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

	public function save($runValidation=true,$attributes=null){
		if(!$runValidation || $this->validate($attributes))
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		else
			return false;
	}

	public function saveAttributes($attributes)
	{
		if(!$this->getIsNewRecord())
		{
			Yii::trace(get_class($this).'.saveAttributes()','system.db.ar.CActiveRecord');
			$values=array();
			foreach($attributes as $name=>$value)
			{
				if(is_integer($name))
					$values[$value]=$this->$value;
				else
					$values[$name]=$this->$name=$value;
			}
			if($this->_pk===null)
				$this->_pk=$this->getPrimaryKey();
			if($this->updateByPk($this->getOldPrimaryKey(),$values)>0)
			{
				$this->_pk=$this->getPrimaryKey();
				return true;
			}
			else
				return false;
		}
		else
			throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
	}

	public function insert($attributes=null){
		if(!$this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);

			if(!isset($this->_id)) $this->_id = new MonogId();
			if($this->getCollection()->insert($this->getRawDocument(), $this->getDbConnection()->getDefaultWriteConcern())){
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;
	}

	public function update($attributes=null){
		if($this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);
			if($this->_id===null)
				throw new CDbException(Yii::t('yii','The active record cannot be updated because it has no _id.'));

			$this->updateByPk($this->{$this->primaryKey()},$this->getAttributes($attributes));
			$this->afterSave();
			return true;
		}
		else
			return false;
	}

	public function delete(){
		if(!$this->getIsNewRecord()){
			$this->trace(__FUNCTION__);
			if($this->beforeDelete()){
				$result=$this->deleteBy_id($this->{$this->primaryKey()});
				$this->afterDelete();
				return $result;
			}
			else
				return false;
		}
		else
			throw new CDbException(Yii::t('yii','The active record cannot be deleted because it is new.'));
	}

	public function exists($criteria=array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->findOne($criteria)!==null;
	}

	public function findOne($criteria=array()){
		$this->trace(__FUNCTION__);
		if($record=$this->getCollection()->findOne($criteria)!==null)
			return $this->populateRecord($record);
		else
			return null;
	}

    public function find($criteria=array()){
    	$this->trace(__FUNCTION__);
    	return new EMongoCursor($criteria, get_class($this));
    }

    public function findBy_id($_id){
    	$this->trace(__FUNCTION__);
		$_id = $_id instanceof MongoId ? $_id : new MongoId($_id);
		return $this->findOne(array('_id' => $_id));
    }

	public function deleteByPk($pk,$criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->remove(array_mege(array($this->primaryKey() => $pk), $criteria),
					array_mege($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	public function updateByPk($pk, $criteria = array(), $updateDoc = array(), $options = array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->update(array_mege(array($this->primaryKey() => $pk), $criteria), $updateDoc,
				array_mege($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	public function updateAll($criteria=array(),$updateDoc=array(),$options=array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->update($criteria, $updateDoc, array_mege($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

	public function deleteAll($criteria=array(),$options=array()){
		$this->trace(__FUNCTION__);
		return $this->getCollection()->remove($criteria, array_mege($this->getDbConnection()->getDefaultWriteConcern(), $options));
	}

    public function clean(){
    	$this->_attributes=array();
		$this->_related=array();

		// blank class properties
		$cache = $this->getDbConnection()->getObjCache(get_class($this));

		if(isset($cache['document'])){
			foreach($cache['document'] as $field)
				$this->$field = null;
		}

		if(isset($cache['virtual'])){
			foreach($cache['virtual'] as $field)
				$this->$field = null;
		}
		return true;
    }

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

    public function getCollection(){
		return $this->getDbConnection()->{$this->collectionName()};
    }

    public function trace($func){
    	Yii::trace(get_class($this).'.'.$func.'()','extensions.MongoYii.EMongoDocument');
    }
}