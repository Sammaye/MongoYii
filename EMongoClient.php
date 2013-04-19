<?php

/**
 * EMongoClient
 *
 * The MongoDB and MongoClient class combined.
 *
 * Quite deceptively this classes magics actually represents the DATABASE not the connection.
 *
 * Normally this would represent the MongoClient or Mongo and it is even named after them and implements
 * some of their functions but it is not due to the way Yii works.
 */
class EMongoClient extends CApplicationComponent{

	/**
	 * The server string (connection string pre-1.3)
	 * @var string
	 */
	public $server;

	/**
	 * Additional options for the connection constructor
	 * @var array
	 */
	public $options = array();

	/**
	 * The name of the database
	 * @var string
	 */
	public $db;

	/**
	 * Write Concern, will default to acked writes
	 * @see http://php.net/manual/en/mongo.writeconcerns.php
	 * @var int|string
	 */
	public $w = 1;

	/**
	 * Are we using journaled writes here? Beware this makes all writes wait for the journal, it does not
	 * state whether MongoDB is using journaling. Only works 1.3+ driver
	 * @var boolean
	 */
	public $j = false;

	/**
	 * The read preference
	 * The first param is the textual version of the constant name in the MongoClient for the type of read
	 * i.e. RP_PRIMARY and the second emulates the setReadPreference prototypes second parameter
	 * @see http://php.net/manual/en/mongo.readpreferences.php
	 * @var array()
	 */
	public $RP = array('RP_PRIMARY', array());

	/**
	 * The Legacy read preference. DO NOT USE IF YOU ARE ON VERSION 1.3+
	 * @var boolean
	 */
	public $setSlaveOkay = false;

	/**
	 * The Mongo Connection instance
	 * @var Mongo|MongoClient
	 */
	private $_mongo;

	/**
	 * The database instance
	 * @var MongoDB
	 */
	private $_db;

	/**
	 * Caches reflection properties for our objects so we don't have
	 * to keep getting them
	 * @var array
	 */
	private $_objCache = array();

	/**
	 * The default action is to get a collection
	 */
	public function __get($k){
		$getter='get'.$k;
		if(method_exists($this,$getter))
			return $this->$getter();
		return $this->selectCollection($k);
	}

	/**
	 * Will either call a function on the database or call for a collection
	 */
	public function __call($name,$parameters = array()){
		if(method_exists($this->_db, $name)){
			return call_user_func_array(array($this->_db, $name), $parameters);
		}
	}

	public function __construct(){
		// We copy this function to add the subdocument validator as a built in validator
		CValidator::$builtInValidators['subdocument'] = 'ESubdocumentValidator';
	}

	/**
	 * The init function
	 * We also connect here
	 * @see yii/framework/base/CApplicationComponent::init()
	 */
	public function init(){
		parent::init();
		$this->connect();
	}

	/**
	 * Connects to our database
	 */
	public function connect(){

		// We don't need to throw useless exceptions here, the MongoDB PHP Driver has its own checks and error reporting
		// Yii will easily and effortlessly display the errors from the PHP driver, we should only catch its exceptions if
		// we wanna add our own custom messages on top which we don't, the errors are quite self explanatory
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->_mongo = new Mongo($this->server, $this->options);
			$this->_mongo->connect();

			if($this->setSlaveOkay)
				$this->_mongo->setSlaveOkay($this->setSlaveOkay);
		}else{
			$this->_mongo = new MongoClient($this->server, $this->options);

			if(is_array($this->RP)){
				$const = $this->RP[0];
				$opts = $this->RP[1];

				if(!empty($opts)) // I do this due to a bug that exists in some PHP driver versions
					$this->_mongo->setReadPreference(constant('MongoClient::'.$const), $opts);
				else
					$this->_mongo->setReadPreference(constant('MongoClient::'.$const));
			}
		}
	}

	/**
	 * Gets the connection object
	 * @return Mongo|MongoClient
	 */
	public function getConnection(){

		if(empty($this->_mongo))
			$this->connect();

		return $this->_mongo;
	}

	/**
	 * Sets the raw database adhoc
	 * @param $name
	 */
	public function setDB($name){
		$this->_db = $this->getConnection()->selectDb($name);
	}

	/**
	 * Gets the raw Database
	 * @return MongoDB
	 */
	public function getDB(){

		if(empty($this->_db))
			$this->setDB($this->db);

		return $this->_db;
	}

	/**
	 * You should never call this function.
	 *
	 * The PHP driver will handle connections automatically, and will
	 * keep this performant for you.
	 */
	public function close(){
		if(!empty($this->_mongo))
			$this->_mongo->close();
	}

	/**
	 * Since there is no easy definition of the public collection class without drilling down
	 * this function is designed to be a helper to make aggregation calling more standard.
	 * @param $collection
	 * @param $pipelines
	 */
	public function aggregate($collection, $pipelines){
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			return $this->getDB()->command(array(
				'aggregate' => $collection,
				'pipeline' => $pipelines
			));
		}
		return $this->getDB()->$collection->aggregate($pipelines);
	}

	/**
	 * Command helper
	 * @param array|sting $command
	 */
	public function command($command = array()){
		return $this->getDB()->command($command);
	}

	/**
	 * ATM does nothing but the original processing; ATM
	 * @param $name
	 */
	public function selectCollection($name){
		return $this->getDB()->selectCollection($name);
	}

	/**
	 * Sets the document cache for any particular document (EMongoDocument/EMongoModel)
	 * sent in as the first parameter of this function
	 * @param $o
	 */
	function setDocumentCache($o){
		if(
			!$this->getObjCache(get_class($o)) && // Run reflection and cache it if not already there
			(get_class($o) != 'EMongoDocument' && get_class($o) != 'EMongoModel') /* We can't cache the model */
		){
			$virtualFields = array();
			$documentFields = array();

			$reflect = new ReflectionClass(get_class($o));
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
			$this->setObjectCache(get_class($o),
				sizeof($virtualFields) > 0 ? $virtualFields : null,
				sizeof($documentFields) > 0 ? $documentFields : null
			);
		}
	}

	/**
	 * Provides a method by which to set some sort of cache for a model to
	 * remember things such as reflection of fields
	 * @param string $name
	 * @param array $virtualFields
	 * @param array $documentFields
	 */
	public function setObjectCache($name, $virtualFields = null, $documentFields = null){

		if($virtualFields)
			$this->_objCache[$name]['virtual'] = $virtualFields;

		if($documentFields)
			$this->_objCache[$name]['document'] = $documentFields;
	}

	/**
	 * Gets the virtual fields of a model from cache
	 * @param string $name
	 * @return NULL|array
	 */
	public function getVirtualObjCache($name){
		return isset($this->_objCache[$name], $this->_objCache[$name]['virtual']) ? $this->_objCache[$name]['virtual'] : null;
	}

	/**
	 * Gets the field of a model from cache
	 * @param string $name
	 * @return NULL|array
	 */
	public function getFieldObjCache($name){
		return isset($this->_objCache[$name], $this->_objCache[$name]['document']) ? $this->_objCache[$name]['document'] : null;
	}

	/**
	 * Just gets the object cache for a model
	 * @param string $name
	 * @return NULL|array
	 */
	public function getObjCache($name){
		return isset($this->_objCache[$name]) ? $this->_objCache[$name] : null;
	}

	/**
	 * Gets the default write concern options for all queries through active record
	 * @return array
	 */
	public function getDefaultWriteConcern(){
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			if($this->w == 1){
				return array('safe' => true);
			}elseif($this->w > 0){
				return array('safe' => $this->w);
			}
		}else{
			return array('w' => $this->w, 'j' => $this->j);
		}
		return array();
	}

	/**
	 * Create ObjectId from timestamp. This function is not actively used it is
	 * here as a helper for anyone who needs it
	 * @param $yourTimestamp
	 */
	public function createMongoIdFromTimestamp( $yourTimestamp )
	{
	    static $inc = 0;

	    $ts = pack( 'N', $yourTimestamp );
	    $m = substr( md5( gethostname()), 0, 3 );
	    $pid = pack( 'n', posix_getpid() );
	    $trail = substr( pack( 'N', $inc++ ), 1, 3);

	    $bin = sprintf("%s%s%s%s", $ts, $m, $pid, $trail);

	    $id = '';
	    for ($i = 0; $i < 12; $i++ )
	    {
	        $id .= sprintf("%02X", ord($bin[$i]));
	    }
	    return new MongoID($id);
	}

	/**
	 * Set read preference on MongoClient
	 * @param $pref
	 * @param $options
	 */
	public function setReadPreference($pref, $options=array()){
		return $this->getConnection()->setReadPreference($pref, $options);
	}

	/**
	 * setSlaveOkay on Mongo
	 * @param $bool
	 */
	public function setSlaveOkay($bool){
		return $this->getConnection()->setSlaveOkay($bool);
	}
}

class EMongoException extends CException {}