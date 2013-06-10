<?php
return CMap::mergeArray(
    require('../../../config/main.php'),
    array(
        'components'=>array(
			'mongodb' => array(
				'class' => 'EMongoClient',
				'server' => 'mongodb://localhost:27017',
				'db' => 'super_test',
				'behaviors' => array(
					'writeConcern' => array(
						'class' => 'EMongoWriteConcernBehavior',
						'aliases' => array(
							'logs' => array('w'=>0),
							'files' => array('w'=>'majority'),
							'critical' => array('w'=>2, 'j'=>true),
						)
					)
				)
			),
        ),
    )
);