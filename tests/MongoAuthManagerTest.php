<?php

require_once 'bootstrap.php';

class MongoAuthManagerTest extends CTestCase
{
	public function tearDown()
	{
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}
	
	/**
	 * Check some random access requests
	 */
	public function testAccess()
	{
		$auth = Yii::app()->authManager;
		$auth->load();
		$this->createACL();
		$auth->load();
		
		$this->assertTrue($auth->checkAccess('readPost', 'authorB'));
		$this->assertFalse($auth->checkAccess('updatePost', 'readerA'));
		$this->assertFalse($auth->checkAccess('deletePost', 'editorC'));
		$this->assertTrue($auth->checkAccess('createPost', 'adminD'));
		$this->assertFalse($auth->checkAccess('unknownTask', 'adminD'));
		
		/**
		 * Assign task to new user
		 */
		$auth->assign('editor', 'newEditor');
		$auth->save();
		$auth->load();
		$this->assertTrue($auth->checkAccess('updatePost', 'newEditor'));
	}
	
	/**
	 * Creates an access control hierarchy, saves and reloads it from mongodb
	 *
	 * @see http://www.yiiframework.com/doc/guide/1.1/en/topics.auth#defining-authorization-hierarchy
	 * @return s EMongoAuthManager The auth manager instance
	 */
	public function createACL()
	{
		$auth = Yii::app()->authManager;
		
		$auth->createOperation('createPost', 'create a post');
		$auth->createOperation('readPost', 'read a post');
		$auth->createOperation('updatePost', 'update a post');
		$auth->createOperation('deletePost', 'delete a post');
		
		$bizRule = 'return Yii::app()->user->id==$params["post"]->authID;';
		$task = $auth->createTask('updateOwnPost', 'update a post by author himself', $bizRule);
		$task->addChild('updatePost');
		
		$role = $auth->createRole('reader');
		$role->addChild('readPost');
		
		$role = $auth->createRole('author');
		$role->addChild('reader');
		$role->addChild('createPost');
		$role->addChild('updateOwnPost');
		
		$role = $auth->createRole('editor');
		$role->addChild('reader');
		$role->addChild('updatePost');
		
		$role = $auth->createRole('admin');
		$role->addChild('editor');
		$role->addChild('author');
		$role->addChild('deletePost');
		
		$auth->assign('reader', 'readerA');
		$auth->assign('author', 'authorB');
		$auth->assign('editor', 'editorC');
		$auth->assign('admin', 'adminD');
		$auth->save();
	}
	
	/**
	 * Manipulate database directly to confirm that authorization is read through mongodb
	 */
	public function testRAW()
	{
		$auth = Yii::app()->authManager;
		$auth->load();
		$this->createACL();
		$auth->load();
		
		// readerA has no right to updatePost
		$this->assertFalse($auth->checkAccess('updatePost', 'readerA'));
		
		// Update ACL collection to add user to editor group
		$tree = Yii::app()->mongodb->{$auth->collectionName}->findOne();
		$tree['editor']['assignments']['readerA'] = array(
			'bizRule' => null,
			'data' => null 
		); // add assignment
		Yii::app()->mongodb->{$auth->collectionName}->save($tree);
		
		// re-load ACL tree and check access
		$auth->load();
		$this->assertTrue($auth->checkAccess('updatePost', 'readerA'));
	}
}