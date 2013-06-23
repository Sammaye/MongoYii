<?php

/**
 * This file is extremely experimental.
 * 
 * It's API may change, more specifically the handling of a files properties such as the size and type, 
 * so please only use it for testing purposes and proposing solid changes to the file. 
 * 
 * Basically this is a MongoYii handler for the GridFS driver standard. it can accept an input file from $_FILES via ::populate and 
 * can also do find() and findOne() on the file collection. When delete is used it will gc the chunks collection by default as well.
 */
class EMongoFile extends EMongoDocument{
	
	/**
	 * Our file object, can be either the MongoGridFSFile or 
	 * CUploadFile
	 */
	private $_file;
	
	// Helper functions to get some common functionality on this class
	
	public function getFilename(){
		if($this->_file instanceof MongoGridFSFile)
			return $this->_file->getFilename();
		return $this->_file->getTempName();
	}
	
	public function getSize(){
		return $this->_file->getSize();
	}

	public function getBytes(){
		if($this->_file instanceof MongoGridFSFile)
			return $this->_file->getBytes();
		return file_get_contents($this->getFilename());
	}
	
	/**
	 * Gets the file object
	 */
	public function getFile(){
		return $this->_file;
	}
	
	/**
	 * Sets the file object
	 */
	public function setFile($v){
		$this->_file=$v;
	}
	
	/**
	 * Returns the static model of the specified AR class.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__){
		return parent::model($className);
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
		
			if(!isset($this->{$this->primaryKey()})) $this->{$this->primaryKey()} = new MongoId;
			if($this->getCollection()->storeFile($this->getFilename(), $this->getRawDocument())){ // The key change
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;		
	}
	
	/**
	 * Deletes the file.
	 * 
	 * If the first param is true it will also seek out all the chunks associated with the file and delete them. This 
	 * means it truly deletes the files in both the files and the chunks collection.
	 * 
	 * Note: gcing the chunks collection can become a stressful job, please make sure running this function with the 
	 * input of true does not break your own system.
	 * 
	 * @param $deleteChunks As to whether or not to gc the chunks collection as well
	 * @see EMongoDocument::delete()
	 */
	public function delete($deleteChunks=true){
		if(!$this->getIsNewRecord()){
			$this->trace(__FUNCTION__);
			if($this->beforeDelete()){
				$_id=$this->getPrimaryKey(); // Store the _id for post-deletion chunk removing
				$result=$this->deleteByPk($_id);
				if($deleteChunks) // Do we wanna remove chunks?
					$this->getCollection()->chunks->remove(array('files_id'=>$_id)); // Ok lets
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
	 * Get collection will now return the GridFS object from the driver
	 * @see EMongoDocument::getCollection()
	 */
	public function getCollection(){
		return $this->getDbConnection()->getGridFS();
	}
	
	/**
	 * Produces a trace message for functions in this class
	 * @param string $func
	 */
	public function trace($func){
		Yii::trace(get_class($this).'.'.$func.'()','extensions.MongoYii.EMongoFile');
	}	
}