<?php
$yiit='../../../../yii/framework/yiit.php';
$config='../../../config/config/test.php';
require_once($yiit);
//require_once(dirname(__FILE__).'/WebTestCase.php');
Yii::createWebApplication($config);

require_once 'models/User.php';
require_once 'models/Interest.php';
require_once 'models/Dummy.php';