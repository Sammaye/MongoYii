<?php

/**
 * ESubdocumentValidator
 *
 * Warning: This class, if abused, can cause heavy repitition within your application.
 * With great power comes great responsibility.
 */
class ESubdocumentValidator extends CValidator{

	public $class;

	public $type;
	public $rules;
	
	public $scenario;

	public function validateAttribute($object, $attribute){

		if(!$this->type)
			throw new EMongoException(Yii::t('yii','You must supply a subdocument type of either "many" or "one" in order to validate subdocuments'));

		if(!$this->class && !$this->rules)
			throw new EMongoException(Yii::t('yii','You must supply either some rules to validate by or a class name to use'));

		// Lets judge what class we are using
		// If we are using a pre-defined class then lets just get on with it otherwise
		// lets instantiate a EMongoModel and fill its rules with what we want
		if($this->class){
			$c = new $this->class;
		}else{
			$c=new EMongoModel();
			foreach($this->rules as $rule){
				if(isset($rule[0],$rule[1]))  // attributes, validator name
					$c->validatorList->add(CValidator::createValidator($rule[1],$this,$rule[0],array_slice($rule,2)));
				else
					throw new CException(Yii::t('yii','{class} has an invalid validation rule. The rule must specify attributes to be validated and the validator name.',
						array('{class}'=>get_class($this))));
			}
		}
		
		if(is_object($this->scenario) && ($this->scenario instanceof Closure)){
			$c->scenario = $this->scenario($object);
		}else{
			$c->scenario = $this->scenario;
		}

		if($this->type == 'many'){
			if(is_array($object->$attribute)){

				$fieldErrors = array();
				$fieldValue = array();

				foreach($object->$attribute as $row){
					$c->clean();
					$val = $fieldValue[] = $row instanceof $c ? $row->getRawDocument() : $row;
					$c->setAttributes($val);
					if(!$c->validate()){
						$fieldErrors[] = $c->getErrors();
					}
				}

				if($this->message!==null){
					$this->addError($object,$attribute,$this->message);
				}elseif(sizeof($fieldErrors)>0){
					$this->setAttributeErrors($object, $attribute, $fieldErrors);
				}

				// Strip the models etc from the field value
				$object->$attribute = $fieldValue;
			}
		}else{
			$c->clean();
			$fieldValue = $object->$attribute instanceof $c ? $object->$attribute->getRawDocument() : $object->$attribute;
			$c->setAttributes($fieldValue);
			if(!$c->validate()){
				if($this->message!==null){
					$this->addError($object,$attribute,$this->message);
				}elseif(sizeof($c->getErrors())>0){
					$this->setAttributeErrors($object, $attribute, $c->getErrors());
				}
			}

			// Strip the models etc from the field value
			$object->$attribute = $fieldValue;
		}
	}

	/**
	 * Sets the errors for a certain attribute
	 * @param CModel $object the data object being validated
	 * @param string $attribute the attribute being validated
	 * @param array $messages the error messages for that attribute
	 */
	protected function setAttributeErrors($object,$attribute,$messages)
	{
		$object->setAttributeErrors($attribute,$messages);
	}
}