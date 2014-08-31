<?php
return CMap::mergeArray(
	require ('../../../config/main.php'), 
	array(
		'components' => array (
			'mongodb' => array (
				'class' => 'EMongoClient',
				'server' => 'mongodb://localhost:27017',
				'db' => 'super_test' 
			),
			'authManager' => array (
				'class' => 'EMongoAuthManager' 
			) 
		) 
	)
);
