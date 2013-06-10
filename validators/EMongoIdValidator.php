<?php

/**
 * EMongoIdValidator
 *
 * This class is basically designed to be used to cast all fields sent into it as MongoIds.
 * This was created because at the time it was seen as the most flexible, yet easiest way, to accomplish
 * the casting of MongoIds automatically.
 */
class EMongoIdValidator extends CValidator{

	public $allowEmpty=true;

	protected function validateAttribute($object,$attribute){
		$value=$object->$attribute;
		if($this->allowEmpty && $this->isEmpty($value))
			return;
		$object->$attribute=$object->$attribute instanceof MongoId ? $object->$attribute : new MongoId($object->$attribute);
	}
}