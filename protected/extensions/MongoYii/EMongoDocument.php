<?php

class EMongoDocument extends EMongoModel{


	function getAttributes(){

	}

	function setAttributes($values){

	}

	public function save($runValidation=true,$attributes=null)
	{
		if(!$runValidation || $this->validate($attributes))
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		else
			return false;
	}

	/**
	 * Returns if the current record is new.
	 * @return boolean whether the record is new and should be inserted when calling {@link save}.
	 * This property is automatically set in constructor and {@link populateRecord}.
	 * Defaults to false, but it will be set to true if the instance is created using
	 * the new operator.
	 */
	public function getIsNewRecord()
	{
		return $this->_new;
	}

	/**
	 * Sets if the record is new.
	 * @param boolean $value whether the record is new and should be inserted when calling {@link save}.
	 * @see getIsNewRecord
	 */
	public function setIsNewRecord($value)
	{
		$this->_new=$value;
	}

	public function onBeforeSave($event)
	{
		$this->raiseEvent('onBeforeSave',$event);
	}

	public function onAfterSave($event)
	{
		$this->raiseEvent('onAfterSave',$event);
	}

	public function onBeforeDelete($event)
	{
		$this->raiseEvent('onBeforeDelete',$event);
	}

	public function onAfterDelete($event)
	{
		$this->raiseEvent('onAfterDelete',$event);
	}

	public function onBeforeFind($event)
	{
		$this->raiseEvent('onBeforeFind',$event);
	}

	public function onAfterFind($event)
	{
		$this->raiseEvent('onAfterFind',$event);
	}

	protected function beforeSave()
	{
		if($this->hasEventHandler('onBeforeSave'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterSave()
	{
		if($this->hasEventHandler('onAfterSave'))
			$this->onAfterSave(new CEvent($this));
	}

	protected function beforeDelete()
	{
		if($this->hasEventHandler('onBeforeDelete'))
		{
			$event=new CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}
		else
			return true;
	}

	protected function afterDelete()
	{
		if($this->hasEventHandler('onAfterDelete'))
			$this->onAfterDelete(new CEvent($this));
	}

	protected function beforeFind()
	{
		if($this->hasEventHandler('onBeforeFind'))
		{
			$event=new CModelEvent($this);
			// for backward compatibility
			$event->criteria=func_num_args()>0 ? func_get_arg(0) : null;
			$this->onBeforeFind($event);
		}
	}

	protected function afterFind()
	{
		if($this->hasEventHandler('onAfterFind'))
			$this->onAfterFind(new CEvent($this));
	}

	public function insert($attributes=null){

	}

	public function update($attributes=null){

	}
}