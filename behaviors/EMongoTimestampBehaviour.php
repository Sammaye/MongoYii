<?php

/**
 * CTimestampBehavior class file.
 *
 * @author Jonah Turnquist <poppitypop@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2011 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 *
 * Rewritten to work with MongoDB
 */

/**
 * EMongoTimestampBheaviour will automatically fill date and time related attributes.
 *
 * EMongoTimestampBehaviour will automatically fill date and time related attributes when the active record
 * is created and/or upadated.
 * You may specify an active record model to use this behavior like so:
 * <pre>
 * public function behaviors(){
 * 	return array(
 * 		'EMongoTimestampBehaviour' => array(
 * 			'class' => 'EMongoTimestampBehaviour',
 * 			'createAttribute' => 'create_time_attribute',
 * 			'updateAttribute' => 'update_time_attribute',
 *          'onScenario' => array('scenarioName'),
 * 		)
 * 	);
 * }
 * </pre>
 * The {@link createAttribute} and {@link updateAttribute} options actually default to 'create_time' and 'update_time'
 * respectively, so it is not required that you configure them. If you do not wish EMongoTimestampBheaviour
 * to set a timestamp for record update or creation, set the corresponding attribute option to null.
 *
 * By default, the update attribute is only set on record update. If you also wish it to be set on record creation,
 * set the {@link setUpdateOnCreate} option to true.
 *
 * Although EMongoTimestampBheaviour attempts to figure out on it's own what value to inject into the timestamp attribute,
 * you may specify a custom value to use instead via {@link timestampExpression}
 */
class EMongoTimestampBehaviour extends CActiveRecordBehavior
{
	/**
	 * @var mixed The name of the attribute to store the creation time.  Set to null to not
	 * use a timestamp for the creation attribute.  Defaults to 'create_time'
	 */
	public $createAttribute = 'create_time';
	
	/**
	 * @var mixed The name of the attribute to store the modification time.  Set to null to not
	 * use a timestamp for the update attribute.  Defaults to 'update_time'
	 */
	public $updateAttribute = 'update_time';

	/**
	 * @var array set attributes only on this scenarios
	 */
	public $onScenario = array();

	/**
	 * @var array not set attributes only on this scenarios
	*/
	public $notOnScenario = array();

	/**
	 * @var bool Whether to set the update attribute to the creation timestamp upon creation.
	 * Otherwise it will be left alone.  Defaults to false.
	*/
	public $setUpdateOnCreate = false;

	/**
	 * @var mixed The expression that will be used for generating the timestamp.
	 * This can be either a string representing a PHP expression (e.g. 'time()'),
	 * or a {@link CDbExpression} object representing a DB expression (e.g. new CDbExpression('NOW()')).
	 * Defaults to null, meaning that we will attempt to figure out the appropriate timestamp
	 * automatically. If we fail at finding the appropriate timestamp, then it will
	 * fall back to using the current UNIX timestamp
	 */
	public $timestampExpression;

	/**
	 * Responds to {@link CModel::onBeforeSave} event.
	 * Sets the values of the creation or modified attributes as configured
	 *
	 * @param CModelEvent $event event parameter
	 */
	public function beforeSave($event)
	{
		if($this->checkScenarios()){
			if($this->getOwner()->getIsNewRecord() && ($this->createAttribute !== null)){
				$this->getOwner()->{$this->createAttribute} = $this->getTimestampByAttribute($this->createAttribute);
			}
			if((!$this->getOwner()->getIsNewRecord() || $this->setUpdateOnCreate) && ($this->updateAttribute !== null)){
				$this->getOwner()->{$this->updateAttribute} = $this->getTimestampByAttribute($this->updateAttribute);
			}
		}
	}

	/**
	 * Gets the approprate timestamp depending on the column type $attribute is
	 *
	 * @param string $attribute $attribute
	 * @return mixed timestamp (eg unix timestamp or a mysql function)
	 */
	protected function getTimestampByAttribute($attribute)
	{
		if($this->timestampExpression instanceof MongoDate){
			return $this->timestampExpression;
		}elseif($this->timestampExpression !== null){
			return @eval('return '.$this->timestampExpression.';');
		}
		return new MongoDate();
	}

	protected function checkScenarios()
	{
		if(!is_array($this->onScenario) or !is_array($this->notOnScenario)){
			throw new CException('onScenario and notOnScenario must be an array');
		}
		if(count($this->onScenario)){
			if(count($this->notOnScenario)){
				throw new CException('You can not specify both the parameter and onScenario notOnScenario');
			}
			if(in_array($this->getOwner()->getScenario(), $this->onScenario)){
				return true;
			}else{
				return false;
			}
		}
		if(count($this->notOnScenario)){
			if(count($this->onScenario)){
				throw new CException('You can not specify both the parameter and onScenario notOnScenario');
			}
			if(in_array($this->getOwner()->getScenario(), $this->notOnScenario)){
				return false;
			}else{
				return true;
			}
		}
		return true;
	}
}