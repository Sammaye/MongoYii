<?php
$yiit='../../../../yii/framework/yiit.php';
$config='../../../config/config/test.php';
require_once($yiit);
//require_once(dirname(__FILE__).'/WebTestCase.php');
Yii::createWebApplication($config);