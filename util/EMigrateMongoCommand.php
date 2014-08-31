<?php

Yii::import('system.cli.commands.MigrateCommand');

/**
 * EMigrateMongoCommand manages the database migrations.
 *
 * It is based on the "yiic migrate" command. It allows to manage database migrations
 * for mongodb.
 *
 * To enable this command, add a "commandMap"-section to your config file:
 *
 * <pre>
 *   'commandMap' => array(
 *       'migratemongo' => array(
 *           'class' => 'application.extensions.MongoYii.util.EMigrateMongoCommand'
 *       )
 *   )
 * </pre>
 */
class EMigrateMongoCommand extends MigrateCommand
{
	/**
	 * @var string the name of the mongodb collection that contains migration data.
	 *      If not set, it will be using 'migrations' as the collection.
	 */
	public $collectionName = 'migrations';
	
	/**
	 *
	 * @var string the connectionId of the EMongoClient component
	 */
	public $connectionID = 'mongodb';
	
	/**
	 * @var EMongoClient
	 */
	private $_db;
	
	public function actionHistory($args)
	{
		$limit = isset($args[0]) ? (int)$args[0] : 0;
		$migrations = $this->getMigrationHistory($limit);
		if($migrations === array()){
			echo "No migration has been done before.\n";
		}else{
			$n = count($migrations);
			if($limit > 0){
				echo "Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n";
			}else{
				echo "Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n";
			}
			foreach($migrations as $version => $time){
				echo "    (" . date('Y-m-d H:i:s', $time) . ') ' . $version . "\n";
			}
		}
	}

	public function actionMark($args)
	{
		if(isset($args[0])){
			$version = $args[0];
		}else{
			$this->usageError('Please specify which version to mark to.');
		}
		
		$originalVersion = $version;
		if(preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/', $version, $matches)){
			$version = 'm' . $matches[1];
		}else{
			echo "Error: The version option must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).\n";
			return 1;
		}
		
		$collection = $this->getDbConnection()->{$this->collectionName};
		
		// try mark up
		$migrations = $this->getNewMigrations();
		foreach($migrations as $i => $migration){
			if(strpos($migration, $version . '_') === 0){
				if($this->confirm("Set migration history at $originalVersion?")){
					for($j = 0; $j <= $i; ++ $j){
						$collection->save(array(
							'version' => $migrations[$j],
							'apply_time' => time() 
						));
					}
					echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
				}
				return 0;
			}
		}
			
		// try mark down
		$migrations = array_keys($this->getMigrationHistory(-1));
		foreach($migrations as $i => $migration){
			if(strpos($migration, $version . '_') === 0){
				if($i === 0){
					echo "Already at '$originalVersion'. Nothing needs to be done.\n";
				}else{
					if($this->confirm("Set migration history at $originalVersion?")){
						for($j = 0; $j < $i; ++ $j){
							$collection->delete(array(
								'version' => $migrations[$j] 
							));
						}
						echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
					}
				}
				return 0;
			}
		}
		
		echo "Error: Unable to find the version '$originalVersion'.\n";
		return 1;
	}
	
	public function beforeAction($action, $params)
	{
		$path = Yii::getPathOfAlias($this->migrationPath);
		if($path === false || !is_dir($path)){
			echo 'Error: The migration directory does not exist: ' . $this->migrationPath . "\n";
			exit(1);
		}
		$this->migrationPath = $path;
		
		$yiiVersion = Yii::getVersion();
		echo "\nYii Migration Tool for MongoYii v1.0 (based on Yii v{$yiiVersion})\n\n";
		
		return CConsoleCommand::beforeAction($action, $params);
	}

	public function getHelp()
	{
		return <<<EOD
USAGE
  yiic migratemongo [action] [parameter]

DESCRIPTION
  This command provides support for mongodb migrations. The optional
  'action' parameter specifies which specific migration task to perform.
  It can take these values: up, down, to, create, history, new, mark.
  If the 'action' parameter is not given, it defaults to 'up'.
  Each action takes different parameters. Their usage can be found in
  the following examples.

EXAMPLES
 * yiic migratemongo
   Applies ALL new migrations. This is equivalent to 'yiic migratemongo up'.

 * yiic migratemongo create create_user_collection
   Creates a new migration named 'create_user_collection'.

 * yiic migratemongo up 3
   Applies the next 3 new migrations.

 * yiic migratemongo down
   Reverts the last applied migration.

 * yiic migratemongo down 3
   Reverts the last 3 applied migrations.

 * yiic migratemongo to 101129_185401
   Migrates up or down to version 101129_185401.

 * yiic migratemongo mark 101129_185401
   Modifies the migration history up or down to version 101129_185401.
   No actual migration will be performed.

 * yiic migratemongo history
   Shows all previously applied migration information.

 * yiic migratemongo history 10
   Shows the last 10 applied migrations.

 * yiic migratemongo new
   Shows all new migrations.

 * yiic migratemongo new 10
   Shows the next 10 migrations that have not been applied.

EOD;
	}
	
	protected function createMigrationHistoryTable()
	{
		echo 'Creating initial migration history record...';
		
		$this->getDbConnection()->{$this->collectionName}->save(array(
			'version' => self::BASE_MIGRATION,
			'apply_time' => time () 
		));
		echo "done.\n";
	}
	
	protected function getDbConnection()
	{
		if($this->_db !== null){
			return $this->_db;
		}elseif(($this->_db = Yii::app ()->getComponent($this->connectionID)) instanceof EMongoClient){
			return $this->_db;
		}
		
		echo "Error: MigrateMongoCommand.connectionID '{$this->connectionID}' is invalid. Please make sure it refers to the ID of a EMongoClient application component.\n";
		exit(1);
	}
	
	protected function getMigrationHistory($limit)
	{
		return CHtml::listData(iterator_to_array($this->getDbConnection()->{$this->collectionName}->find()->sort(array(
			'version' => - 1 
		))->limit($limit)), 'version', 'apply_time');
	}
	
	protected function getNewMigrations()
	{
		$applied = array();
		foreach($this->getMigrationHistory(0) as $version => $time){
			$applied[substr($version, 1, 13)] = true;
		}
		
		$migrations = array();
		$handle = opendir($this->migrationPath);
		while(($file = readdir($handle)) !== false){
			if($file === '.' || $file === '..'){
				continue;
			}
			$path = $this->migrationPath . DIRECTORY_SEPARATOR . $file;
			if(preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file($path) && !isset($applied[$matches [2]])){
				$migrations[] = $matches[1];
			}
		}
		closedir($handle);
		sort($migrations);
		return $migrations;
	}

	protected function getTemplate()
	{
		if($this->templateFile !== null){
			return file_get_contents(Yii::getPathOfAlias($this->templateFile) . '.php');
		}else{
			return <<<EOD
<?php

class {ClassName} extends EMongoMigration
{
	public function up()
	{
	}

	public function down()
	{
		echo "{ClassName} does not support migration down.\\n";
		return false;
	}
}
EOD;
		}
	}

	protected function migrateDown($class)
	{
		if($class === self::BASE_MIGRATION){
			return;
		}
		
		echo "*** reverting $class\n";
		$start = microtime(true);
		$migration = $this->instantiateMigration($class);
		if($migration->down() !== false){
			$this->getDbConnection()->{$this->collectionName}->remove(array(
				'version' => $class 
			));
			$time = microtime(true) - $start;
			echo "*** reverted $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
		}else{
			$time = microtime(true) - $start;
			echo "*** failed to revert $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
			return false;
		}
	}
	
	protected function migrateUp($class)
	{
		if($class === self::BASE_MIGRATION){
			return;
		}
		
		echo "*** applying $class\n";
		$start = microtime(true);
		$migration = $this->instantiateMigration($class);
		if($migration->up() !== false){
			$this->getDbConnection()->{$this->collectionName}->save(array(
				'version' => $class,
				'apply_time' => time() 
			));
			$time = microtime(true) - $start;
			echo "*** applied $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
		}else{
			$time = microtime(true) - $start;
			echo "*** failed to apply $class (time: " . sprintf("%.3f", $time) . "s)\n\n";
			return false;
		}
	}
}
