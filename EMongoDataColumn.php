<?php

/**
 * EMongoDataColumn
 *
 * The MongoDB and MongoClient class combined.
 *
 * Quite deceptively this classes magics actually represents the DATABASE not the connection.
 *
 * Normally this would represent the MongoClient or Mongo and it is even named after them and implements
 * some of their functions but it is not due to the way Yii works.
 */
class EMongoDataColumn extends CDataColumn{
	/**
	 * Renders the header cell content.
	 * This method will render a link that can trigger the sorting if the column is sortable.
	 */
	protected function renderHeaderCellContent()
	{
		
		if($this->grid->enableSorting && $this->sortable && $this->name!==null)
			echo $this->grid->dataProvider->getSort()->link($this->name,$this->header,array('class'=>'sort-link'));
		elseif($this->name!==null && $this->header===null)
		{
			if($this->grid->dataProvider instanceof EMongoDataProvider)
				echo CHtml::encode($this->grid->dataProvider->model->getAttributeLabel($this->name));
			else
				echo CHtml::encode($this->name);
		}
		//else
			//parent::renderHeaderCellContent();
		
	}
}
