<?php
class EMongoClient extends CApplicationComponent{

	public $server;
	public $db;

	public $w = 1;
	public $j = false;

	private $_mongo;
	private $_db;

	function init(){
		parent::init();
		$this->connect();
	}

	function connect(){

		// We don't need to throw useless exceptions here, the MongoDB PHP Driver has its own checks and error reporting
		// Yii will easily and effortlessly display the errors from the PHP driver, we should only catch its exceptions if
		// we wanna add our own custom messages on top which we don't, the errors are quite self explanatory
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			$this->_mongo = new Mongo($server);
			$this->_mongo->connect();
		}else{
			$this->_mongo = new MongoClient($server);
		}
	}

	function getConnection(){

		if(empty($this->_mongo))
			$this->connect();

		return $this->_mongo;
	}

	function setDB($name){
		$this->_db = $this->getConnection()->selectDb($name);
	}

	function getDB(){

		if(empty($this->_db))
			$this->setDB($this->db);

		return $this->_db;
	}

	/**
	 * You should never call this function.
	 */
	function close(){
		if(!empty($_mongo))
			$_mongo->close();
	}

	/**
	 * Gets the default write concern options for all queries through active record
	 * @return array
	 */
	function getDefaultWriteConcern(){
		if(version_compare(phpversion('mongo'), '1.3.0', '<')){
			if((bool)$this->w){
				return array('safe' => true);
			}
		}else{
			return array('w' => $this->w, 'j' => $this->j);
		}
		return array();
	}
}