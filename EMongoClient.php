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
class EMongoClient extends CApplicationComponent
{
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
	 * @var int string
	 */
	public $w = 1;
	
	/**
	 * Are we using journaled writes here? Beware this makes all writes wait for the journal, it does not
	 * state whether MongoDB is using journaling.
	 * Note: this is NOT straight to disk,
	 * it infact makes the journal to disk time a third of its normal time (anywhere between 2-30ms).
	 * Only works 1.3+ driver
	 * @var boolean
	 */
	public $j = false;
	
	/**
	 * The read preference
	 * The first param is the textual version of the constant name in the MongoClient for the type of read
	 * i.e.
	 * RP_PRIMARY and the second emulates the setReadPreference prototypes second parameter
	 * @see http://php.net/manual/en/mongo.readpreferences.php
	 * @var array()
	 */
	public $RP = array('RP_PRIMARY', array());
	
	/**
	 * The Legacy read preference.
	 * DO NOT USE IF YOU ARE ON VERSION 1.3+
	 * @var boolean
	 */
	public $setSlaveOkay = false;
	
	/**
	 * Allows one to connect when they want to when turned to false
	 * Note that if you try and access MongoDB before it is connected it
	 * will attempt to connect
	 * @var boolean
	 */
	public $autoConnect = true;
	
	/**
	 * Enables logging to the profiler
	 * @var boolean
	 */
	public $enableProfiling = false;
	
	/**
	 * @var integer number of seconds that query results can remain valid in cache.
	 * Use 0 or negative value to indicate not caching query results (the default behavior).
	 *
	 * In order to enable query caching, this property must be a positive
	 * integer and {@link queryCacheID} must point to a valid cache component ID.
	 *
	 * The method {@link cache()} is provided as a convenient way of setting this property
	 * and {@link queryCachingDependency} on the fly.
	 *
	 * @see cache
	 * @see queryCachingDependency
	 * @see queryCacheID
	 */
	public $queryCachingDuration = 0;
	
	/**
	 * @var CCacheDependency|ICacheDependency the dependency that will be used when saving query results into cache.
	 * @see queryCachingDuration
	 */
	public $queryCachingDependency;
	
	/**
	 * @var integer the number of SQL statements that need to be cached next.
	 * If this is 0, then even if query caching is enabled, no query will be cached.
	 * Note that each time after executing a SQL statement (whether executed on DB server or fetched from
	 * query cache), this property will be reduced by 1 until 0.
	 */
	public $queryCachingCount = 0;
	
	/**
	 * @var string the ID of the cache application component that is used for query caching.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable query caching.
	 */
	public $queryCacheID = 'cache';
	
	/**
	 * The Mongo Connection instance
	 * @var Mongo MongoClient
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
	 * you input.
	 * The secondary function, if not getter found, is to get a collection
	 */
	public function __get($k)
	{
		$getter = 'get' . $k;
		if(method_exists($this, $getter)){
			return $this->$getter();
		}
		return $this->selectCollection($k);
	}
	
	/**
	 * Will call a function on the database or error out stating that the function does not exist
	 * @param string $name
	 * @param array $parameters
	 * @return mixed
	 */
	public function __call($name, $parameters = array())
	{
		if(!method_exists($this->getDB(), $name)){
			return parent::__call($name, $parameters);
		}
		return call_user_func_array(array($this->getDB(), $name), $parameters);
	}
	
	public function __construct()
	{
		// We copy this function to add the subdocument validator as a built in validator
		CValidator::$builtInValidators['subdocument'] = 'ESubdocumentValidator';
	}
	
	/**
	 * The init function
	 * We also connect here
	 * @see yii/framework/base/CApplicationComponent::init()
	 */
	public function init()
	{
		parent::init();

		if($this->db){
			$this->options['db'] = $this->db;
		}
		
		if($this->autoConnect){
			$this->connect();
		}
	}
	
	/**
	 * Connects to our database
	 */
	public function connect()
	{
		if(!extension_loaded('mongo')){
			throw new EMongoException(
				yii::t(
					'yii', 
					'We could not find the MongoDB extension ( http://php.net/manual/en/mongo.installation.php ), please install it'
				)
			);
		}
		
		// We don't need to throw useless exceptions here, the MongoDB PHP Driver has its own checks and error reporting
		// Yii will easily and effortlessly display the errors from the PHP driver, we should only catch its exceptions if
		// we wanna add our own custom messages on top which we don't, the errors are quite self explanatory
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->_mongo = new Mongo($this->server, $this->options);
			$this->_mongo->connect();
			
			if($this->setSlaveOkay){
				$this->_mongo->setSlaveOkay($this->setSlaveOkay);
			}
		}else{
			$this->_mongo = new MongoClient($this->server, $this->options);
			
			if(is_array($this->RP)){
				$const = $this->RP[0];
				$opts = $this->RP[1];
				
				if(!empty($opts)){ 
					// I do this due to a bug that exists in some PHP driver versions
					$this->_mongo->setReadPreference(constant('MongoClient::' . $const), $opts);
				}else{
					$this->_mongo->setReadPreference(constant('MongoClient::' . $const));
				}
			}
		}
	}
	
	/**
	 * Gets the connection object
	 * Use this to access the Mongo/MongoClient instance within the extension
	 * @return Mongo MongoClient
	 */
	public function getConnection()
	{
		if(empty($this->_mongo)){
			$this->connect();
		}
		return $this->_mongo;
	}
	
	/**
	 * Sets the raw database adhoc
	 * @param string $name
	 */
	public function setDB($name)
	{
		$this->_db = $this->getConnection ()->selectDb ( $name );
	}
	
	/**
	 * Selects a different database
	 * @param $name
	 * @return MongoDB
	 */
	public function selectDB($name)
	{
		$this->setDB($name);
		return $this->getDB();
	}
	
	/**
	 * Gets the raw Database
	 * @return MongoDB
	 */
	public function getDB()
	{
		if(empty($this->_db)){
			$this->setDB ( $this->db );
		}
		return $this->_db;
	}
	
	/**
	 * You should never call this function.
	 * The PHP driver will handle connections automatically, and will
	 * keep this performant for you.
	 */
	public function close()
	{
		if(!empty($this->_mongo)){
			$this->_mongo->close ();
			return true;
		}
		return false;
	}
	
	/**
	 * This function is designed to be a helper to make calling the aggregate command
	 * more standard across all drivers.
	 * 
	 * @param string $collection        	
	 * @param
	 *        	$pipelines
	 * @return array
	 */
	public function aggregate($collection, $pipelines)
	{
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			return $this->getDB()->command(array('aggregate' => $collection, 'pipeline' => $pipelines));
		}
		return $this->getDB()->$collection->aggregate($pipelines);
	}
	
	/**
	 * Command helper
	 * @param array|string $command
	 * @return array
	 */
	public function command($command = array())
	{
		return $this->getDB()->command($command);
	}
	
	/**
	 * A wrapper for the original processing
	 * @param string $name
	 * @return MongoCollection
	 */
	public function selectCollection($name)
	{
		return $this->getDB()->selectCollection($name);
	}
	
	/**
	 * Sets the document cache for any particular document (EMongoDocument/EMongoModel)
	 * sent in as the first parameter of this function.
	 * Will not cache actual EMongoDocument/EMongoModel instances
	 * only active classes that inherit these
	 * @param $o
	 */
	public function setDocumentCache($o)
	{
		if(
			$this->getDocumentCache(get_class($o)) === array() && // Run reflection and cache it if not already there
			(get_class($o) != 'EMongoDocument' && get_class($o) != 'EMongoModel') /* We can't cache the model */
		){
			$_meta = array();
			
			$reflect = new ReflectionClass(get_class($o));
			$class_vars = $reflect->getProperties(ReflectionProperty::IS_PUBLIC); // Pre-defined doc attributes
			
			foreach($class_vars as $prop){
				
				if($prop->isStatic()){
					continue;
				}
				
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
	public function getFieldCache($name, $include_virtual = false)
	{
		$doc = isset($this->_meta[$name]) ? $this->_meta[$name] : array();
		$fields = array();
		
		foreach($doc as $name => $opts){
			if($include_virtual || !$opts['virtual']){
				$fields[] = $name;
			}
		}
		return $fields;
	}
	
	/**
	 * Just gets the document cache for a model
	 * @param string $name
	 * @return array
	 */
	public function getDocumentCache($name)
	{
		return isset($this->_meta[$name]) ? $this->_meta[$name] : array();
	}
	
	/**
	 * Gets the default write concern options for all queries through active record
	 * @return array
	 */
	public function getDefaultWriteConcern()
	{
		if(!version_compare(phpversion('mongo'), '1.3.0', '<')){
			return array('w' => $this->w, 'j' => $this->j);
		}elseif($this->w == 1){
			return array('safe' => true);
		}elseif($this->w > 0){
			return array('safe' => $this->w);
		}
		return array ();
	}
	
	/**
	 * Create ObjectId from timestamp.
	 * This function is not actively used it is
	 * here as a helper for anyone who needs it
	 * @param int $yourTimestamp
	 * @return MongoID
	 */
	public function createMongoIdFromTimestamp($yourTimestamp)
	{
		static $inc = 0;
		
		$ts = pack('N', $yourTimestamp);
		$m = substr(md5(gethostname ()), 0, 3);
		$pid = pack('n', getmypid());
		$trail = substr(pack('N', $inc ++), 1, 3);
		
		$bin = sprintf("%s%s%s%s", $ts, $m, $pid, $trail);
		
		$id = '';
		for($i = 0; $i < 12; $i ++){
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
	public function setReadPreference($pref, $options = array())
	{
		return $this->getConnection()->setReadPreference($pref, $options);
	}
	
	/**
	 * setSlaveOkay on Mongo
	 * @param bool $bool
	 * @return bool
	 */
	public function setSlaveOkay($bool)
	{
		return $this->getConnection()->setSlaveOkay($bool);
	}
	
	/**
	 * Sets the parameters for query caching.
	 * This method can be used to enable or disable query caching.
	 * By setting the $duration parameter to be 0, the query caching will be disabled.
	 * Otherwise, query results of the new SQL statements executed next will be saved in cache
	 * and remain valid for the specified duration.
	 * If the same query is executed again, the result may be fetched from cache directly
	 * without actually executing the SQL statement.
	 * 
	 * @param integer $duration
	 *        	the number of seconds that query results may remain valid in cache.
	 *        	If this is 0, the caching will be disabled.
	 * @param CCacheDependency|ICacheDependency $dependency
	 *        	the dependency that will be used when saving
	 *        	the query results into cache.
	 * @param integer $queryCount
	 *        	number of SQL queries that need to be cached after calling this method. Defaults to 1,
	 *        	meaning that the next SQL query will be cached.
	 * @return static the connection instance itself.
	 * @since 1.1.7
	 */
	public function cache($duration, $dependency = null, $queryCount = 1)
	{
		$this->queryCachingDuration = $duration;
		$this->queryCachingDependency = $dependency;
		$this->queryCachingCount = $queryCount;
		return $this;
	}
	
	public function getSerialisedQuery($criteria = array(), $fields = array(), $sort = array(), $skip = 0, $limit = null)
	{
		$query = array(
			'$query' => $criteria,
			'$fields' => $fields,
			'$sort' => $sort,
			'$skip' => $skip,
			'$limit' => $limit
		);
		return json_encode($query);
	}
	
	/**
	 *
	 * @return array the first element indicates the number of query statements executed,
	 *         and the second element the total time spent in query execution.
	 */
	public function getStats()
	{
		$logger = Yii::getLogger();
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.findOne');
		$count = count($timings);
		$time = array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.insert');
		$count += count($timings);
		$time += array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.find');
		$count += count($timings);
		$time += array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.deleteByPk');
		$count += count($timings);
		$time += array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.updateByPk');
		$count += count($timings);
		$time += array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.updateAll');
		$count += count($timings);
		$time += array_sum($timings);
		$timings = $logger->getProfilingResults(null, 'extensions.MongoYii.EMongoDocument.deleteAll');
		$count += count($timings);
		$time += array_sum($timings);
		
		return array($count, $time);
	}
}