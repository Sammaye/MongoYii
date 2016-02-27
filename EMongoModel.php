<?php

class EMongoModel extends CModel
{
	/**
	 * @var EMongoClient the default database connection for all active record classes.
	 * By default, this is the 'mongodb' application component.
	 * @see getDbConnection
	 */
	public static $db;

	private $_errors = array();	// attribute name => array of errors

	private $_attributes = array();
	
	private $_related = array();

	private $_partial = false;

	/**
	 * @see yii/framework/CComponent::__get()
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		if(isset($this->_attributes[$name])){
			return $this->_attributes[$name];
		}
		if(isset($this->_related[$name])){
			return $this->_related[$name];
		}
		if(array_key_exists($name, $this->relations())){
			return $this->_related[$name] = $this->getRelated($name);
		}
		try{
			return parent::__get($name);
		}catch(CException $e){
			$getter = 'get' . $name;
			if(method_exists($this, $getter)){
				throw $e;
			}elseif(strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)){
				throw $e;
			}
			return null;
		}
	}

	/**
	 * @see CComponent::__set()
	 * @param string $name
	 * @param mixed $value
	 * @return mixed
	 */
	public function __set($name, $value)
	{
		if(isset($this->_related[$name]) || array_key_exists($name, $this->relations())){
			return $this->_related[$name] = $value;
		}
		// This might be a little unperformant actually since Yiis own active record detects
		// If an attribute can be set first to ensure speed of accessing local variables...hmmm
		try{
			return parent::__set($name, $value);
		}catch(CException $e){
			return $this->setAttribute($name, $value);
		}
	}

	/**
	 * @see CComponent::__isset()
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		if(isset($this->_attributes[$name])){
			return true;
		}
		if(isset($this->_related[$name])){
			return true;
		}
		if(array_key_exists($name, $this->relations())){
			return $this->getRelated($name) !== null;
		}
		return parent::__isset($name);
	}

	/**
	 * @see CComponent::__unset()
	 * @param string $name
	 * @return void
	 */
	public function __unset($name)
	{
		if(isset($this->_attributes[$name])){
			unset($this->_attributes[$name]);
		}elseif(isset($this->_related[$name])){
			unset($this->_related[$name]);
		}else{
			parent::__unset($name);
		}
	}

	/**
	 * @see CComponent::__call()
	 * @param string $name
	 * @param array $parameters
	 * @return mixed
	 */
	public function __call($name,$parameters)
	{
		if(!array_key_exists($name, $this->relations())){
			return parent::__call($name,$parameters);
		}
		if(empty($parameters)){
			return $this->getRelated($name, false);
		}
		return $this->getRelated($name, false, $parameters[0]);
	}

	/**
	 * This sets up our model.
	 * Apart from what Yii normally does this also sets a field cache for reflection so that we only ever do reflection once to
	 * understand what fields are in our model.
	 * @param string $scenario
	 */
	public function __construct($scenario = 'insert')
	{
		$this->getDbConnection()->setDocumentCache($this);

		if($scenario === null){ // internally used by populateRecord() and model()
			return;
		}

		$this->setScenario($scenario);
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
	 * @return bool
	 */
	public function init()
	{
		return true;
	}

	/**
	 * @see CModel::attributeNames()
	 * @return array
	 */
	public function attributeNames()
	{
		$fields = $this->getDbConnection()->getFieldCache(get_class($this), true);
		$cols = array_merge($fields, array_keys($this->_attributes));
		return $cols !== null ? $cols : array();
	}

	/**
	 * Holds all our relations
	 * @return array
	 */
	public function relations()
	{
		return array();
	}

	/**
	 * Finds out if a document attributes actually exists
	 * @param string $name
	 * @return bool
	 */
	public function hasAttribute($name)
	{
		$attrs = $this->_attributes;
		$fields = $this->getDbConnection()->getFieldCache(get_class($this));
		return isset($attrs[$name]) || isset($fields[$name]) || property_exists($this, $name) ? true : false;
	}

	/**
	 * Sets the attribute of the model
	 * @param string $name
	 * @param mixed $value
	 * @return bool
	 */
	public function setAttribute($name, $value)
	{
		if(property_exists($this,$name)){
			$this->$name = $value;
		}else{
			$this->_attributes[$name] = $value;
		}
		return true;
	}

	/**
	 * Gets a document attribute
	 * @param string $name
	 * @return mixed
	 */
	public function getAttribute($name)
	{
		if(property_exists($this, $name)){
			return $this->$name;
		}
		if(isset($this->_attributes[$name])){
			return $this->_attributes[$name];
		}
		return null;
	}

	/**
	 * @see CModel::getAttributes()
	 * @param bool $names
	 * @return array
	 */
	public function getAttributes($names = true)
	{
		$attributes = $this->_attributes;
		$fields = $this->getDbConnection()->getFieldCache(get_class($this));

		if(is_array($fields)){
			foreach($fields as $name){
				$attributes[$name] = $this->$name;
			}
		}

		if(!is_array($names)){
			return $attributes;
		}
		$attrs = array();
		foreach($names as $name){
			if(property_exists($this, $name)){
				$attrs[$name] = $this->$name;
			}else{
				$attrs[$name] = isset($attributes[$name]) ? $attributes[$name] : null;
			}
		}
		return $attrs;
	}

	/**
	 * Sets the attribute values in a massive way.
	 * @param array $values attribute values (name=>value) to be set.
	 * @param boolean $safeOnly whether the assignments should only be done to the safe attributes.
	 * A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
	 * @see getSafeAttributeNames
	 * @see attributeNames
	 */
	public function setAttributes($values, $safeOnly = true)
	{
		if(!is_array($values)){
			return;
		}
		$attributes = array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());
		$_meta = $this->getDbConnection()->getDocumentCache(get_class($this));
		foreach($values as $name => $value){
			$field_meta = isset($_meta[$name]) ? $_meta[$name] : array();
			if($safeOnly){
				if(isset($attributes[$name])){
					$this->$name = !is_bool($value) && !is_array($value) && !is_object($value) && preg_match('/^([0-9]|[1-9]{1}\d+)$/' /* Will only match real integers, unsigned */, $value) > 0
						&& ( (PHP_INT_MAX > 2147483647 && (string)$value < '9223372036854775807') /* If it is a 64 bit system and the value is under the long max */
						|| (string)$value < '2147483647' /* value is under 32bit limit */) ? (int)$value : $value;
				}elseif($safeOnly){
					$this->onUnsafeAttribute($name, $value);
				}
			}else{
				$this->$name = !is_bool($value) && !is_array($value) && !is_object($value) && preg_match('/^([0-9]|[1-9]{1}\d+)$$/' /* Will only match real integers, unsigned */, $value) > 0
					&& ( (PHP_INT_MAX > 2147483647 && (string)$value < '9223372036854775807') || (string)$value < '2147483647') ? (int)$value : $value;
			}
		}
	}

	/**
	 * Sets the attributes to be null.
	 * @param array $names list of attributes to be set null. If this parameter is not given,
	 * all attributes as specified by {@link attributeNames} will have their values unset.
	 * @since 1.1.3
	 */
	public function unsetAttributes($names = null)
	{
		if($names === null){
			$names = $this->attributeNames();
		}
		foreach($names as $name){
			$this->$name = null;
		}
	}
	
	/**
	 * Allows for mass assignment of the record in question
	 */
	public function populateRecord($attributes, $runEvent = true)
	{
		if($attributes === false || $attributes === null){
			return null;
		}
		
		$record = new $this;
		$record->setScenario('update');
		
		foreach($attributes as $name => $value){
			$record->setAttribute($name, $value);
		}
		
		$record->init();
		$record->attachBehaviors($record->behaviors());

		if($runEvent){
			$record->afterConstruct();
		}
		return $record;
	}

	/**
	 * Sets whether or not this is a partial document
	 * @param $partial
	 */
	public function setIsPartial($partial)
	{
		$this->_partial = $partial;
	}

	/**
	 * Gets whether or not this is a partial document, i.e. it only has some
	 * of its fields present
	 */
	public function getIsPartial()
	{
		return $this->_partial;
	}

	/**
	 * You can change the primarykey but due to how MongoDB
	 * actually works this IS NOT RECOMMENDED
	 */
	public function primaryKey()
	{
		return '_id';
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
	public function getRelated($name, $refresh = false, $params = array())
	{
		if(!$refresh && $params === array() && (isset($this->_related[$name]) || array_key_exists($name, $this->_related))){
			return $this->_related[$name];
		}

		$relations = $this->relations();

		if(!isset($relations[$name])){
			throw new EMongoException(
				Yii::t(
					'yii',
					'{class} does not have relation "{name}".',
					array('{class}' => get_class($this), '{name}' => $name)
				)
			);
		}
		Yii::trace('lazy loading ' . get_class($this) . '.' . $name, 'extensions.MongoYii.EMongoModel');

		$cursor = array();
		$relation = $relations[$name];

		// Let's get the parts of the relation to understand it entirety of its context
		$cname = $relation[1];
		$fkey = $relation[2];
		$pk = isset($relation['on']) ? $this->{$relation['on']} : $this->getPrimaryKey();
		$pkName = isset($relation['on']) ? $relation['on'] : $this->primaryKey();

		// This will detect . notation key names like AuthorName.id
		if(strpos($pkName, '.') !== false){
			
			$pk = [];
			
			$parts = explode('.', $pkName);
			
			if($this->hasAttribute($parts[0])){
			
				$val = $this->{$parts[0]};
			
				if(!is_array($val) && !is_object($val)){
					// continue
				}elseif(is_object($val) && property_exists($val, $parts[1])){
					$pk[] = $val->{$parts[1]};
				}elseif(is_array($val) && isset($val[$parts[1]])){
					$pk[] = $val[$parts[1]];
				}else{
					foreach($val as $k => $v){
						if(is_array($v) && isset($v[$parts[1]])){
							$pk[] = $v[$parts[1]];
						}elseif(is_object($v) && property_exists($v, $parts[1])){
							$pk[] = $v->{$parts[1]};
						}
					}
				}
			}
		}
		
		// This takes care of cases where the PK is an DBRef and only one DBRef, where it could 
		// be mistaken as a multikey field 
        if($relation[0] === 'one' && is_array($pk) && array_key_exists('$ref', $pk)){
            $pk = array($pk);
        }
        
		// Form the where clause
		$where = $params;
		if(isset($relation['where']) && !$params){
			$where = array_merge($relation['where'], $params);
		}
		
		// Find out what the pk is and what kind of condition I should apply to it
		if(is_array($pk)){
			//It is an array of references
			if(MongoDBRef::isRef(reset($pk))){
				$result = array();
				foreach($pk as $singleReference){
					$row = $this->populateReference($singleReference, $cname);
					
					// When $row does not exists it will return null. It will not add it to $result
					array_push($result, $row);
				}
				
				// When $row is null count($result) will be 0 and $result will be an empty array
				// Because we are a one relation we want to return null when a row does not exists
				// Currently it was returning an empty array
				
				if($relation[0] === 'one' && count($result) > 0){
					$result = $result[0];
				}
				return $this->_related[$name] = $result;
			}
			// It is an array of _ids
			$clause = array_merge($where, array($fkey => array('$in' => $pk)));
		}elseif($pk instanceof MongoDBRef){
			// I should probably just return it here
			// otherwise I will continue on
			return $this->_related[$name] = $this->populateReference($pk, $cname);
		}else{
			// It is just one _id
			$clause = array_merge($where, array($fkey => $pk));
		}

		$o = $cname::model($cname);
		if($relation[0] === 'one'){
			// Lets find it and return it
			return $this->_related[$name] = $o->findOne($clause);
		}elseif($relation[0] === 'many'){
			// Lets find them and return them
			$cursor = $o->find($clause)
				->sort(isset($relation['sort']) ? $relation['sort'] : array())
				->skip(isset($relation['skip']) ? $relation['skip'] : null)
				->limit(isset($relation['limit']) ? $relation['limit'] : null);
			
			if(!isset($relation['cache']) || $relation['cache'] === true){
				return $this->_related[$name] = iterator_to_array($cursor);
			}
		}
		return $cursor; // FAIL SAFE
	}

	/**
	 * @param mixed $reference Reference to populate
	 * @param null|string $cname Class of model to populate. If not specified, populates data on current model
	 * @return EMongoModel
	 */
	public function populateReference($reference, $cname = null)
	{
		$row = MongoDBRef::get(self::$db->getDB(), $reference);
		$o = (is_null($cname)) ? $this : $cname::model();
		return $o->populateRecord($row);
	}

	/**
	 * Returns a value indicating whether the named related object(s) has been loaded.
	 * @param string $name the relation name
	 * @return boolean a value indicating whether the named related object(s) has been loaded.
	 */
	public function hasRelated($name)
	{
		return isset($this->_related[$name]) || array_key_exists($name, $this->_related);
	}

	/**
	 * Sets the errors for that particular attribute
	 * @param string $attribute
	 * @param array $errors
	 */
	public function setAttributeErrors($attribute, $errors)
	{
		$this->_errors[$attribute] = $errors;
	}

	/* THESE ERROR FUNCTIONS ARE ONLY HERE BECAUSE OF THE WAY IN WHICH PHP RESOLVES THE THE SCOPES OF VARS */
	// I needed to add the error handling function above but I had to include these as well

	/**
	 * Returns a value indicating whether there is any validation error.
	 * @param string $attribute attribute name. Use null to check all attributes.
	 * @return boolean whether there is any error.
	 */
	public function hasErrors($attribute = null)
	{
		if($attribute === null){
			return $this->_errors !== array();
		}
		return isset($this->_errors[$attribute]);
	}

	/**
	 * Returns the errors for all attribute or a single attribute.
	 * @param string $attribute attribute name. Use null to retrieve errors for all attributes.
	 * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
	 */
	public function getErrors($attribute = null)
	{
		if($attribute === null){
			return $this->_errors;
		}
		$attribute = trim(strtr($attribute, '][', '['), ']');
		if(strpos($attribute, '[') !== false){
			$prev = null;
			foreach(explode('[',$attribute) as $piece){
				if($prev === null && isset($this->errors[$piece])){
					$prev = $this->_errors[$piece];
				}elseif(isset($prev[$piece])){
					$prev = is_array($prev) ? $prev[$piece] : $prev->$piece;
				}
			}
			return $prev === null ? array() : $prev;
		}
		return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : array();
	}

	/**
	 * Returns the first error of the specified attribute.
	 * @param string $attribute attribute name.
	 * @return string the error message. Null is returned if no error.
	 */
	public function getError($attribute)
	{
		$attribute = trim(strtr($attribute, '][', '['), ']');
		if(strpos($attribute, '[') === false){
			return isset($this->_errors[$attribute]) ? reset($this->_errors[$attribute]) : null;
		}
		$prev = null;
		foreach(explode('[', $attribute) as $piece){
			if($prev === null && isset($this->_errors[$piece])){
				$prev = $this->_errors[$piece];
			}elseif(isset($prev[$piece])){
				$prev = is_array($prev) ? $prev[$piece] : $prev->$piece;
			}
		}
		return $prev === null ? null : reset($prev);
	}

	/**
	 * Adds a new error to the specified attribute.
	 * @param string $attribute attribute name
	 * @param string $error new error message
	 */
	public function addError($attribute,$error)
	{
		$this->_errors[$attribute][] = $error;
	}

	/**
	 * Adds a list of errors.
	 * @param array $errors a list of errors. The array keys must be attribute names.
	 * The array values should be error messages. If an attribute has multiple errors,
	 * these errors must be given in terms of an array.
	 * You may use the result of {@link getErrors} as the value for this parameter.
	 */
	public function addErrors($errors)
	{
		foreach($errors as $attribute => $error){
			if(is_array($error)){
				foreach($error as $e){
					$this->addError($attribute, $e);
				}
			}else{
				$this->addError($attribute, $error);
			}
		}
	}

	/**
	 * Removes errors for all attributes or a single attribute.
	 * @param string $attribute attribute name. Use null to remove errors for all attribute.
	 */
	public function clearErrors($attribute = null)
	{
		if($attribute === null){
			$this->_errors = array();
		}else{
			unset($this->_errors[$attribute]);
		}
	}

	/**
	 * Returns the database connection used by active record.
	 * By default, the "mongodb" application component is used as the database connection.
	 * You may override this method if you want to use a different database connection.
	 * @return EMongoClient - the database connection used by active record.
	 * @throws EMongoException
	 */
	public function getDbConnection()
	{
		if(self::$db !== null){
			return self::$db;
		}
		self::$db = $this->getMongoComponent();
		if(self::$db instanceof EMongoClient){
			return self::$db;
		}
		throw new EMongoException(Yii::t('yii', 'MongoDB Active Record requires a "mongodb" EMongoClient application component.'));
	}
	
	/**
	 * This allows you to define a custom override by default for models and the such
	 */
	public function getMongoComponent()
	{
		return Yii::app()->mongodb;
	}

	/**
	 * Cleans or rather resets the document
	 * @return bool
	 */
	public function clean()
	{
		$this->_attributes = array();
		$this->_related = array();

		// blank class properties
		$cache = $this->getDbConnection()->getDocumentCache(get_class($this));
		foreach($cache as $k => $v){
			$this->$k = null;
		}
		return true;
	}

	/**
	 * Gets the formed document with MongoYii objects included
	 * @return array
	 */
	public function getDocument()
	{
		$attributes = $this->getDbConnection()->getFieldCache(get_class($this));
		$doc = array();

		if(is_array($attributes)){
			foreach($attributes as $field){
				$doc[$field] = $this->$field;
			}
		}
		return array_merge($doc, $this->_attributes);
	}

	/**
	 * Gets the raw document with MongoYii objects taken out
	 * @return array
	 */
	public function getRawDocument()
	{
		return $this->filterRawDocument($this->getDocument());
	}

	/**
	 * Filters a provided document to take out MongoYii objects.
	 * @param array $doc
	 * @return array
	 */
	public function filterRawDocument($doc)
	{
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
	public function getJSONDocument()
	{
		return json_encode($this->getRawDocument());
	}

	/**
	 * Gets the BSON encoded document (never normally needed)
	 */
	public function getBSONDocument()
	{
		return bson_encode($this->getRawDocument());
	}
}