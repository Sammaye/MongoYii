<?php
$envFile = dirname(__FILE__) . '/config/env.php';
if(file_exists($envFile)){
	$env = require_once $envFile;
}else{
	$env = array();
}

if(!isset($env['config'])){
	$env['config'] = dirname(__FILE__) . '/config/test.php';
}
if(!isset($env['yiit'])){
	$env['yiit'] = '../../../../yii/framework/yiit.php'; // You may change this line according to your setup
}
require_once($env['yiit']);
//require_once(dirname(__FILE__).'/WebTestCase.php');
Yii::createWebApplication($env['config']);

require_once 'models/User.php';
require_once 'models/UserTsTest.php';
require_once 'models/Interest.php';
require_once 'models/Dummy.php';
require_once 'models/Skill.php';
require_once 'models/versionedDocument.php';
