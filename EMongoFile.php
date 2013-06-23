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
	
	private $filename;
	
	private $tmp_name;
	private $type;
	private $size;
	private $error;	
	
	private $_file;
	
	function getName(){
		return $this->filename;
	}
	
	function setName($v){
		$this->filename=$v;
	}
	
	function getTmp_name(){
		return $this->tmp_name;
	}
	
	function setTmp_name($v){
		$this->tmp_name=$v;
	}
	
	function getType(){
		return $this->type;
	}
	
	function setType($v){
		$this->type=$v;
	}
	
	function getSize(){
		return $this->size;
	}
	
	function setSize($v){
		$this->size=$v;
	}
	
	function getError(){
		return $this->error;
	}
	
	function setError($v){
		$this->error=$v;
	}
	
	function getBytes(){
		return $this->_file->getBytes();
	}
	
	function getFile(){
		return $this->_file;
	}
	
	function setFile($v){
		if($v instanceof MongoGridFSFile)
			$this->_file=$v;
		return $this;
	}
	
	/**
	 * Returns the static model of the specified AR class.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}	
	
	/**
	 * This cna populate from a $_FILES instance
	 * @param CModel $model
	 * @param string $attribute
	 * @return boolean|EMongoFile|NULL
	 */
	static function populate($model,$attribute){
		if($file=CUploadedFile::getInstance($model, $attribute)){
			
			if($file->getHasError())
				return false;
			
			$model=new EMongoFile();
			$model->name=$file->getTempName();
			$model->tmp_name=$file->getTempName();
			$model->type=$file->getType();
			$model->size=$file->getSize();
			$model->error=$file->getError();
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
	function populateRecord($attributes,$callAfterFind=true,$partial=false){
		if($attributes!==false)
		{
			$file=$attributes;
			$attributes=$file->file;
					
			$record=$this->instantiate($attributes);			
			
			$record->name=$file->getFilename();
			$record->size=$file->getSize();
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
	 * Inserts the file
	 * @see EMongoDocument::insert()
	 */
	function insert($attributes=null){
		if(!$this->getIsNewRecord())
			throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
		if($this->beforeSave())
		{
			$this->trace(__FUNCTION__);
		
			if(!isset($this->{$this->primaryKey()})) $this->{$this->primaryKey()} = new MongoId;
			if($this->getCollection()->storeFile($this->getName(), $this->getRawDocument())){ // The key change
				$this->afterSave();
				$this->setIsNewRecord(false);
				$this->setScenario('update');
				return true;
			}
		}
		return false;		
	}
	
	/**
	 * Deletes the file
	 * @see EMongoDocument::delete()
	 */
	function delete($deleteChunks=true){
		if(!$this->getIsNewRecord()){
			$this->trace(__FUNCTION__);
			if($this->beforeDelete()){
				$_id=$this->getPrimaryKey();
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
	 * Get collection will now return the gridfs object
	 * @see EMongoDocument::getCollection()
	 */
	function getCollection(){
		return $this->getDbConnection()->getGridFS();
	}
}