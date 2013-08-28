<?php
/**
 * EMongoClient
 *
 * The MongoDB and MongoClient class combined.
 *
 * Quite deceptively the magic functions of this class actually represent the DATABASE not the connection.
 * This is in contrast to MongoClient whos' own represent the SERVER.
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
	 * state whether MongoDB is using journaling. Note: this is NOT straight to disk, 
	 * it infact makes the journal to disk time a third of its normal time (anywhere between 2-30ms). 
	 * Only works 1.3+ driver
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
	private $_meta = array();

	/**
	 * The default action is to find a getX whereby X is the $k param 
	 * you input. The secondary function, if not getter found, is to get a collection
	 */
	public function __get($k){
		$getter='get'.$k;
		if(method_exists($this,$getter))
			return $this->$getter();
		return $this->selectCollection($k);
	}

	/**
	 * Will call a function on the database or error out stating that the function does not exist
	 */
	public function __call($name,$parameters = array()){
		if(method_exists($this->getDB(), $name)){
			return call_user_func_array(array($this->getDB(), $name), $parameters);
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
	 * Use this to access the Mongo/MongoClient instance within the extension
	 * @return Mongo|MongoClient
	 */
	public function getConnection(){

		if(empty($this->_mongo))
			$this->connect();

		return $this->_mongo;
	}

	/**
	 * Sets the raw database adhoc
	 * @param string $name
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
	 * This function is designed to be a helper to make calling the aggregate command 
	 * more standard across all drivers.
	 * @param string $collection
	 * @param $pipelines
	 * @return array
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
	 * @param array|string $command
	 * @return array
	 */
	public function command($command = array()){
		return $this->getDB()->command($command);
	}

	/**
	 * ATM does nothing but the original processing; ATM
	 * @param string $name
	 * @return MongoCollection
	 */
	public function selectCollection($name){
		return $this->getDB()->selectCollection($name);
	}

	/**
	 * Sets the document cache for any particular document (EMongoDocument/EMongoModel)
	 * sent in as the first parameter of this function. Will not cache actual EMongoDocument/EMongoModel instances 
	 * only active classes that inherit these
	 * @param $o
	 */
	function setDocumentCache($o){
		if(
			$this->getDocumentCache(get_class($o))===array() && // Run reflection and cache it if not already there
			(get_class($o) != 'EMongoDocument' && get_class($o) != 'EMongoModel') /* We can't cache the model */
		){

			$_meta = array();

			$reflect = new ReflectionClass(get_class($o));
			$class_vars = $reflect->getProperties(ReflectionProperty::IS_PUBLIC); // Pre-defined doc attributes

			foreach ($class_vars as $prop) {

				if($prop->isStatic())
					continue;

				$docBlock = $prop->getDocComment();
				$field_meta = array(
					'name' => $prop->getName(),
					'virtual' => $prop->isProtected() || preg_match('/@virtual/i', $docBlock) <= 0 ? false : true
				);

				// Lets fetch the data type for this field
				// Since we always fetch the data type for this field we make a regex that will only pick out the first
				if(preg_match('/@var ([a-zA-Z]+)/', $docBlock, $matches) > 0){
					$field_meta['type'] = $matches[1];
				}
				$_meta[$prop->getName()] = $field_meta;
			}
			$this->_meta[get_class($o)] = $_meta;
		}
	}

	/**
	 * Get a list of the fields (attributes) for a document from cache
	 * @param string $name
	 * @param boolean $include_virtual
	 * @return array
	 */
	public function getFieldCache($name, $include_virtual = false){
		$doc = isset($this->_meta[$name]) ? $this->_meta[$name] : array();
		$fields = array();
		
		foreach($doc as $name => $opts)
			if($include_virtual || !$opts['virtual']) $fields[] = $name;
		return $fields;		
	}

	/**
	 * Just gets the document cache for a model
	 * @param string $name
	 * @return array
	 */
	public function getDocumentCache($name){
		return isset($this->_meta[$name]) ? $this->_meta[$name] : array();
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
	 * @param int $yourTimestamp
	 * @return MongoID
	 */
	public function createMongoIdFromTimestamp($yourTimestamp)
	{
	    static $inc = 0;

	    $ts = pack( 'N', $yourTimestamp );
	    $m = substr( md5( gethostname()), 0, 3 );
	    $pid = pack( 'n', getmypid() );
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
	 * @param string $pref
	 * @param array $options
	 * @return bool
	 */
	public function setReadPreference($pref, $options=array()){
		return $this->getConnection()->setReadPreference($pref, $options);
	}

	/**
	 * setSlaveOkay on Mongo
	 * @param bool $bool
	 * @return bool
	 */
	public function setSlaveOkay($bool){
		return $this->getConnection()->setSlaveOkay($bool);
	}
}

/**
 * EMongoException
 * The Exception class that is used by this extension
 */
class EMongoException extends CException{
	public $errorInfo;
	public function __construct($message,$code=0,$errorInfo=null){
		$this->errorInfo=$errorInfo;
		parent::__construct($message,$code);
	}
}