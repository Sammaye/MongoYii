<?php
/**
 * Created by IntelliJ IDEA.
 * User: soldatenko
 * Date: 29.04.13
 * Time: 6:45
 * To change this template use File | Settings | File Templates.
 */ 
class Skill extends EMongoDocument {

    public $name;
    public $_id;
    function rules(){
        return array(
            array('_id, name', 'safe', 'on'=>'search'),
        );
    }

    function collectionName(){
        return 'skills';
    }

    /**
     * Returns the static model of the specified AR class.
     * @return User the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

}
