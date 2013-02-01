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

	function __construct($scenario = 'insert'){

		if($scenario===null) // internally used by populateRecord() and model()
			return;

		$this->setScenario($scenario);

		parent::__construct($scenario);

		// Run reflection and cache it if not already there
		if(!$this->getDb()->getObjCache(get_class($this)) && get_class($this) != 'EMongoModel' /* We can't cache the model */){
			$virtualFields = array();
			$documentFields = array();

			$reflect = new \ReflectionClass(get_class($this));
			$class_vars = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED); // Pre-defined doc attributes

			foreach ($class_vars as $prop) {
				$docBlock = $prop->getDocComment();

				// If it is not public and it is not marked as virtual then assume it is document field
				if($prop->isProtected() || preg_match('/@virtual/i', $docBlock) <= 0){
					$documentFields[] = $prop->getName();
				}else{
					$virtualFields[] = $prop->getName();
				}
			}
			$this->getDb()->setObjectCache(get_class($this),
				sizeof($virtualFields) > 0 ? $virtualFields : null,
				sizeof($documentFields) > 0 ? $documentFields : null
			);
		}

		$this->afterConstruct();
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

	function getModel(){

	}

	function getRawModel(){

	}

}
