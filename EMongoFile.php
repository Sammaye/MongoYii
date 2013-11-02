<?php

/**
 * The MongoYii representation of a helper for uploading files to GridFS.
 * 
 * It can accept an input file from $_FILES via ::populate and can also do find() and findOne() on the files collection. 
 * This file is specifically designed for uploading files from a form to GridFS and is merely a helper, IT IS IN NO WAY REQUIRED.
 */
class EMongoFile extends EMongoDocument{
	
	/**
	 * Our file object, can be either the MongoGridFSFile or 
	 * CUploadFile
	 */
	private $_file;
	
	// Helper functions to get some common functionality on this class
	
	public function getFilename(){
		if($this->getFile() instanceof MongoGridFSFile)
			return $this->getFile()->getFilename();
		return $this->getFile()->getTempName();
	}
	
	public function getSize(){
		return $this->getFile()->getSize();
	}

	public function getBytes(){
		if($this->getFile() instanceof MongoGridFSFile)
			return $this->getFile()->getBytes();
		return file_get_contents($this->getFilename());
	}
	
	/**
	 * Gets the file object
	 */
	public function getFile(){
		// This if statement allows for you to continue using this class AFTER insert 
		// basically it will only get the file if you plan on using it further which means that
		// otherwise it omits at least one database call each time
		if($this->_id instanceof MongoId && !$this->_file instanceof MongoGridFSFile)
			return $this->_file=$this->getCollection()->get($this->_id);
		return $this->_file;
	}
	
	/**
	 * Sets the file object
	 */
	public function setFile($v){
		$this->_file=$v;
	}
	
	/**
	 * This denotes the prefix to all gridfs collections set by this class
	 * @return string
	 */
	public function collectionPrefix(){
		return 'fs';
	}
	
	/**
	 * Returns the static model of the specified AR class.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__){
		return parent::model($className);
	}	
	
	/**
	 * Magic will either call a function on the file if it exists or bubble to parent
	 * @see EMongoDocument::__call()
	 */
	public function __call($name,$parameters){
		if($this->getFile() instanceof MongoGridFSFile && method_exists($this->getFile(), $name))
			return call_user_func_array(array($this->getFile(), $name), $parameters);
		return parent::__call($name,$parameters);
	}
	
	/**
	 * This can populate from a $_FILES instance
	 * @param CModel $model
	 * @param string $attribute
	 * @return boolean|EMongoFile|NULL
	 */
	public static function populate($model,$attribute){
		if($file=CUploadedFile::getInstance($model, $attribute)){
			$model=new EMongoFile();
			$model->setFile($file);
			return $model;
		}
		return null;
	}
	
	/**
	 * Replaces the normal populateRecord specfically for GridFS by setting the attributes from the 
	 * MongoGridFsFile object correctly and other file details like size and name.
	 * @see EMongoDocument::populateRecord()
	 */
	public function populateRecord($attributes,$callAfterFind=true,$partial=false){
		if($attributes!==false)
		{
			// the cursor will actually input a MongoGridFSFile object as the "document" 
			// so what we wanna do is get the attributes or metadata attached to the file object 
			// set it as our attributes and then set this classes file as the first param we got
			$file=$attributes;
			$attributes=$file->file;
			$record=$this->instantiate($attributes);			
			$record->setFile($file);			
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
	 * Inserts the file.
	 * 
	 * The only difference between the normal insert is that this uses the storeFile function on the GridFS object
	 * @see EMongoDocument::insert()
	 */
	public function insert($attributes=null){
		if(!$this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);
			if($attributes===null){
				$document=$this->getRawDocument();
			}else{
				$document=$this->filterRawDocument($this->getAttributes($attributes));
			}
			
			if(YII_DEBUG){
				// we're actually physically testing for Yii debug mode here to stop us from
				// having to do the serialisation on the update doc normally.
				Yii::trace('Executing storeFile: {$document:'.json_encode($document).'}', 'extensions.MongoYii.EMongoDocument');
			}
			if($this->getDbConnection()->enableProfiling)
				$this->profile('extensions.MongoYii.EMongoFile.insert({$document:'.json_encode($document).'})', 'extensions.MongoYii.EMongoFile.insert');			
		
			if($_id=$this->getCollection()->storeFile($this->getFilename(), $document)){ // The key change
				$this->_id=$_id;
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;		
	}

	/**
	 * Get collection will now return the GridFS object from the driver
	 * @see EMongoDocument::getCollection()
	 */
	public function getCollection(){
		return $this->getDbConnection()->getDB()->getGridFS($this->collectionPrefix());
	}
	
	/**
	 * Produces a trace message for functions in this class
	 * @param string $func
	 */
	public function trace($func){
		Yii::trace(get_class($this).'.'.$func.'()','extensions.MongoYii.EMongoFile');
	}	
}