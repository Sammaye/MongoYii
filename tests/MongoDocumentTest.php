<?php

require_once 'bootstrap.php';

class MongoDocumentTest extends CTestCase{

	function setUp(){
		parent::setUp();
	}

	function setUpRelationalModel(){
		$parentDocs = array(
			array('username' => 'sam', 'job_title' => 'awesome guy'),
			array('username' => 'john', 'job_title' => 'co-awesome guy'),
			array('username' => 'dan', 'job_title' => 'programmer'),
			array('username' => 'lewis', 'job_title' => 'programmer'),
			array('username' => 'ant', 'job_title' => 'programmer')
		);

		$childDocs = array(
			array('name' => 'jogging'),
			array('name' => 'computers'),
			array('name' => 'biking'),
			array('name' => 'drinking'),
			array('name' => 'partying'),
			array('name' => 'cars')
		);

		$docsLinkedByDBRef=array(
			array('name' => 'python'),
			array('name' => 'java'),
			array('name' => 'php'),
			array('name' => 'lisp'),
			array('name' => 'C'),
			array('name' => 'Objective C'),
			array('name' => 'C++'),
			array('name' => 'ruby'),
		);

		foreach (array(array('class'=>'Interest','data'=>$childDocs),array('class'=>'Skill','data'=>$docsLinkedByDBRef)) as $subgroup){
			foreach($subgroup['data'] as $doc){
				$i = new $subgroup['class']();
				foreach($doc as $k=>$v) $i->$k=$v;
				$this->assertTrue($i->save());
			}
		}

		// Lets make sure those child docs actually went in
		$skills=iterator_to_array(Skill::model()->find());
		$this->assertTrue(count($skills)>0);
		$c=Interest::model()->find();
		$this->assertTrue($c->count()>0);


		// Let's build an array of the all the _ids of the child docs
		$interest_ids = array();
		foreach($c as $row){
			$interest_ids[] = $row->_id;
		}


		// Create the users with each doc having the value of the interest ids
		$user_ids = array();
		foreach($parentDocs as $doc){
			$u = new User();
			foreach($doc as $k=>$v) $u->$k=$v;
			$u->interests = $interest_ids;

			//Let`s take random skill as primary for this user
			$primarySkill=$skills[array_rand($skills)]->_id;
			$u->mainSkill=MongoDBRef::create(Skill::model()->collectionName(),$primarySkill);

			//Now, lets pick array of secondary skills
			$allSecondarySkills=array();
			foreach (array_rand($skills,rand(3,5)) as $secondarySkill){
				if ($secondarySkill==$primarySkill)  continue;
				array_push($allSecondarySkills,MongoDBRef::create(Skill::model()->collectionName(),$skills[$secondarySkill]->_id));
			}

			$u->otherSkills=$allSecondarySkills;
			$this->assertTrue($u->save());
			$user_ids[] = $u->_id;
		}

		$interests = array_values(iterator_to_array($c));

		// Now 50^6 times re-insert each interest with a parnt user _id
		// So we have two forms of the document in interests, one without the parent user and one with
		for($i=0;$i<50;$i++){

			$randInt = rand(0,sizeof($interests)-1);
			$row =$interests[$randInt];

			$randPos = rand(0, sizeof($user_ids)-1);
			$row->i_id = $user_ids[$randPos];

			$row->setIsNewRecord(true);
			$row->_id = null;
			$row->setScenario('insert');

			$this->assertTrue($row->save());
		}

		// we will assume the set up was successful and we will leave it to further testing to see
		// whether it really was.
	}

	function tearDown(){
		Yii::app()->mongodb->drop();
		parent::tearDown();
	}


	function testModel(){
		$c=User::model();
		$this->assertInstanceOf('EMongoDocument', $c);
	}

	function testSaving(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$r=User::model()->find();
		$this->assertTrue($r->count()>0);

		foreach($r as $doc){
			$doc->username="dan";
			$this->assertTrue($doc->save());
		}
	}

	function testDeleting(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());
		$r=$c->delete();
		$this->assertTrue($r['n']==1&&$r['err']===null);

		$r=User::model()->find();
		$this->assertFalse($r->count()>0);
	}

	function testFindOne(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$r=User::model()->find();
		$this->assertTrue($r->count()>0);
	}

	function testFindBy_id(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$r=User::model()->findBy_id($c->_id);
		$this->assertTrue(!is_null($r));

		$r=User::model()->findBy_id((string)$c->_id);
		$this->assertTrue(!is_null($r));
	}
	
	function testFindAllByPk(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$r=User::model()->findAllByPk($c->_id);
		$this->assertTrue(!is_null($r));

		$r=User::model()->findAllByPk((string)$c->_id);
		$this->assertTrue(!is_null($r));
		
		$r=User::model()->findAllByPk(array((string)$c->_id));
		$this->assertTrue(!is_null($r));		
	}

	function testUpdateByPk(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->updateByPk($c->_id, array('$set' => array('username' => 'gfgfgf')));

		$r=User::model()->findOne(array('username' => 'gfgfgf'));
		$this->assertInstanceOf('EMongoDocument', $r);
	}

	function testDeleteByPk(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->deleteByPk($c->_id);

		$r=User::model()->findOne();
		$this->assertNull($r);
	}

	function testUpdateAll(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->updateAll(array('_id' => $c->_id),array('$set' => array('username' => 'gfgfgf')));

		$r=User::model()->findOne(array('username' => 'gfgfgf'));
		$this->assertInstanceOf('EMongoDocument', $r);
	}

	function testDeleteAll(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->deleteAll();

		$r=User::model()->findOne();
		$this->assertNull($r);
	}

	function testSaveAttributes(){

		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->job_title='programmer';
		$r=$c->saveAttributes(array('username'));
		$this->assertNull($r['err']);

		$r=User::model()->findOne();
		$this->assertFalse(isset($r->job_title));
	}

	function testPartialDocuments(){

		$u=new User;
		$u->username='sammaye';
		$this->assertTrue($u->save());

		$r=User::model()->findOne(array(),array('username' => 1));
		$this->assertTrue($r->getIsPartial());

		$p=$r->getProjectedFields();
		$this->assertTrue(isset($p['username'],$p['_id']));
		$this->assertFalse(isset($p['addresses']));

		$r2=User::model()->find(array(),array('username' => 1));
		foreach($r2 as $row)
			$this->assertTrue($row->getIsPartial());

		// Assume that the partial field cache since past this point both methods are initialised in the same way

		$this->assertTrue($r->save());
	}

	function testOneRelation(){
		$this->setUpRelationalModel();
		$r=User::model()->findOne();
		$this->assertInstanceOf('EMongoDocument', $r->one_interest);
	}

	function testManyRelation(){
		$this->setUpRelationalModel();
		$r=User::model()->findOne();
		$this->assertInstanceOf('EMongoCursor', $r->many_interests);
		$this->assertTrue($r->many_interests->count()>0);
	}


    function testOneDBRefRelation(){
        $this->setUpRelationalModel();
        $r=User::model()->findOne();
        $this->assertInstanceOf('Skill', $r->primarySkill);
    }

    function testManyDBRefRelation(){
        $this->setUpRelationalModel();
        $r=User::model()->findOne();
        $secondarySkills=$r->secondarySkills;
        $this->assertTrue(count($secondarySkills)>0);
        foreach ($secondarySkills as $skill) $this->assertInstanceOf('Skill', $skill);
    }



	function testEmbeddedRelation(){
		$this->setUpRelationalModel();
		$r=User::model()->findOne();
		$this->assertInstanceOf('EMongoCursor', $r->embedInterest);
		$this->assertTrue($r->embedInterest->count()>0);
	}

	function testWhereRelation(){
		$this->setUpRelationalModel();
		$r=User::model()->findOne();
		$this->assertInstanceOf('EMongoCursor', $r->where_interest);
	}

	function testFunctionalRelation(){
		$this->setUpRelationalModel();
		$r=User::model()->findOne();

		$rel=$r->many_interests(array('name' => 'computers'));
		$this->assertInstanceOf('EMongoCursor', $rel);
		$this->assertTrue($rel->count()>0);
	}

	function testTimestampBehaviour(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());
		$this->assertTrue(isset($c->create_time));

		$c->job_title='programmer';
		$this->assertTrue($c->save());
		$this->assertTrue(isset($c->update_time));
	}

	function testUniqueValidator(){
		$c=new User;
		$c->setScenario('testUnqiue');
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c=new User;
		$c->setScenario('testUnqiue');
		$c->username='sammaye';
		$this->assertFalse($c->validate());
		$this->assertNotEmpty($c->getError('username'));
	}

	function testArraySubdocumentValidator(){

		$c=new User;
		$c->username='sammaye';
		$c->addresses = array(
				array('road' => 12, 'town' => 'yo', 'county' => 23, 'post_code' => 'g', 'telephone' => 'ggg')
		);
		$this->assertFalse($c->validate());

		$c=new User;
		$c->username='sammaye';
		$c->addresses = array(
				array('road' => 's', 'town' => 'yo', 'county' => 'sa', 'post_code' => 'g', 'telephone' => 23)
		);
		$this->assertTrue($c->validate());
	}

	function testClassSubdocumentValidator(){
		$c=new User;
		$c->username='sammaye';

		$s=new SocialUrl();
		$s->url="facebook";
		$s->caption="social_profile";
		$c->url=$s;

		$this->assertFalse($c->validate());
		$this->assertTrue(!$c->url instanceof SocialUrl);

		$c=new User;
		$c->username='sammaye';

		$s=new SocialUrl();
		$s->url=1;
		$s->caption=2;
		$c->url=$s;

		$this->assertTrue($c->validate());
		$this->assertTrue(!$c->url instanceof SocialUrl);

	}

	function testExists(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());
		$this->assertTrue(User::model()->exists(array('username' => 'sammaye')));
	}

	function testEquals(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$d=User::model()->findOne(array('username' => 'sammaye'));
		$this->assertTrue($c->equals($d));
	}

	function testScopes(){

		$parentDocs = array(
			array('username' => 'sam', 'job_title' => 'awesome guy'),
			array('username' => 'john', 'job_title' => 'co-awesome guy'),
			array('username' => 'dan', 'job_title' => 'programmer'),
			array('username' => 'lewis', 'job_title' => 'programmer'),
			array('username' => 'ant', 'job_title' => 'programmer')
		);

		foreach($parentDocs as $doc){
			$c=new User;
			foreach($doc as $k=>$v) $c->$k=$v;
			$this->assertTrue($c->save());
		}

		$u=User::model()->programmers()->find();
		$this->assertTrue($u->count(true)==2);
		User::model()->resetScope();
	}


	function testClean_Refresh(){
		$c=new User;
		User::model()->resetScope();
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$this->assertTrue($c->clean());
		$this->assertNull($c->username);

		$r=User::model()->findOne();
		$this->assertInstanceOf('EMongoDocument',$r);

		$r->username = 'fgfgfg';
		$r->refresh();
		$this->assertEquals('sammaye', $r->username);
	}

	function testGetAttributeLabel(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$this->assertEquals('name', $c->getAttributeLabel('username'));
	}

	function testSaveCounters(){
		$c=new User;
		$c->username='sammaye';
		$this->assertTrue($c->save());

		$c->saveCounters(array('i' => 1));
		$this->assertTrue($c->i == 1);

		$d=User::model()->findOne(array('username' => 'sammaye'));
		$this->assertTrue($d->i==1);

		$c->saveCounters(array('i' => -1));
		$this->assertTrue($c->i == 0);

		$e=User::model()->findOne(array('username' => 'sammaye'));
		$this->assertTrue($e->i==0);
	}
}