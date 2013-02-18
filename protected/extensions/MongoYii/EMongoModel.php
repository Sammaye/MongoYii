<?php

/**
 * WHY IS THERE NO FUCKING GOOD NAME FOR THIS FUCKING PIECE OF SHIT
 */
class EMongoModel extends CModel{

	/**
	 * @var EMongoClient the default database connection for all active record classes.
	 * By default, this is the 'mongodb' application component.
	 * @see getDbConnection
	 */
	public static $db;

	private $_errors=array();	// attribute name => array of errors

	private $_attributes = array();
	private $_related = array();

	/**
	 * (non-PHPdoc)
	 * @see yii/framework/CComponent::__get()
	 */
	public function __get($name){

		if(isset($this->_related[$name])){
			return $this->_related[$name];
		}elseif(array_key_exists($name, $this->relations())){
			return $this->_related[$name]=$this->getRelated($name);
		}elseif(isset($this->_attributes[$name])){
			return $this->_attributes[$name];
		}else{
			return parent::__get($name);
		}

	}

	/**
	 * (non-PHPdoc)
	 * @see CComponent::__set()
	 */
	public function __set($name,$value){
		$setter='set'.$name;
		if(method_exists($this,$setter))
			return $this->$setter($value);
		elseif(isset($this->_related[$name]) || array_key_exists($name, $this->relations()))
			$this->_related[$name]=$value;
		elseif($this->setAttribute($name,$value)===false) // It should never be false, this is a white lie
			parent::__set($name,$value);
	}

	/**
	 * (non-PHPdoc)
	 * @see CComponent::__isset()
	 */
	public function __isset($name){

		if(isset($this->_attributes[$name]))
			return true;
		elseif(isset($this->_related[$name]))
			return true;
		elseif(array_key_exists($name, $this->relations()))
			return $this->getRelated($name)!==null;
		else
			return parent::__isset($name);

	}

	/**
	 * (non-PHPdoc)
	 * @see CComponent::__unset()
	 */
	public function __unset($name){

		if(isset($this->_attributes[$name]))
			unset($this->_attributes[$name]);
		elseif(isset($this->_related[$name]))
			unset($this->_related[$name]);
		else
			parent::__unset($name);

	}

	/**
	 * (non-PHPdoc)
	 * @see CComponent::__call()
	 */
	public function __call($name,$parameters)
	{
		if(array_key_exists($name, $this->relations()))
		{
			if(empty($parameters))
				return $this->getRelated($name,false);
			else
				return $this->getRelated($name,false,$parameters[0]);
		}

		return parent::__call($name,$parameters);
	}

	/**
	 * This sets up our model.
	 * Apart from what Yii normally does this also sets a field cache for reflection so that we only ever do reflection once to
	 * understand what fields are in our model.
	 * @param string $scenario
	 */
	function __construct($scenario = 'insert'){

		if($scenario===null) // internally used by populateRecord() and model()
			return;

		$this->setScenario($scenario);

		// Run reflection and cache it if not already there
		if(!$this->getDbConnection()->getObjCache(get_class($this)) && get_class($this) != 'EMongoModel' /* We can't cache the model */){
			$virtualFields = array();
			$documentFields = array();

			$reflect = new \ReflectionClass(get_class($this));
			$class_vars = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED); // Pre-defined doc attributes

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
		
		// We copy this function to add the subdocument validator as a built in validator
		CValidator::$builtInValidators['subdocument'] = 'ESubdocumentValidator';		

		$this->init();

		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	/**
	 * Initializes this model.
	 * This method is invoked when an AR instance is newly created and has
	 * its {@link scenario} set.
	 * You may override this method to provide code that is needed to initialize the model (e.g. setting
	 * initial property values.)
	 */
	public function init(){ return true; }

	/**
	 * (non-PHPdoc)
	 * @see CModel::attributeNames()
	 */
	function attributeNames(){

		$fields = $this->getDbConnection()->getFieldObjCache(get_class($this));
		$virtuals = $this->getDbConnection()->getVirtualObjCache(get_class($this));

		$cols = array_merge(is_array($fields) ? $fields : array(), is_array($virtuals) ? $virtuals : array(), array_keys($this->_attributes));
		return $cols!==null ? $cols : array();
	}

	/**
	 * Holds all our relations
	 * @return array
	 */
	function relations(){ return array(); }

	/**
	 * Finds out if a document attributes actually exists
	 * @param string $name
	 */
	public function hasAttribute($name)
	{
		$attrs = $this->_attributes;
		$fields = $this->getDbConnection()->getFieldObjCache(get_class($this));
		return isset($attrs[$name])||isset($fields[$name])?true:false;
	}

	/**
	 * Sets the attribute of the model
	 * @param string $name
	 * @param mixed $value
	 */
	public function setAttribute($name,$value){

		if(property_exists($this,$name))
			$this->$name=$value;
		else//if(isset($this->_attributes[$name]))
			$this->_attributes[$name]=$value;
		//else return false;
		return true;

	}

	/**
	 * Gets a document attribute
	 * @param string $name
	 */
	public function getAttribute($name){
		if(property_exists($this,$name))
			return $this->$name;
		elseif(isset($this->_attributes[$name]))
			return $this->_attributes[$name];
	}

	/**
	 * (non-PHPdoc)
	 * @see CModel::getAttributes()
	 */
	public function getAttributes($names=true)
	{
		$attributes=$this->_attributes;
		$fields = $this->getDbConnection()->getFieldObjCache(get_class($this));

		if(is_array($fields)){
			foreach($fields as $name=>$column)
			{
				if(property_exists($this,$name))
					$attributes[$name]=$this->$name;
				elseif($names===true && !isset($attributes[$name]))
					$attributes[$name]=null;
			}
		}
		if(is_array($names))
		{
			$attrs=array();
			foreach($names as $name)
			{
				if(property_exists($this,$name))
					$attrs[$name]=$this->$name;
				else
					$attrs[$name]=isset($attributes[$name])?$attributes[$name]:null;
			}
			return $attrs;
		}
		else
			return $attributes;
	}

	/**
	 * Returns the related record(s).
	 * This method will return the related record(s) of the current record.
	 * If the relation is 'one' it will return a single object
	 * or null if the object does not exist.
	 * If the relation is 'many' it will return an array of objects
	 * or an empty iterator.
	 * @param string $name the relation name (see {@link relations})
	 * @param boolean $refresh whether to reload the related objects from database. Defaults to false.
	 * @param mixed $params array with additional parameters that customize the query conditions as specified in the relation declaration.
	 * @return mixed the related object(s).
	 * @throws EMongoException if the relation is not specified in {@link relations}.
	 */
	public function getRelated($name,$refresh=false,$params=array())
	{
		if(!$refresh && $params===array() && (isset($this->_related[$name]) || array_key_exists($name,$this->_related)))
			return $this->_related[$name];

		$relations = $this->relations();

		if(!isset($relations[$name]))
			throw new EMongoException(Yii::t('yii','{class} does not have relation "{name}".',
				array('{class}'=>get_class($this), '{name}'=>$name)));

		Yii::trace('lazy loading '.get_class($this).'.'.$name,'extensions.MongoYii.EMongoModel');

		// I am unsure as to the purpose of this bit
		//if($this->getIsNewRecord() && !$refresh && ($relation instanceof CHasOneRelation || $relation instanceof CHasManyRelation))
			//return $relation instanceof CHasOneRelation ? null : array();

		$cursor = array();
		$relation = $relations[$name];

		// Let's get the parts of the relation to understand it entirety of its context
		$cname = $relation[1];
		$fkey = $relation[2];
		$pk = isset($relation['on']) ? $this->{$relation['on']} : $this->{$this->primaryKey()};

		// Form the where clause
		$where = array();
		if(isset($relation['where'])) $where = array_merge($relation['where'], $params);

		// Find out what the pk is and what kind of condition I should apply to it
		if(is_array($pk)){

			// It is an array of _ids
			$clause = array_merge($where, array($fkey=>array('$in' => $pk)));
		}elseif($pk instanceof MongoDBRef){

			// If it is a DBRef I can only get one doc so I should probably just return it here
			// otherwise I will continue on
			$row = $pk::get();
			if(isset($row['_id'])){
				$o = $cname::model();
				$o->setAttributes($row);
				return $o;
			}
			return null;

		}else{

			// It is just one _id
			$clause = array_merge($where, array($fkey=>$pk));
		}

		$o = $cname::model();
		if($relation[0]==='one'){

			// Lets find it and return it
			$cursor = $o->findOne($clause);
		}elseif($relation[0]==='many'){

			// Lets find them and return them
			$cursor = $o->find($clause);
		}
		return $cursor;
	}

	/**
	 * Returns a value indicating whether the named related object(s) has been loaded.
	 * @param string $name the relation name
	 * @return boolean a value indicating whether the named related object(s) has been loaded.
	 */
	public function hasRelated($name)
	{
		return isset($this->_related[$name]) || array_key_exists($name,$this->_related);
	}

	/**
	 * (non-PHPdoc)
	 * @see CModel::validate()
	 */
	public function validate($attributes=null, $clearErrors=true)
	{
		if($clearErrors)
			$this->clearErrors();
		if($this->beforeValidate())
		{
			foreach($this->getValidators() as $validator)
				$validator->validate($this,$attributes);
			$this->afterValidate();
			return !$this->hasErrors();
		}
		else
			return false;
	}

	function setAttributeErrors($attribute, $errors){
		$this->_errors[$attribute]=$errors;
	}

	/**
	 * Returns the database connection used by active record.
	 * By default, the "mongodb" application component is used as the database connection.
	 * You may override this method if you want to use a different database connection.
	 * @return EMongoClient the database connection used by active record.
	 */
	public function getDbConnection()
	{
		if(self::$db!==null)
			return self::$db;
		else
		{
			self::$db=Yii::app()->mongodb;
			if(self::$db instanceof EMongoClient)
				return self::$db;
			else
				throw new EMongoException(Yii::t('yii','MongoDB Active Record requires a "mongodb" EMongoClient application component.'));
		}
	}

	/**
	 * Gets the formed document with MongoYii objects included
	 */
	function getDocument(){

		$attributes = $this->getDbConnection()->getFieldObjCache(get_class($this));
		$doc = array();

		foreach($attributes as $field)
			$doc[$field] = $this->$field;
		return array_merge($doc, $this->_attributes);
	}

	/**
	 * Gets the raw document with MongoYii objects taken out
	 */
	function getRawDocument(){
		return $this->filterRawDocument($this->getDocument());
	}

	/**
	 * Filters a provided document to take out MongoYii objects.
	 * @param array $doc
	 */
	function filterRawDocument($doc){
		if(is_array($doc)){
			foreach($doc as $k => $v){
				if(is_array($v)){
					$doc[$k] = $this->{__FUNCTION__}($doc[$k]);
				}elseif($v instanceof EMongoModel || $v instanceof EMongoDocument){
					$doc[$k] = $doc[$k]->getRawDocument();
				}
			}
		}
		return $doc;
	}

	/**
	 * Gets the JSON encoded document
	 */
	function getJSONDocument(){
		return json_encode($this->getRawDocument());
	}

	/**
	 * Gets the BSON encoded document (never normally needed)
	 */
	function getBSONDocument(){
		return bson_encode($this->getRawDocument());
	}
}
