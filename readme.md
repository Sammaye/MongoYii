**You can find a more user friendly version of this documentation on the extensions Github page: [http://sammaye.github.io/MongoYii](http://sammaye.github.io/MongoYii)**

# MongoYii

Another active record handler for the Yii framework that supports MongoDB.

## Rationale

There is already a great extension called YiiMongoDBSuite out for Yii so why make another? YiiMongoDBSuite has certain flaws which I wish to address:

- Does not support `$or` natively
- Very large and complicated code base
- Does not support the later versions of the PHP driver (1.3.x series) that well
- Obscured the MongoDB query language, layering a query language over the top

After some spare time I decided that I would take the liberty to make a MongoDB extension for Yii. It is basically a "glue" between MongoDB and
Yii and it is designed to be quite free form in that respect.

There are a few points of design I wished to enforce:

- expose the MongoDB query language in its raw form
- make the programming of this extension simple and easy to maintain for all parties
- make sure this extension worked with both the new and old versions of the MongoDB driver
- attempt to make things a little more performant
- try to follow Yiis own CActiveRecord API as much as possible without compromising MongoDB "semantics" such as the name for query operators and the use of a `MongoCursor`

Okay, so we have got some of the rationale in place it is time to actually talk about the extension.

## Setting up the extension

In order to use the extension you first need to set it up. The first thing to do is to download the source code and place it somewhere accessible within your applications structure, I have chosen
`protected/extensions/MongoYii`.

Once you have the source code in place you need to edit your `main.php` configuration file (`console.php` will need modifying too if you intend to use this extension in the console) with
the following type of configuration:

	'mongodb' => array(
		'class' => 'EMongoClient',
		'server' => 'mongodb://localhost:27017',
		'db' => 'super_test'
	),

And add the MongoYii directories to your `import` section:

	'application.extensions.MongoYii.*',
	'application.extensions.MongoYii.validators.*',
	'application.extensions.MongoYii.behaviors.*',
	'application.extensions.MongoYii.util.*'

That is the basic setup of the extension.

You will notice that I use a `EMongoClient`. This is a bit deceptive since it actually represents `MongoClient` and `MongoDB` combined.  This means that whenever you call the magic `__call`
on the `EMongoClient` like so:

	Yii::app()->mongodb->getSomething();

It will either try and call a function of `getSomething` in `EMongoClient` or, if the function does not exist, try and call it within the `MongoDB` class.

If you wish to call a function on the `MongoClient` or `Mongo` class you will need to retrieve the connection object like so:

	Yii::app()->mongodb->getConnection()->getSomething();

`EMongoClient` is also designed to handle full write concern and read preferences in a compatible manner with all versions of the driver.

**Note:** The models will by default seek a `mongodb` component within your configuration so please make sure that unless you modify the extension, or use it without active record, to
make your default (master) connection be a component called `mongodb`.

If you wish to setup the log to insert entries into MongoDB (like in `CDbLogRoute`) you can add the following to your 'log' component configuration:

		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(
					'class'=>'CFileLogRoute',
					'levels'=>'error, warning',
				),

				[ ... ]

				array(
					'class'=>'EMongoLogRoute',
					'connectionId'=>'my_connection_id', // optional, defaults to 'mongodb'
					'logCollectionName'=>'my_log_collection', // optional, defaults to 'YiiLog'
				),
				
			),
		),

### Providing a custom mongodb component/multiple connections

Each `EMongoDocument` or `EMongoModel` inherited class, i.e. your models will have a overrideable function called `getMongoComponent()`. You can simply override this to 
return your custom application component, for example:

	public function getMongoComponent()
	{
		return Yii::app()->someweirddbconnectionINIT;
	}

and that model will now use that new application component to source its information. This is also helpful if you are using different databases for different models.

### Composer

MongoYii fully supports Composer and is listed on [packagist](https://packagist.org/packages/sammaye/mongoyii).

As an additional side note posted by [@ujovlado](https://github.com/ujovlado) on a related [issue](https://github.com/Sammaye/MongoYii/issues/81#issuecomment-19739105);
if you are only using Composer for Yii extensions then you can set a more blanketed solution of simply changing your `vendor-dir` to `protected/extensions`:

	{
	    "config": {
	        "vendor-dir": "protected/extensions"
	    }
	}

However to have automatic handling of loading MongoYii using Composer you would require to downgrade to 1.0.3 where the [Yii installer is not deprecated](https://github.com/Sammaye/MongoYii/issues/81#issuecomment-19722140).
Failing both of those options you can also make your [own script to handle](http://getcomposer.org/doc/articles/scripts.md) what the removed installer used to.

Currently MongoYii does not handle namespaces and this is unlikely to change in Yii1.

### Write Concern (formally "safe" writes)

This extension uses the new `w` variable globally to handle the level of write concern you wish to impose on MongoDB.

By default the extension will assume acknowledged writes, this means `safe=true` or `w=1` depending on the version of your driver. To change this simply add `w` to your `mongodb` component configuration
and give it a value according to the [PHP documentation](http://php.net/manual/en/mongo.writeconcerns.php).

For those using the 1.3.x series of the driver there is also a `j` option which can be set to either `true` or `false` within the configuration which allows you to control
whether or not the write is journal acknowledged.

**Note:** Write Concern is abstracted from the driver itself to make this variable compatible across all versions of the driver so please use the configuration or the `EMongoClient` `w` and
`j` class variables to set the write concern when you need to, otherwise that write concern will not be used within active record.

**Note:** Write Concern works differently when you touch the database directly and the write concern issued within the `EMongoClient` class will have no
effect. Instead you should always ensure in this case you specify the write concern manually according to your driver version.

This may change in the future but at the moment when you want the active record to go away it just will.

### Read Preference

For those using the old driver there is only one extra configuration variable available to you, `setSlaveOkay`. Set this to either `true` or `false` in your configuration to make it
possible to read from members of a replica set.

For those using the 1.3.x series of the driver you have the `RP` configuration variable. The RP configuration variable is a 1-1 relation to the options of `setReadPreference`
on the `MongoClient` class with one exception. The first parameter is not a constant but instead the name of the constant. An example of using read preferences in your configuration
would be:

	'RP' => array('RP_SECONDARY' /* The name of the constant from the documentation */,
		array(/* Would normally be read tags, if any */))

Please refer to the [drivers documentation for a full set of options here](http://php.net/manual/en/mongo.readpreferences.php).

To change the Read Preference at any time please use the function applicable to your driver; for 1.3.x series:

	Yii::app()->mongodb->setReadPreference(MongoClient::RP_PRIMARY, array());

and for pre-1.3:

	Yii::app()->mongodb->setSlaveOkay(true);

**Note:** Unlike write concern, the `RP` and `setSlaveOkay` variables do not inter-lock between different versions of the driver, using the `EMongoClient` `RP` variable
will not translate to `slaveOkay`.

## Using MongoDB without Active Record

You can call the database directly at any time using the same implemented methods as you would using the driver normally. As an example, to query the database:

	Yii::app()->mongodb->collection->find(array('name' => 'sammaye'));

So the active record element of MongoYii can quickly disappear if needed.

## EMongoModel

The `EMongoModel` is a stripped down version of the `EMongoDocument`.

This was made separate from `EMongoDocument` to provide a small and slim active model for use on subdocuments. Whenever you make a class based subdocument you can extend this class.

The `EMongoModel` implements all that `CModel` does but with a few added and changed features.

### Magic functions

The getters and setters should inherit all of Yiis own functionality.

### Virtual Attributes

This extension supports virtual attributes via a doc block notation syntax of `@virtual`, for example:

	class User extends EMongoModel{
	    /** @virtual */
	    public $somevar;
	}

These variables can be used in the same way as everything else except they will never be saved in MongoDB.

**Note:** due to how PHP OO accession works it is a good idea to make all your record fields, virtual or not, `public`.

### Relations

Unlike in SQL where you have many complicated types of relations, in MongoDB you tend to only have two:- `one` and `many`.

As you have guessed, you can only define two types of relation in this extension - `one` and `many`. Lets take a look at an example:

	function relations(){
		return array(
			'others' => array('many', 'Other', 'otherId', 'sort' => array('_id'=>-1), 'skip' => 1, 'limit' => 10)
		);
	}

You will recognise a lot of this from Yiis own active record, in fact a lot is the same. We define a name for the relation as a key and then we define either `one` or `many` in text
(constants seemed useless with only two types) and then we define a class name, `Other` in this case, and then we define the foreign key in that class, `otherId`.

The default behaviour of relations is to attempt to use the primary key, `_id`, of the current model to query the foreign key. This is a problem for `EMongoModel`
since it has no primary key. Make sure that if you use this in `EMongoModel` you define a `on` clause to replace the primary key of the current model.

The `on` clause supports multiple field types. It can take a `DBRef` or an `ObjectId` or an array of `ObjectId`s depending on how you define your document.

You can also, just like in Yii, define a `where` clause. This is a 1-1 relation to the syntax used within normal querying in MongoDB and the extension will basically merge this
clause with the primary key field you define in order to query for the relation.

#### Caching

As of 5.x relation caching in MongoYii is turned ON by default. This means that all relations are now cached. If you wish to make sure a relation is not cached you can explicitly add 
`'cache' => false` to the relation definition like so:

	function relations(){
		return array(
			'others' => array('many', 'Other', 'otherId', 'cache' => false)
		);
	}

Versions prior to 5.x do not have relation caching turned on by default.

### getDocument()

Just gets the docuemnt "as-it-is". This means that if you put meta objects in like nested `EMongoModel`s it will get these back in the output.

### getRawDocument()

Will strip away all classes used by the extension and return a document suitable for use with MongoDB.

### getJSONDocument()

Will run `getRawDocument()` and then return its output as a JSON string.

### getBSONDocument()

Will run `getRawDocument()` and then return its output as a BSON string.

## EMongoDocument

The `EMongoDocument` extends `EMongoModel` and implements all of its features along with the needed features for database accession. It also implements as much as possible of
`CActiveRecord`.

**Note:** The functions that allow database usage are not defined within this section of the documentation. Instead those functions are actually defined within the "Querying" section.
Please move to the "Querying" section if you wish to read about this part of the `EMongoDocument`.

### collectionName()

Returns a string representing the collection name. All active record models should implement this function although it is not `abstract`.

### primaryKey()

Currently only returns `_id` as the key.

### Using a Custom Primary Key

If you are using a primary key that IS NOT a `ObjectId` (otherwise known as a `MongoId` in the PHP driver) then you should override the `getPrimaryKey` function of the `EMongoDocument`
to not return a `MongoId`:

	public function getPrimaryKey($value=null){
		if($value===null)
			$value=$this->{$this->primaryKey()};
		return (string)$value;
	}

You can, of course, add whatever procedure or formatting code you need within this function to make sure that your primary key is ready for MongoDB when it comes to querying.

### Scopes

Scopes are fully supported in all the normal ways as with `CActiveRecord` but with one difference; the terminology.

The scopes, and queries, in this extension use these words to describe their parts:

- `condition` to describe the condition itself
- `sort` to describe the sort
- `skip` to describe offset
- `limit` to describe limit

As an example of a full default scope which omits deleted models to get the latest 10 skipping the first one:

	array(
		'condition' => array('deleted' => array('$ne' => 1)),
		'sort' => array('date' => -1),
		'skip' => 1,
		'limit' => 11
	)

You can also define your own scopes, however, it is a little different to how you are used to doing it in Yii:

	function someScope(){
		$this->mergeDbCriteria(array(
			'condition'=>array('scoped' => true),
			'sort'=>array('date'=>-1),
			'skip'=>1,
			'limit'=>11
		));
	}

As you will notice the `_criteria` variable within the EMongoDocument which would normally be a `EMongoCriteria` object is actually completely array based.

This applies to all scope actions; they are all array based.

To help you in not having the `EMongoCriteria` object the `EMongoDocument` provides a helper function for merging criteria objects called `mergeCriteria`. Using this function will
have no impact on the model itself and merely merges criteria to be returned. As an example of using the `mergeCriteria` function:

	function someScope(){

		$criteria = array(
			'condition'=>array('scoped' => true),
			'sort'=>array('date'=>-1),
			'skip'=>1,
			'limit'=>11
		);

		if($this->deleted)
			$criteria = $this->mergeCriteria($criteria,array('condition'=>array('deleted'=>1)));

		$this->mergeDbCriteria($criteria);
		reutrn $this;
	}

**Note:** Just like in Yii, normally scopes are not reset automatically, please use `resetScope()` to reset the scope.

### equals()

Checks if the current model equals another sent in as a parameter.

### exists()

Checks if a document exists in the database with the criteria supplied as the first parameter.

### clean()

Cleans the document of all properties and relations.

### refresh()

Runs `clean()` and then re-populates the model from the database.

### getCollection()

Returns the raw `MongoCollection`.

It is normally best not to use this but instead to use the extension wrapped editions - `updateAll` and `deleteAll`. The only difference of said functions
from doing it manually on `getCollection()` is that these functions understand the write concern of the extension.

### ensureIndexes()

This function allows the user to ensure a set of indexes by array definition.

This is most useful when used in the `init()` function to produce pre-made indexes on the start up of the model. A good example is:

    public function init()
    {
        if(YII_DEBUG){
            $this->ensureIndexes(array(
                array('username' => 1),
                array(array('email' => 1), array('unique' => true)),
                array(array('description' => 1))
            ));
        }
    }

The above example snippet shows all the different ways you can define indexes.

By default each element of the function input array will be an index definition, element `0` being the fields and `1` being the options.

However you are not required to define index options. You can also simplify the definition further by not defining a `0` element but instead an associative array 
defining only the fields of the index.

### setAttributes()

It is important, nay, imperative that you understand exactly how, by default MongoYii assigns integers. Since MongoDB has no strict handling of field types it is very easy
for boolean integers from the likes of checkboxes etc to end up as strings breaking your application and causing you to have to cast objects repeatedly or change the way you query
(since, of course, MongoDB is type aware when querying).

MongoYii will convert any number, real integer (otherwise known as "positive" or "unsigned" integer), not starting with 0 and not possessing a letter to an `int`.

This is important because the largest integer MongoDB can natively store is only 32bit. In order to make MongoDB store larger integers you must use the
[native_long](http://www.php.net/manual/en/mongo.configuration.php#ini.mongo.native-long) configuration variable available within the driver.

If you are on a 32bit system you will need to add another configuration variable to the stack: [long_as_object](http://www.php.net/manual/en/mongo.configuration.php#ini.mongo.long-as-object).

**Note:** Integers greater than the systems limit will be left as strings. This means that on a 32bit system the maximum `int` you can assign from form data is 2147483647 while on a 64bit system it is 9223372036854775807.
If you wish to use `int` data types from forms past your systems limits you will be required to process the fields yourself, either within the `CHttpRequest` handler or using a validator.

### Example

So now that we have discussed the `EMongoDocument` lets look at the most base of example:

	class User extends EMongoDocument{
		function collectionName(){
			return 'users';
		}

		public static function model($className=__CLASS__){
			return parent::model($className);
		}
	}

This is the most basic document that can exist - no predefined schema and only a `model` function (same as Yii active record) and the `tableName`, otherwise known as the `collectionName`,
are needed.

As time goes on you will want to add certain fields like virtual attributes and such to make your life easier:

	class User extends EMongoDocument{

		/** @virtual */
		public $agree = 1;

		public $addresses = array();

		function collectionName(){
			return 'users';
		}

		public static function model($className=__CLASS__){
			return parent::model($className);
		}
	}

Notice how I have added the `addresses` field despite not needing to? I do this due to the way that PHP uses magic functions.

If you access an array magically you cannot, in the same breath, manipulate it since it is an indirect accession of the variable. So a good tip here: if you plan on having subdocuments
 it might be good to explicitly declare the field as a variable within the class.

## Querying

Querying attempts to expose the native MongoDB querying language as much as possible. A `EMongoCriteria` class is provided, however, it is not required and does not provide any more functionality
than just doing it via arrays. The `EMongoCriteria` class is not relied on anywhere and is not needed.

### Caching

MongoYii, as well supporting full caching through EMongoCacheDependency (see towards the bottom of this documentation), supports active model query caching as 
defined in the [documentation](http://www.yiiframework.com/doc/guide/1.1/en/caching.data).

An example of this can be shown by:

	$dep = new EMongoCacheDependency('article', [['_id' => new MongoId('540477726803fad51b8b4568')], 'sort' => ['a' => 1]]);
	$c = Article::model()->cache(4, $dep)->findAll();

The results of `$c` will be drawn from the cache table into your application until the dependency is considered to expire.

Just like in normal Yii active record you can also say how many queries after the dependency should actually be cached.

### find()

`find()` is really simple. It is essentially a 1-1 to the drivers own `find()` function and implements the same specifics. Just like the drivers edition, it also returns a cursor
instance (`EMongoCursor`) which can be used to lazy load results from the database.

It will return a cursor irrespective of whether it finds results or not. However if it cannot find results then `count` will return `0` and the iterator will not have any iterations
to it.

**Note:** The cursor does not eager load documents, instead if you wish to accomplish this please wrap the call to `find` in a call to `iterator_to_array`.

### findOne() and findBy_id()

`findOne`, just like `findBy_id` is a straight 1-1 implementation of the drivers own `findOne` method and returns an active record record model if something was found, otherwise `null`.

The `findBy_id` function takes either a hexadecimal representation of a `ObjectId` in string form or wrapped in the `MongoId` class and will seek out a record with that `_id` using
the `findOne` function, returning the exact same. It is basically a helper for `findOne` to make your life a little easier.

### Scopes

The read functions of this extension have full support for scopes within models.

### Example

Ok so now we have a basic grasp of querying lets look at an example:

	$c = User::model()->recently()->find(array('deleted' => 0))->sort(array('joined' => -1))->skip(2)->limit(3);

This may look complicated but I will now break it down for you:

- `User::model()` gets our model
- `->recently()` is actually a scope, this is not needed but good for demonstration purposes
- `->find(/*...*/)` is basically the MongoDB drivers `find` method and returns a `EMongoCursor` which implements a `MongoCursor`
- `->sort()` is basically the MongoDB drivers `sort` method on the `MongoCursor`
- `->limit()` is, again, basically the MongoDB drivers own `limit` function on the `MongoCursor`

For a reference on what operators are supported please refer to the MongoDB documentation: [http://docs.mongodb.org/manual/reference/operators/](http://docs.mongodb.org/manual/reference/operators/)

**Note:** Other functions like `findByAttributes` have been omitted since it seems pointless with MongoDBs querying language to implement those.

### save()

This `save`s the document and is used externally as a means to access either `insert` or `update` on the active record model, i.e.:

	if($user->validate()) $user->save();

If the document is new it will insert otherwise it will update.

### insert()

This is used internally by the active record model. If the record is new it will attempt to insert otherwise it will throw
an error.

### update()

This is used internally by the active record model. If the record is not new it will attempt to update it otherwise it will throw an error.

If you send in attributes into either this function or the `save` function it will attempt to do a `$set` on those attributes otherwise it will `save` the model.

### delete()

This is used to delete the current active record.

### deleteByPk() and updateByPk()

These are helpers to the update and delete functions except they act on the database directly, instead of through active record.

To show by example:

	User::model()->deleteByPk($_id[, array('deleted' => 1)[, array('w' => 2)]]);
	User::model()->updateByPk($_id, array('$set' => array('d' => 1)[, array('deleted' => 1)[, array('w' => 2)]]);

Arguments shown in `[]` are optional.

These functions can take both a string and a `MongoId` as the `$_id` parameter.

### updateAll() and deleteAll()

Same as above really except these translate directly to the MongoDB drivers own `update` and `delete` functions.

**Note:** `UpdateAll` is `multi` `true` by default

## Validation

The validation has pretty much not changed except for the names of certain validators due to Yiis own requiring SQL.

### unique

The `unique` validator is now the `EMongoUnqiueValidator`.

	array('username', 'EMongoUniqueValidator', 'className' => 'User', 'attributeName' => 'username')
	
### exist

The `exist` validator is now the `EMongoExistValidator`.

	array('user_id', 'EMongoExistValidator', 'className' => 'User', 'attributeName' => '_id')
	
### EMongoIdValidator

This validator was added as a easy, yet flexible, method to automate the conversion of hexidecimal representation of `MongoId`s (for example: `addffrg33334455add0001`) to the 
`MongoId` object for database manipulation. This validator can also handle arrays of strings that need converting to `MongoId`s.

	array('ids,id', 'EMongoIdValidator'), // ids is an array while id is a single string value

### EMongoSubdocumentValidator

This is the subdocument validator, please see the "Subdocuments" section for full documentation.

## Behaviours

### EMongoTimestampBehaviour

This is the MongoYii edition of `CTimestampBehavior` behaviour and will use `MongoDate` fields, however, an expression can be added to `timestampExpression` to make the 
behaviour return integer timestamps.

The usage of the behaviour is very much alike to normal, infact only the name is different:

	function behaviors(){
		return array(
			'EMongoTimestampBehaviour'
		);
	}

## Subdocuments

Subdocuments are, mostly, not automatically supported by this extension. There a couple of reasons, firstly due to performance - automating subdocument usage requires a lot of
loaded classes to handle different subdocuments and their validation.

The other main reason is that, in any project I have done, whenever I tried to automate subdocuments through active record it has always resulted in me actually ditching
it and doing the process manually. It has been proven many times that you rarely actually want automated subdocuments and normally you want greater control over their storage than
this extension could provide.

So that is a brief understanding of the rationale behind the idea to ditch automatic subdocument handling within the active record.

This does not mean you cannot embed subdocument classes at all; when saving, the active record class will iterate the document and attempt to strip any `EMongoModel` or `EMongoDocument`
classes that have sprung up.

This all aside, there is a subdocument validator and technically it can even accept multi-level nesting. Please bare in mind, though, that it will cause repetition
for every level you use it on. This WILL have a performance implication on your application.

An example of using an array based subdocument is:

	function rules(){
		return array(
			array('addresses', 'subdocument', 'type' => 'many', 'rules' => array(
				array('road,town,county,post_code', 'safe'),
				array('telephone', 'integer')
			)),
		);
	}

While an example of a class based one is:

	function rules(){
		return array(
			array('addresses', 'subdocument', 'type' => 'many', 'class' => 'Other'),
		);
	}

`type` defines the type of subdocument, as with relations this is either `one` or `many`.

The validator will evaluate the rules as though they are completely separate from the originating model so in theory there is nothing stopping you from using any validator you want.

The error output for the validator will differ between the `one` and `many` types of subdocument. With `one` the validator will output the model errors directly onto the field
however with `many` it will create a new element for each model (row) with embedded errors in that new element in the field on the parent, for example:

	array(
		'addresses' => array(
			0 => array(
				'telephone' => array(
					0 => 'Some error here'
				)
			)
		)
	)
	
**Note:** In order to get filters working on 1.1.4 the validator will now, by default, overwrite what you send into it with the results of the validator output. This means that if your
rules are not defined correctly you will lose fields within your subdocuments. You can combat this by setting the `strict` parameter of this validator to `false`.

**Note:** While on the subject, to avoid the iteration every time you save the root document (since validation is run by default in Yii on save) you should confine your subdocument
validators to specific scenarios where they will be actively used.

### Handling Subdocuments

As we already know MongoYii does not handle subdocuments automatically for you. if you wish to have an automatic handler for subdocuments it is normally considered good advice to make
your own based on the scenarios you require. One reason for this is because many people have many different document setups and since there is no predefined schema for the subdocuments I
cannot provide automated usage without short of taking every single possibility of subdocument existence into account.

For this explanation we will assume you do not wish to make your own subdocument handler, but instead, are fine using MongoYiis and PHPs own built in abilities. Handling subdocuments depends heavily upon how you intend to manage and use them.

Okay, let's start at the top; are you using a class for these subdocuments? If the answer is "Yes sir!" then chances are that your subdocuments are quite complex and has a section in your
application all to itself with its own controller and everything like, for example, comments on a bog post.

Now the second question you must ask yourself; are you replacing these subdocuments every time you save them or do you want to use modifiers such as `$push`, `$pull`, `$pullAll`, `$pushAll`,
`$addToSet` ectera?

If you wish to use modifiers each time then the best way to manage these type of documents is to make the subdocument singular class extend `EMongoModel`, for example, `Comment`
would extend `EMongoModel`.

When, say, adding a comment to a post you would do:

	if(isset($_POST['Comment'])){
		$comment=new Comment;
		$comment->attributes=$_POST['Comment'];
		if($comment->validate())
			$response = Post::model()->updateAll(array('_id' => $someId), array('$push' => $comment->getRawDocument()));
	}

And you would use relatively similar behaviour for most other operations you need to perform. In this case MongoYii merely acts as a helper and glue for you to make life a little easier,
however, at the end of the day it will not auto manage subdocuments for you.

If you are not using a class then chances are your subdocuments are quite primative and most likely are just detail to the root document and you are replacing them each time. This scenario
also applies if you are using complex classes but you are replacing the subdocument list on each save.

If this is the case you can either use the subdocument validator mentioned above to process your subdocuments or you can actually programmably do this:

	$valid=true;
	$user=User::model()->findBy_id($uid);
	if(isset($_POST['numbers'])){
		foreach($_POST['numbers'] as $row){
			$d=new Model();
			$d->attributes = $row;
			$valid=$d->validate()&&$valid;
			$user->numbers[] = $d;
		}
	}
	if($valid) $user->save();

as an example.

As an added side note you can actually treat the array fields within your document that contain the subdocuments the same as any other field. For example this will work:

	$m=new Something();
	$m->name='thing';
	$parentClass->things[6] = $m;
	$parentClass->save();

So subdocuments are very flexible in this extension and they do not corner you into thinking one way and one way only, much like MongoDB itself really.

## Using the ActiveDataProvider

This extension comes with a `CActiveDataProvider` helper called `EMongoDataProvider`. It works exactly the same way except for how it is called.

Instead of using a `EMongoCriteria` or something similar you use arrays like so:

	new EMongoDataProvider(array(
		'criteria' => array(
			'condition' => array(),
			'sort' => array(),
			'skip' => 1,
			'limit' => 1
		),
		/* All other options */
	));

The `criteria` option basically relates to the parts of a cursor.

This extension does fully support `CGridView` (thanks to @acardinale for the fix) and it should also be able to take the `CListView` as well.

As a side note to the above, `CGridView` is best used when you predefine the schema you wish to display within the definition of the `CGridView` widget. So, to display an example
for a user model:

	$this->widget('zii.widgets.grid.CGridView', array(
		'id'=>'user-grid',
		'dataProvider'=>$model->search(),
		'filter'=>$model,
		'columns'=>array(
			'_id',
			'username',
			'addresses',
			'create_time',
			array(
				'class'=>'CButtonColumn',
				'template'=>'{update}{delete}',
			),
		),
	));

This is normally the best method because, of course, MongoDB is schemaless (has a flexible schema is more appropriate) so sometimes it doesn't work so well in a rigid table.

## EMongoCriteria

The `EMongoCriteria` class can help build modular queries across many segments of your application providing an abstracted layer with helper functions enabling you to better create complex
queries.

A brief, yet complete, example of using the `EMongoCriteria` would be:

	$c = new EMongoCriteria();
	User::model()->find($c
					->addCondition(array('name' => 'sammaye')) // This is basically a select
					->addOrCondition(array(array('interest' => 'Drinking'), array('interest' => 'Clubbing'))) // This is adding a $or condition to our select
					->skip(2) // This skips a number of rows
					->limit(3) // This limits by a number of rows
						);

So you can see that quickly we can build very complex queries with ease.

Just like with `CDbCriteria` you can also set all of these properties of the query straight from the constructor like so:

	$c = new EMongoCriteria(array(
		'condition' => array('name'=>'sammaye'),
		'limit' => 10
	));

The EMongoCriteria class implements many of the functions you would expect of CDbCriteria.

### setCondition() / getCondition()

These basically just sets and gets the condition of the query.

### addCondition()

Adds a normal, non `$or` condition to the query and takes an `array` as its only parameter.

### addOrCondition()

Adds an `$or` condition and takes an array of `arrays` as its only parameter with each nested `array` being a condition within the `$or` (just like in the driver).

It would be wise to note that calling this function will overwrite any `$or` previously placed in the condition.

### getSort() / setSort()

Get and set the sort of the query.

### getSkip() / setSkip()

Get and set the skip of the query.

### getLimit() / setLimit()

Get and set the limit of the query.

### getProject() / setProject()

Sets the projection of the criteria to state specific fields to include/omit.

### getSelect() / setSelect()

These provide aliases for `getProject()` and `setProject()`.

### compare()

This works a lot like `CDbCriteria`s and is heavily based on it.

You simply enter `column`, `value` and `matchPartial` parameter values (in that order) and the `EMongoCriteria` class will create a condition and merge it into your current condition
based upon the entered data. As examples:

	$c->compare('name', 'sammaye');

	$c->compare('i', '<4');

The compare funtion, as seen in the second example, will accept a certain number of operators. The operators supported are: `<>`, `<=`, `>=`, `<`, `>`, `=`.

It is good to note that the function currently only accepts `AND` conditioning.

### mergeWith()

Just like in `CDbCriteria` this merges either an array or another `EMongoCriteria` object into this one, transferring all of its properties.

As an example:

	$c->mergeWith($otherC);

Now `$c` will have all the merged properties of `$otherC`.

### toArray()

This basically will convert your EMongoCriteria into array form of the syntax:

	array(
		'condition' => array(),
		'skip' => 1,
		'limit' => 1,
		'sort' => array(),
		'project' => array()
	)

and, by default, is called like:

	$c->toArray();

## Covered and Partial Queries

When you do not wish to retrieve the entire document you can instead just return a partial result.

Both the `EMongoCriteria` and normal array based querying supports projection through two methods. First as a `project` variable in either `EMongoCriteria`:

	$c->project=array('_id'=>0,'d'=>1);

Or as an element within the defined array (a scope as an example):

	functions scope(){
		return array(
			'project' => array('_id'=>0,'d'=>1)
		);
	}

And second, as a parameter injected into the read functions of the active record model, as an example:

	User::model()->find(array(),array('_id'=>0,'d'=>1));

These will return `partial=true` `EMongoDocument` instances, either eagerly or in a cursor object. This specification is implemented within all currently existing read functions such as
`findOne` and `findBy_id` and `findByPk` however, they are not accepted within the write functions (`update`, `insert`, `save` etc).

When a document is returned as partial it will only save the root level fields that are included within the result of the query.

**Note:** When using `$elemMatch` projection you must bare in mind that MongoYii will treat that result as the definitive result for that field. In other words when you go to save the
root document MongoYii will consider that single projected subdocument the complete field value and will erase all other subdocuments within that field.

**Note:** If `_id` is omitted via `'_id' => 0` from the root document then you will not be permitted to save the document at all. The extension will instead throw an exception about the
`_id` field not being set.

## GridFS

MongoYii has a GridFS handler called `EMongoFile`. This class is specifically designed as a helper and is in no way required in order to use GridFS with MongoYii. What it does is
make it easy to upload, save and then retrieve files from GridFS. It is specifically oriented around uploading files from a form.

Let's go through an example of its usage as taken from the example in the [test repository](https://github.com/Sammaye/MongoYii-test/blob/master/protected/controllers/UserController.php#L67).
To upload a new file from a form you simply call the `populate` static function on the class like so:

	EMongoFile::populate($model,'avatar')

This essentially says: *"Get the uploaded file from the model `user` and the field `avatar`"* The rest works much the same as a normal upload form. If `populate` returns anything except
`null` then it has found something.

To save the file to GridFS simply call `save()`. The class directly extends `EMongoDocument` as such this means that you have access to all the normal active record stuff as in
other classes.

If you wish to add a validator for the file object itself you must point it to the `file` variable of the class; be sure to only allow validators for the file object on `create`
otherwise Yii will not know how to handle the `MongoGridFSFile` object.

**Note:** Currently if you choose to call save on update it will overwrite the previous file. No versioning has been implemented.

Retreiving the file later is just as easy as saving it and is no different to finding any other record:

	EMongoFile::model()->findOne(array('userId'=>Yii::app()->user->id))

This code snippet assumes we wish to find a file whose metadata field `userId` is of the current user in session.

## Using urlManager

If you wish to regex out the `_id` within a URL for use with the urlManager you can use:

	'<controller:\w+>/<action:\w+>/<id:[a-z0-9]{24}>'=>'<controller>/<action>',
	
Whereby it will try and pick out a alphanumeric `_id` of 24 characters in length. 

## Versioned Document Models

2.5.x of MongoYii adds the ability to version your documents. 

If you are confused about versioning or how it can be beneficial for some scenarios then a well explained, yet simple and easy to read [blog post can actually be found by the 
creators of MongoDB describing its addition to Mongoose](http://aaronheckmann.tumblr.com/post/48943525537/mongoose-v3-part-1-versioning).

To setup a versioned document you can simply create a model implementing `version()` which returns `true` and, optionally, `versionField()`:

	class versioned extends EMongoDocument{
		public function versioned(){
			return true;
		}
		
		public function versionField(){
			return '_v'; // This is actually the default value in EMongoDocument
		}
		
		public static function model($className=__CLASS__){
			return parent::model($className);
	    }	
	}

The verisoning ability of a document cannot be changed during runtime once it has been set, in other words you cannot do `$doc->removeVersion()` to stop versioning from having 
an effect for a certain insert. 

After the documents model has been setup versioning works behind the scenes, there is no need for you to do anything else, everytime `save` is called it will make sure the 
version you have is upto date.

## Database migrations

Even though MongoDB is schemaless, you sometimes may need to modify your records. To do so, you may use the `yiic migratemongo` command. 
It works exactly like `yiic migrate`. For detailed usage, please refer to the [yii docs](http://www.yiiframework.com/doc/guide/1.1/en/database.migration).

To enable the command in your application, add a `commandMap` entry in your config file:

    'commandMap' => array(
        'migratemongo' => array(
            'class' => 'application.extensions.MongoYii.util.EMigrateMongoCommand'
        )
    )

## Known Flaws

- Subdocuments are not automated, however, I have stated why above
- the aggregation framework does not fit well with active record as such it is not directly supported within the models, however, there is an `aggregate` helper on each model but
it will not return a model instance but instead the direct result of the MongoDB server response.

I am sure there are more but that is the immediate flaws you need to consider in this extension.

## Bugs

Probably some, however, I will endeavour to accept pull requests and fix reported bugs.

Please report all issues, including bugs and/or questions, on the [GitHub issue tracker](https://github.com/Sammaye/MongoYii/issues). 

## Examples

Please look to the tests folder for further examples of how to use this extension, it is quite comprehensive.

There is also a demonstration application built using MongoYii. It is effectively mimicking a Wikipedia type website and allows for user (including sessions) and article management. 
It is not a good place to start if you are still learning Yii, however, it is a good place to start if you are learning MongoYii. 

[You can find the demonstration application repository here](https://github.com/Sammaye/MongoYii-test).

## Running the Tests

The tests require the PHPUnit plugin with all dependencies compiled. Using PEAR you can initiate the following command:

	sudo pear install --force --alldeps phpunit/PHPUnit &&
	pear install phpunit/dbUnit &&
	pear install phpunit/PHPUnit_Story &&
	pear install phpunit/PHPUnit_Selenium &&
	pear install phpunit/PHP_Invoker

After that you can just tell PHPUnit to run all tests within the `tests/` folder with no real order.

## Contributing

When adding extensive functionality to MongoYii please try and provide the corresponding unit tests. Without the unit tests your functionality, the very same your project most 
likely relies on, may break in future versions.

If you are intending to contribute changes to MongoYii I should explain my own position on the existance of the `EMongoCriteria` class. I, personally, believe it is not needed.

There are a number of reasons why. In SQL an abstraction is justified by, some but not all, of these reasons:

- Different implementations (i.e. MySQL and MSSQL and PostgreSQL) creates slightly different syntax
- SQL is a string based querying language as such it makes sense to have an object oriented abstraction layer
- SQL has some rather complex and difficult to form queries that would make an abstraction layer useful

MongoDB suffers from none of these problems; first it has an OO querying interface already, secondly it is easy to merge different queries together simply using `CMap::MergeArray()`
and most of all it has only one syntax since MongoDB is only one database. On top of this, due to the way MongoDBs querying is built up this class can actually constrict your querying
and make life a little harder and maybe even create unperformant queries (especially due to how difficult it is to do `$or`s in this class).

As such I believe that the `EMongoCriteria` class is just dead weight consuming memory which I could use for other tasks.

This extension does not rely on `EMongoCriteria` internally.

So I expect all modifications to certain parts of MongoYii to be compatible with and without `EMongoCriteria`.

## Utilities

The `util` folder contains general awesome extensions to MongoYii that people may find useful. The sort of things that count as part of this folder are replacements for internal pieces 
of Yii that might seem outside of the scope of the root of this repository.

### EMongoCache

This is a MongoYii implementation of `CCache` by [Rajcsányi Zoltán](http://ezmegaz.hu/).

To use it first place it in your configuration:

	'components'=>array(
		...
		'cache' => array(
			'class'=>'application.extensions.MongoYii.util.EMongoCache',
			// 'ensureIndex' => true, //set to false after first use of the cache
			// 'mongoConnectionId' => 'mongodb',
			// 'collectionName' => 'mongodb_cache',		
		),
	}

The commented out lines are optional parameters you can send in if required.

And now an example of its usage: 

	// flush cache
	Yii::app()->cache->flush();
	
	// add data to cache
	Yii::app()->cache->set('apple', 'fruit');
	Yii::app()->cache->set('onion', 'vegetables');
	Yii::app()->cache->set(1, 'one');
	Yii::app()->cache->set(2, 'two');
	Yii::app()->cache->set('one', 1);
	Yii::app()->cache->set('two', 2);
	
	// delete from cache
	Yii::app()->cache->delete(1);
	Yii::app()->cache->delete('two');
	
	// read from cache
	echo Yii::app()->cache->get(2);
	
	// multiple read from cache
	$arr = Yii::app()->cache->mget(array('apple', 1, 'two'));

  	print_r($arr); // Array( [apple] => fruit [1] => [two] => )
  	
### EMongoMessageSource

This is a MongoYii `Yii::t()` implementation by [Rajcsányi Zoltán](http://ezmegaz.hu/).

To use it first add it to your configuration:

	'components' => array(
		...
		'messages' => array(
			'class' => 'application.extensions.MongoYii.util.EMongoMessageSource',
			// 'mongoConnectionId' => 'mongodb', 
			// 'collectionName' => 'YiiMessages',               
		)        
	)

The commented out lines are optional parameters you can send in if required.

And then add some messages to the translation table:

	db.YiiMessages.insert( { category:"users", message:"Freund", translations: [ {language:"eng", message:"Friend"} ] } );

And then simply get that message:
  
	<?=Yii::t('users', 'Freund'); ?>
	
### EMongoSession

This is a MongoYii `CHttpSession` implementation by yours truly.

To use it simply include it in your configuration:

	'session' => array(
		'class' => 'application.extensions.MongoYii.util.EMongoSession',
	)

And use it as you would Yiis own normal session.

### EMongoAuthManager

This is a MongoDB replacement for Yiis auth manager by [@tvollstaedt](https://github.com/tvollstaedt).

To use it simply place it in your configuration:

	'authManager' => array(
    	'class' => 'EMongoAuthManager',
    )
    
It will work the same way as any other auth manager. 

**Note:** You may want to use [Database migrations](#database-migrations) to keep authorization settings across your application instances up to date.

### EMongoPagination

This is a replacement `CPagination` for MongoYii built by [@kimbeejay](https://github.com/kimbeejay).

It uses the same API as `CPagination` and requires no extra documentation (outside of `CPagination`) aside from making you aware of its existance.

### EMongoCacheDependency

This is to enable MongoYiis edition of [caching](http://www.yiiframework.com/doc/guide/1.1/en/caching.data).

Example usage of this class would be:

	$cache = Yii::app()->cache;
	$cache->set(
		'12', 
		'dfgdfgf', 
		30,
		new EMongoCacheDependency('t', [
			[],
			'limit' => 5
		])
	);
	var_dump($cache->get('12'));

would return `dfgdfgf` when the cache is not invalid but if you invalidate it it will return `false` per the documentation.

As such if I were then to run:

	$cache = Yii::app()->cache;
	Yii::app()->mongodb->t->insert(['g' => 1]);
	var_dump($cache->get('12'));

I would get false as the return value.

The constructor for this cache class accepts two parameters, one being the collection name and the other being the query.

The first (`0`) index of the query parameter will always be the `find()` query, this is in fact how the query parameter is parsed by the class:

	$query = array();
	if(isset($this->query[0])){
		$query = $this->query[0];
	}
		
	$cursor = $this->getDbConnection()->{$this->collection}->find($query);
		
	if(isset($this->query['sort'])){
		$cursor->sort($this->query['sort']);
	}
		
	if(isset($this->query['skip'])){
		$cursor->limit($this->query['skip']);
	}
		
	if(isset($this->query['limit'])){
		$cursor->limit($this->query['limit']);
	}

currently the quey parameter of this class only accepts the parts to be shown as parsed above, it does not currently allow you to actually grab the cursor directly.

**Note:** Do not put a cursor into this class, it will not save to your datastore in a manner that the PHP driver for MongoDB will be able to use it. Instead you will be 
told that the `MongoCursor` was not correctly inited by its parent class(es).

## Versioning

This project uses [semantic versioning 2.0.0](http://semver.org/).

## Licence

This extension is licensed under the [BSD 3 clause](http://opensource.org/licenses/BSD-3-Clause). To make it short and to the point: do whatever you want with it.
