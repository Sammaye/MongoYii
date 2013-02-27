# MongoYii

Another active record handler for the Yii framework that supports MongoDB.

## Rationale

There is already a great extension called YiiMongoDBSuite out for Yii so why make another? YiiMongoDBSuite has certain flaws which I wish to address:

- Does not support `$or` natively
- Very large and complicated code base
- Does not support the later versions of the PHP driver (1.3.x series) that well
- Obscured the MongoDB query language, layering a query language over the top

After some spare time I decided that I would take the liberty to make a MongoDB extension for Yii. It is really basically a "glue" between MongoDB and
Yii and it is designed to be quite free form in that respect.

There are a few points of design I wished to enforce:

- expose the MongoDB query language in its raw form
- make the programming of this extension simple and easy to maintain for all parties
- make sure this extension worked with both the new and old versions of the MongoDB driver
- attempt to make things a little more performant
- try to follow Yiis own CActiveRecord API as much as possible without compromising MongoDB "semantics" such as the name for query operators and the use of a `MongoCursor`

Ok so we have got some of the rationale in place it is time to actually talk about the extension.

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
	'application.extensions.MongoYii.behaviors.*'

That is the basic setup of the extension.

You will notice that I use a `EMongoClient`. This is a bit deceptive since it actually represents `MongoClient` and `MongoDB` combined.  This means that whenever you call the magic `__call`
on the `EMongoClient` like so:

	Yii::app()->mongodb->getSomething();

It will either try and call a function of `getSomething` in `EMongoClient` or, if the function does not exist, try and call it within the `MongoDB` class.

If you wish to call a function on the `MongoClient` or `Mongo` class you will need to retrieve the connection object like so:

	Yii::app()->mongodb->getConnection()->getSomething();

`EMongoClient` is also designed to handle full write concern and read preferences in a compatible manner with all verisons of the driver.

**Note:**The component within your configuration MUST be called `mongodb` otherwise you will need to feed the component in manually into each of your models.

### Write Concern (formally "safe" writes)

This extension uses the new `w` variable globally to handle the level of write concern you wish to impose on MongoDB.

By default the extension will assume acked writes, this means `safe=true` or `w=1` depending on the version of your driver. To change this simply add `w` to your `mongodb` components configuration
and give it a value according to the PHP documentation: http://php.net/manual/en/mongo.writeconcerns.php

For those using the 1.3.x series of the driver there is also a `j` option which can be set to either `true` or `false` within the configuration which allows you to control
whether or not the write is journal acked.

**Note:** Write Concern is abstracted from the driver itself to make this variable compatible across all verisons of the driver so please use the configuration or the `EmongoClient` `w` and
`j` class variables to set the write concern when you need to otherwise that write concern will not be used within active record.

**Note:** Write Concern works differently when you touch the database directly and the write concern issued within the `EMongoCLient` class will have no
effect. Instead you should always ensure in this case you specify the write concern manually according to your driver version.

This may change in the future but at the moment when you want the active record to go away it just will.

### Read Preference

For those using the old driver there is only one extra configuration variable available to you, `setSlaveOkay`. Set this either `true` or `false` in your configuration to make it
possible to read from members of a replica set.

For those using the 1.3.x series of the driver you have the `RP` configuration variable. The RP configuration variable is a 1-1 related options array to the `setReadPreference` function
on the `MongoClient` class with one exception. The first parameter is not a constant but instead the name of the constant. An example of using read preferences in your configuration:

	'RP' => array('RP_SECONDARY' /* The name of the constant from the documentation */,
		array(/* Would normally be read tags, if any */))

Please refer to the drivers documentation for a full set of options here: http://php.net/manual/en/mongo.readpreferences.php

To change the Read Preference at any time please use the function applicable to your driver; for 1.3.x series:

	Yii::app()->mongodb->setReadPreference(MongoClient::RP_PRIMARY, array());

and for pre-1.3:

	Yii::app()->mongodb->setSlaveOkay(true);

**Note:** Unlike write concern, the `RP` and `setSlaveOkay` variables do not interlock between different versions of the driver, using the `EMongoClient` `RP` variable
will not translate to `slaveOkay`.

## Using MongoDB without Active Record

You can call the database directly at anytime using the same implemented methods as you would using the driver normally. As an example, to get the test database:

	Yii::app()->mongodb->test

And then to query the test database:

	Yii::app()->mongodb->test->collection->find(array('name' => 'sammaye'));

So the active record element of MongoYii can quckly disappear if needed.

## EMongoModel

The `EMongoModel` is a stripped down version of the `EMongoDocument`.

This was made separate from `EMongoDocument` to provide a small and slim active model for use on subdocuments. Whenever you make a class based subdocument you can extend this class.

The `EMongoModel` implements all that `CModel` does but with a few added and changed features.

### Magic functions

In order to support the schema-less nature of MongoDB without using hacks like behaviours I have changed the way that the magic functions in Yii work slightly.
The `__set` and `__get` will no longer seek out behaviour properties or call variable function events.

Behaviours tend to manipulate a `owner` within its own self contained context while allowing the calling of events from the magic functions is role blurring. Events should be
called as functions if you want to use them. In my opinion the `__set` and `__get` function has been made clearer by this.

### Virtual Attributes

This extension supports virtual attributes via a doc block notation syntax of `@virtual`, for example:

	class User extends EMongoModel{
	    /** @virtual */
	    public $somevar;
	}

These variables can be used in the same way as everything else except they will never be saved in MongoDB.

**Note:** due to how PHP OO accession works it is a good idea to make all your record fields, virtual or not, `public`.

### Relations

Unlike in SQL where you have many complicated types of relation, in MongoDB you tend to only have two:- `one` and `many`.

As you have guessed, you can only define two types of relation in this extension - `one` and `many`. Lets take a look at an example:

	function relations(){
		return array(
			'others' => array('many', 'Other', 'otherId')
		);
	}

You will recognise a lot of this from Yiis own active record, in fact a lot is the same. We define a name for the relation as a key and then we define either `one` or `many` in text
(constants seemed useless with only two types) and then we define a class name, `Other` in this case, and then we define the foreign key in that class, `otherId`.

The default behaviour of relations is to attempt to use the primary key, `_id`, of the current model to query the foreign key. This is a problem for `EMongoModel`
since it has no primary key. Make sure that if you use this in `EMongoModel` you define a `on` clause to replace the primary key of the current model.

The `on` clause supports multiple field types. It can take a `DBRef` or an `ObjectId` or an array of `ObjectId`s depending on how you define your document.

You can also, just like in Yii, define a `where` clause. This is a 1-1 relation to the syntax used within normal querying in MongoDB and the extension will basically merge this
clause with the primary key field you define in order to query for the relation.

All relations are returned as `EMongoCursor`s which is basically the Yii active record implementation of `MongoCursor`. There is no eager loading, if you wish to use eager loading
please look into using `iterator_to_array()` on the return value from calling the relation.

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

**Note:** The functions that allow database usage are not defined within this section of the documentation. Instead those functions are actually defined within the "Querying" section of this
documentation. Please move to the "Querying" section if you wish to read about this part of the `EMongoDocument`.

### collectionName()

Returns a string representing the collection name. All active record models should implement this function although it is not `abstract`.

### primaryKey()

Currently only returns `_id` as the key. This function is `private` and cannot be overridden.

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

**Note:** Just like in Yii normally scopes are not reset automatically, please use `resetScope()` to reset the scope.

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

It is normally best not to use this and instead to use the extension wrapped editions - 'updateAll` and `deleteAll`. The only difference of said functions
from doing it manually on `getCollection()` is that the functions understand the write concern of the extension.

## Querying

Querying attempts to expose the native MongoDB querying as much as possible. A `EMongoCriteria` class is provided however, it is not required and does not provide any more functionality
than just doing it via arrays. The `EMongoCriteria` class is not relied on anywhere and is not needed.

### find()

`find()` is really simple. It is essentially a 1-1 to the drivers own `find()` function and implements the same specifics. Just like the drivers edition, it also returns a cursor
instance (`EMongoCursor`) which can be used to lazy load results from the database.

It will return a cursor irrespective of whether it finds results or not. However if it cannot find results then `count` will return `0` and the iterator will not have any iterations
to it.

**Note:** The cursor does not eager load documents, instead if you wish to accomplish this please wrap the call to `find` in a `iterator_to_array` function.

### findOne() and findOneBy_id()

`findOne`, just like `find` is a straight 1-1 implementation of the drivers own `findOne` method and returns an active record record model if something was found, otherwise `null`.

The `findOneBy_id` function takes either a hexadecimal representation of a `ObjectId` in string form or wrapped in the `MongoId` class and will seek out a record with that `_id` using
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
- `->sort()` is basically the MongoDB driver `sort` method on the `MongoCursor`
- `->limit()` is again basically the MongoDB drivers own `limit` function on the `MongoCursor`

For a reference on what operators are supported please refer to the MongoDB documentation: http://docs.mongodb.org/manual/reference/operators/

**Note:** other functions like `findByAttributes` have been omitted since it seems pointless with MongoDBs querying language to implement those.

### save()

This `save`s the document and is used externally as a means to access either `insert` or `update` on the active record model, i.e.:

	if($user->validate()) $user->save();

If the document is new it will insert otherwise it will update.

### insert()

This is used internally by the active record model. If the record is new it will attempt to insert it instead of updating it otherwise it will throw
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

Note: `UpdateAll` is `multi` `true` by default

## Validation

The validation has pretty much not changed except for one validator which required some rewriting, the unique validator.

Basically the `CUniqueValidator` is retro-fitted to work for MongoDB so the call to the validator is the same but you must take into account that the name of the
validator is now `EMongoUniqueValidator`.

## Subdocuments

Subdocuments are, mostly, not automatically supported by this extension. There a couple of reasons, firstly due to performance - automating subdocument usage requires a lot of
loaded classes to handle different subdocuments and their validation.

The other main reason is that, in any project I have done, whenever I tried to automate subdocuments through active record it has always resulted in me actually ditching
it and doing the process manually. It has been proven many times that you rarely actually want automated subdocuments and normally you want greater control over their storage than
this extension could provide.

So that is a brief understanding of the rationale behind the idea to ditch automatic subdocument handling within the active record.

This does not mean you cannot embed subdocument classes at all, when saving, the active record will iterate the document and attempt to strip any `EMongoModel` or `EMongoDocument`
classes that have sprung up.

This all aside, is a subdocument validator and technically it can even accept multi-level nesting. Please bare in mind, though, that it will cause repitition
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

**Note:** While on the subject, to avoid the iteration every time you save the root document (since validation is run by default in Yii on save) you should confine your subdocument
validators to specific scenarios where they will be actively used.

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

Note: This does not work with `CGridView` due to how Yii core expects a `CActiveRecord` and uses that class directly for some parts of the widget.

## Known Flaws

- Covered queries are not supported, but then as I am unsure if they really fit with active record
- Subdocuments are not automated, but again I have stated why above
- the aggregation framework does not fit well with active record as such it is not directly supported within the models

I am sure there are more but that is the immediate flaws you need to consider in this extension.

## Bugs

Probably some, however, I will endeavour to accept pull requests and fix reported bugs.

## Examples

Please look to the tests folder for examples of how to use this extension, it is quite comprehensive.

## Running the Tests

The tests require a fully complied version of PHPUnit. Using PEAR you can initiate the following command:

	sudo pear install --force --alldeps phpunit/PHPUnit &&
	pear install phpunit/dbUnit &&
	pear install phpunit/PHPUnit_Story &&
	pear install phpunit/PHPUnit_Selenium

After that you can just tell PHPUnit to run all tests within the `tests/` folder with no real order.