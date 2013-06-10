<?php
/**
 * EMongoWriteConcernBehavior class file
 *
 * @author Pavel E. Tetyaev <pahanini@gmail.com>
 * @license http://www.yiiframework.com/license/
 *
 */

/**
 * EMongoWriteConcernBehaviour allows to change default write concern using predefined in config aliases
 *
 * You may specify an predefined write concerns:
 * <pre>
 * 'mongodb' => array(
 *   'class' => 'EMongoClient',
 *     'behaviors' => array(
 *	     'writeConcern' => array(
 * 		    'class' => 'EMongoWriteConernBehavior',
 * 			'aliases => array(
 *				'logs' => array('w'=>0),
 * 				'files' => array('w'=>'majority'),
 * 				'critical' => array('w'=>1, 'j'=>1),
 *          ),
 * 		  )
 *     )
 *  )
 * </pre>
 *
 * Examples:
 *
 * 1. Set write concern
 * <pre>
 * 		Yii::app()->mongodb->setWriteConcern('logs');
 * 		doSomethingWithLogs();
 * 		Yii::app()->mongodb->setWriteConcern('default');	// Special name 'default' for original wc
 * </pre>
 *
 *
 * 2. You can easily set and restore:
 * <pre>
 * 		Yii::app()->mongodb->pushWriteConcern('critical');
 *  	doSomethingWithNewWC();
 * 		Yii::app()->mongodb->popWriteConcern(); // restore previous wc
 * </pre>
 */

class EMongoWriteConcernBehavior extends CBehavior {

	/**
	 * @var array Stack for push/pop
	 */
	private $_stack=array();

	/**
	 * @var array List of available options (array keys are option's names)
	 */
	public $aliases=array();

	/**
	 * Applys values from array to owner
	 * @param array $val
	 */
	private function applyAlias($val) {
		$owner=$this->getOwner();
		if (!isset($val['w'], $val['j']))
			$default=$this->getAlias('default');
		$owner->w = array_key_exists('w', $val) ? $val['w'] : $default['w'];
		$owner->j = isset($val['j']) ? $val['j'] : $default['j'];
		if (empty($owner->options['replicaSet']) && $owner->w!==0)
			$owner->w=1;
	}

	/**
	 * Attaches behavior
	 * @param CComponent $owner
	 */
	public function attach($owner) {
		parent::attach($owner);
		$this->aliases['default'] = array(
			'w' => $owner->w,
			'j' => $owner->j,
		);
	}

	/**
	 * @param $name
	 * @return mixed
	 * @throws EMongoException
	 */
	private function getAlias($name) {
		if (!isset($this->aliases[$name]))
			throw new EMongoException(Yii::t('yii', "Try to set undefined write concern {$name}", array('{name}' => $name)));
		return $this->aliases[$name];
	}

	/**
	 * Restores write concern from stack
	 */
	public function popWriteConcernAlias() {
		if ($option=array_pop($this->_stack))
			$this->applyAlias($option);
	}

	/**
	 * Saves current write concern to stack and sets new write concern with alias $name
	 * @param $name
	 */
	public function pushWriteConcernAlias($name) {
		$option=$this->getAlias($name);
		$owner=$this->getOwner();
		array_push($this->_stack, array('w'=>$owner->w, 'j'=>$owner->j));
			$this->applyAlias($option);
	}

	/**
	 * Sets write concern with alias $name
	 * @param $name
	 */
	public function setWriteConcernAlias($name) {
		$this->applyAlias($this->getAlias($name));
	}
}