<?php
class ESubdocumentValidator extends CValidator{

	public $class;

	public $type;
	public $rules;

	function validateAttribute($object, $attribute){

		if(!$this->type)
			throw new EMongoException(Yii::t('yii','You must supply a subdocument type of either "many" or "one" in order to validate subdocuments'));

		if(!$this->class && !$this->rules)
			throw new EMongoException(Yii::t('yii','You must supply either some rules to validate by or a class name to use'));

		//$subdocument = $model->$attribute;

//		if($this->type == 'many'){
//
//			$errors = array();
//
//			foreach($subdocument as $k => $doc){
//				$EDoc = $this->validateDocument($doc);
//				if(is_array($EDoc) && !empty($EDoc))
//					$this->addError($object, $attribute, $message, $params)
//
//			}
//		}else{
//
//		}
	}

	function validateDocument($doc){

		if($this->rules && !empty($this->rules)){

		}else{
			if($doc instanceof $this->class && !$doc->validate()){
				return $doc->getErrors();
			}else{
				$oDoc = new $this->class;
				$oDoc->attributes($doc);
				if(!$oDoc->validate())
					return $oDoc->getErrors();
			}
		}

		return true;
	}
}