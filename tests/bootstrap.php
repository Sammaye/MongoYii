<?php
$envFile=dirname(__FILE__).'/config/env.php';
if (file_exists($envFile))
	$env=require_once $envFile;
else
	$env=array();
if (!isset($env['config']))
	$env['config']=dirname(__FILE__).'/config/test.php';
if (!isset($env['yiit']))
	$env['yiit'] = '../../../../yii/framework/yiit.php';

require_once($env['yiit']);
//require_once(dirname(__FILE__).'/WebTestCase.php');
Yii::createWebApplication($env['config']);

require_once 'models/User.php';
require_once 'models/Interest.php';
require_once 'models/Dummy.php';
require_once 'models/Skill.php';