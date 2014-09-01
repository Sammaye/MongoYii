<?php
/**
 * EMongoSort
 * @author Andrea Cardinale <a.cardinale80@gmail.com>
 * corresponding to Csort for MongoYii
 * @see yii/framework/web/CSort
 */

/**
 * This is only ever used in conjunction with CGridView and CListView. It is not designed to be used independantly
 */
class EMongoSort extends CSort
{
	/**
	 * @see CSort::resolveAttribute()
	 * @param string $attribute
	 * @return bool|string|array
	 */
	public function resolveAttribute($attribute)
	{
		if($this->attributes !== array()){
			$attributes = $this->attributes;
		}elseif($this->modelClass !== null){
			$attributes = EmongoDocument::model($this->modelClass)->attributeNames();
			if(empty($attributes)){
				// The previous statement can return null in certain models. So this is used as backup.
				$attributes = EmongoDocument::model($this->modelClass)->safeAttributeNames;
			}
		}else{
			return false;
		}
		foreach($attributes as $name => $definition){
			if(is_string($name)){
				if($name === $attribute){
					return $definition;
				}
			}elseif($definition === '*'){
				if($this->modelClass !== null && EmongoDocument::model($this->modelClass)->hasAttribute($attribute)){
					return $attribute;
				}
			}elseif($definition === $attribute){
				return $attribute;
			}
		}
		return false;
	}
	
	/**
	 * @see CSort::resolveLabel()
	 * @param string $attribute
	 * @return string
	 */
	public function resolveLabel($attribute)
	{
		$definition = $this->resolveAttribute($attribute);
		if(is_array($definition)){
			if(isset($definition['label'])){
				return $definition['label'];
			}
		}elseif(is_string($definition)){
			$attribute = $definition;
		}
		if($this->modelClass !== null){
			return EmongoDocument::model($this->modelClass)->getAttributeLabel($attribute);
		}
		return $attribute;
	}
	
	/**
	 * @see CSort::getOrderBy()
	 * @param EMongoCriteria $criteria
	 * @return array|string
	 * @throws EMongoException
	 */
	public function getOrderBy($criteria = null)
	{
		$directions = $this->getDirections();
		if(empty($directions)){
			return is_string($this->defaultOrder) ? $this->defaultOrder : array();
		}
		$schema = null; // ATM the schema aspect of this function has been disabled, the code below for schema isset is left in for future reference
		$orders = array();
		foreach($directions as $attribute => $descending){
			$definition = $this->resolveAttribute($attribute);
			if(is_array($definition)){
				// Atm only single cell sorting is allowed, this will change to allow you to define
				// a true definition of multiple fields to sort when one sort field is triggered but atm that is not possible
				if($descending){
					$orders[$attribute] = isset($definition['desc']) ? -1 : 1;
				}else{
					$orders[$attribute] = isset($definition['asc']) ? 1 : -1;
				}
			}elseif($definition !== false){
				$attribute = $definition;
				if(isset($schema)){
					if(($pos = strpos($attribute,'.')) !== false){
						throw new EMongoException('MongoDB cannot sort on joined fields please modify ' . $attribute . ' to not be sortable');
						//$attribute=$schema->quoteTableName(substr($attribute,0,$pos)).'.'.$schema->quoteColumnName(substr($attribute,$pos+1));
					}else{
						// MongoDB does not need these escaping or table namespacing elements at all so they have been commented out for the second
						//$attribute=($criteria===null || $criteria->alias===null ? EMongoDocument::model($this->modelClass)->getTableAlias(true) : $schema->quoteTableName($criteria->alias)).'.'.$schema->quoteColumnName($attribute);
					}
				}
				$orders[$attribute] = $descending ? -1 : 1;
			}
		}
		return $orders;
	}
}