<?php
/**
 * EMongoCacheDependency represents a dependency based on the query result of a Mongo Query.
 *
 * If the query result (a scalar) changes, the dependency is considered as changed.
 * To specify the Mongo Cursor, set {@link cursor} property.
 */
class EMongoCacheDependency extends CCacheDependency
{
	public $cursor;

	/**
	 * Constructor.
	 * @param string $cursor the Mongo Cursor whose result is used to determine if the dependency has been changed.
	 */
	public function __construct($cursor=null)
	{
		$this->cursor = $cursor;
	}

	/**
	 * PHP sleep magic method.
	 * This method ensures that the database instance is set null because it contains resource handles.
	 * @return array
	 */
	public function __sleep()
	{
		return array_keys((array)$this);
	}

	/**
	 * Generates the data needed to determine if dependency has been changed.
	 * This method returns the value of the global state.
	 * @throws CException if {@link cursor} is empty
	 * @return mixed the data needed to determine if dependency has been changed.
	 */
	protected function generateDependentData()
	{
		if($this->query!==null){
			if($db->queryCachingDuration>0){
				// temporarily disable and re-enable query caching
				$duration=$db->queryCachingDuration;
				$db->queryCachingDuration=0;
				$result=iterator_to_array($this->cursor);
				$db->queryCachingDuration=$duration;
			}else{
				$result=iterator_to_array($this->cursor);
			}
			return $result;
		}else{
			throw new CException(Yii::t('yii','EMongoCacheDependency.cursor cannot be empty.'));
		}
	}
}