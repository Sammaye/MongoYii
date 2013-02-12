<?php
class EMongoCriteria{

	public $condition = array();

	public $sort = array();
	public $skip = 0;
	public $limit = 0;

	/**
	 * These are not really designed to be used, instead it is a map of operators for where
	 * Yii would rather give me a textual version of the operator (i.e. CGridView when you enter
	 * > or <= into the search boxes)
	 * @var array
	 */
	public $operators = array(
		'greater'	=> '$gt',
		'>'			=> '$gt',
		'greatereq'	=> '$gte',
		'>='		=> '$gte',
		'less'		=> '$lt',
		'<'			=> '$lt',
		'lesseq'	=> '$lte',
		'<='		=> '$lte',
		'noteq'		=> '$ne',
		'!='		=> '$ne',
		'<>'		=> '$ne',
		'in'		=> '$in',
		'notin'		=> '$nin',
		'all'		=> '$all',
		'size'		=> '$size',
		'type'		=> '$type',
		'exists'	=> '$exists',
		'notexists'	=> '$exists',
		'elemmatch'	=> '$elemMatch',
		'mod'		=> '$mod',
		'%'			=> '$mod',
		'equals'	=> '$$eq',
		'eq'		=> '$$eq',
		'=='		=> '$$eq',
		'where'		=> '$where'
	);

    public function __construct($data) {
		foreach($data as $name=>$value)
			$this->$name=$value;
    }

    /**
     * Designed to be used for Yiis CGridView as noted about the operators above
     * @param $operator
     * @param $field
     * @param $value
     */
    function addSearchColumn($column, $value, $partialMatch=false){
		if(preg_match('/^(?:\s*(<>|<=|>=|<|>|=))?(.*)$/',$value,$matches)){
			$value=$matches[2];
			$op=$matches[1];
		}
		else
			$op='';

		if($value==='')
			return $this;

		if($partialMatch)
		{
			if($op==='')
				return $this->addSearchCondition($column,$value,$escape,$operator);
			if($op==='<>')
				return $this->addSearchCondition($column,$value,$escape,$operator,'NOT LIKE');
		}
		elseif($op==='')
			$op='=';

		$this->addCondition($column.$op.self::PARAM_PREFIX.self::$paramCount,$operator);
		$this->params[self::PARAM_PREFIX.self::$paramCount++]=$value;

		return $this;
    }

}