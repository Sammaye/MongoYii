# YiiMongo

Another active record handler for the Yii framework that supports MongoDB.

## Rationale

There is already a great extension called YiiMongoDBSuite out for Yii so why make another? YiiMongoDBSuite has certain flaws which I wish to address:

- Does not support `$or` natively
- Very large and complicated code base
- Does not support the later versions of the driver that well
- Obscured the MongoDB query language and so querying against it

So after understanding these "flaws" about the YiiMongoDBSuite I decided after a while that I would make my own extension. After some spare time (a year later) I
finally got to make my own extension, and this is the result.

It is really basically a "glue" between MongoDB and Yii and it is designed to be quite free form in that respect.

There are a few points of design I wished to enforce that need to be taken into consideration:

- expose the MongoDB query language in its raw form
- make the programming of this extension simple and easy to maintain for all parties
- make sure this extension worked with both the new and old versions of the MongoDB driver
- attempt to make things at least a little more performant
- try to follow Yiis own CActiveRecord API as much as possible without compromising MongoDB "semantics" such as the name for query operators and the use of a `MongoCursor`

Ok so we have got some of the rationale in place it is time to actually talk about the extension.

## Setting up the extension

Not much has changed from YiiMongoDBSuite here, you simply add the configuration for your connection to your `main.php` and/or `console.php` file within the `components` part:

	'mongodb' => array(
		'class' => 'EMongoClient',
		'server' => 'mongodb://localhost:27017',
		'db' => 'super_test'
	),

And add the YiiMongo directories to your `import` section:

	'application.extensions.MongoYii.*',
	'application.extensions.MongoYii.validators.*',
	'application.extensions.MongoYii.behaviors.*'

There are however some differences. You will notice that, first off, the class I use is `EMongoClient`. This is a bit deceptive since it actually represents `MongoClient` and `MongoDB`
combined. I combined them because of how Yii works on this front, it made it easier for everyone to jump start the actual database handling from the configuration.

This does mean that whenever you call the magic `__call` on the `EMongoClient` like so:

	Yii::app()->mongodb->getSomething();

It will either try and call a function of `getSomething` in `EMongoClient` or, if the function does not exist, try and call it within the `MongoDB` class.

`EMongoClient` is also designed to handle "safe" (such a bad word for what it really is) writes and read preferences differently to YiiMongoDBSuite. It is fully compatible with the
1.3.x series of the MongoDB driver.

### Write Concern (formally safe writes)

This extension uses the new `w` variable globally to handle the level of write concern you wish to impose on MongoDB.

Note: Write Concern is abstracted from the driver itself to make this variable compatible across all verisons of the driver so please use the configuration or the `EmongoClient` `w` and
`j` class variables to set the write concern when you need to otherwise that write concern will not be used within active record.

Note: Write Concern works differently when you touch the database directly (currently, not sure about this) and the write concern to use within the `EMongoCLient` class will have no
effect. Instead you should always ensure in this case you specify the write concern manually or globally according to your driver version.

By default the extension will assume acked writes, this means `safe=true` or `w=1` depending on the version of your driver. To change this simply add `w` to your `mongodb` component
and give it a value according to the PHP documentation: http://php.net/manual/en/mongo.writeconcerns.php

For those using the 1.3.x series of the driver there is also a `j` variable which can be set to either `true` or `false` within the configuration which allows you to control
whether or not the write is journal acked.

### Read Preference

For those using the old driver there is only one extra configuration variable available to you, `setSlaveOk`. Set this either `true` or `false` in your configuration to make it
possible to read from members of a replica set.

For those using the 1.3.x series of the driver you have the `RP` configuration variable. The RP configuration variable is a straight constructor to the `setReadPreference` function
on the `MongoClient` class with one exception. The first parameter is not a constant but instead the name of the constant. An example of using read preferences in your configuration:

	'RP' => array('RP_SECONDARY' /* The name of the constant from the documentation */, array(/* Would normally be read tags */))

Please refer to the documentation for a full set of options here: http://php.net/manual/en/mongo.readpreferences.php

To change the Read Preference at any time please use the function applicable to your driver, i.e. for 1.3.x series:

	Yii::app()->mongodb->setReadPreference(MongoClient::RP_PRIMARY, array());

and for pre-1.3:

	Yii::app()->mongodb->setSlaveOkay(true);

Note: Unlike write concern, the `RP` and `setSlaveOk` variables do not interlock between different versions of the driver, using the `EMongoClient` `RP` variable
will not translate to `slaveOkay`.

## EMongoModel

The `EMongoModel` is a stripped down version of the `EMongoDocument` which actually extends this class.

This was made separate from `EMongoDocument` to provide a small and slim active model for use on subdocuments. Whenever you make a class based subdocument you can extend this class
to prevent unneeded functions and code from polluting your coding space.

The `EMongoModel` implements all that `CModel` does but with a few added and changed features.

### Magic functions

In order to support the schema-less nature of MongoDB without using hacks like behaviours to pretend this I have changed the way to that magic functions in Yii work slightly.
The `__set` will now no longer will seek out behaviour properties or call variable function events.

Behaviours tend to manipulate a `owner` within its own self contained context while allowing the calling of events from the magic functions is role blurring. Events should be
called as functions if you want to use them. As far as I am concerned no real functionality has been lost, instead the `__set` function has been made more clear but only setting
relations and properties from now on.

### Virtual Attributes

This extension supports virtual attributes via a doc block notation syntax of `@virtual`, for example:

	class User extends EMongoModel{
	    /** @virtual */
	    public $somevar;
	}

These variables can be used in the same way as everything else except they will never be saved in MongoDB.

### Relations

Defining a relation and how they are returned has been changed now. Unlike in SQL where you have many complicated types of relation in MongoDB you tend to only have two:-
`one` and `many`.

As you have guessed it you can only define two types of relation in this extension - `one` and `many`. Lets take a look at some examples:

	function relations(){
		return array(
			'others' => array('many', 'Other', 'otherId')
		);
	}

You will recognise a lot of this from Yiis own active record, in fact a lot is the same. We define a name for the relation as a key and then we define either `one` or `many` in text
(this is because constants seemed useless with only two states) and then we define a class name (`Other` in this case) and then we define the foreign key in that class (`otherId`).

The default behaviour of relations is to attempt to use the primary key, `_id`, of the current model as the primary key to query the foreign key. This is a problem for `EMongoModel`
since it has no primary key (subdocument semantics) so make sure that, if you use this in `EMongoModel` you define a `on` clause to replace the primary key of the current model.

The `on` clause support multiple definitions. It can take a `DBRef` or an `ObjectId` or an array of `ObjectId`s depending on how you define your document.

You can also, just like in Yii, define a `where` clause. This is a 1-1 relation to the syntax used within normal querying in MongoDB and the extension will basically merge this
clause with the primary key field you define in order to query for the relation.

All relations are returned as `EMongoCursor`s which is basically the Yii active record implementation of `MongoCursor`. There is no eager loading, if you wish to use eager loading
please look into using `iterator_to_array()` on the relation when you retrieve it.

### Document Retrieval functions

The `EMongoModel` has numerous helper functions to aid the user in retrieving information is a format most suitable for them.

#### getDocument()

Just gets the docuemnt "as-it-is". This means that if you put meta objects in like nested `EMongoModel`s it will get these back in the output.

#### getRawDocument()

Will strip away all classes used by the extension and return a document suitable for use with MongoDB.

#### getJSONDocument()

Will run `getRawDocument()` and then return its output as a JSON string.

#### getBSONDocument()

Will run `getRawDocument()` and then return its output as a BSON string.

## EMongoDocument

The `EMongoDocument` extends `EMongoModel` and implements all of its features along with the needed feature for database accession. It also implements as much as possible of
`CActiveRecord`.

The functions that allow database usage are not defined within this section of the documentation. Instead those functions are actually defined within the "Querying" section of this
documentation. Please move to the "Querying" section if you wish to read about this part of the `EMongoDocument`.

### collectionName()

Returns a string representing the collection name. All active record models should implement this function although it is not `abstract`.

### primaryKey()

Currently only returns `_id` as the key. This function is `private` and cannot be overridden.

### Scopes

Scopes are fully supported in all the normal ways with CActiveRecord but with one difference. The terminology.

The scopes, and queries, in this extension use these words to describe their parts:

- `condition` to describe the condition itself (i.e. `array('deleted' => 1)`)
- `sort` to describe the sort (i.e. `array('deleted' => -1)`)
- `skip` to describe offset (i.e. `2`)
- `limit` to describe limit (i.e. `3`)

As an example of a full default scope which omits deleted models to get the latest 10 skipping the first one:

	array(
		'condition' => array('deleted' => array('$ne' => 1)),
		'sort' => array('date' => -1),
		'skip' => 1,
		'limit' => 11
	)

### equals()

Checks if the current model equals another sent in as a parameter.

### exists()

Checks if a document exists in the data with the criteria supplied as the first parameter.

### clean()

Cleans the document of all properties and relations.

### refresh()

Runs `clean()` and then re-populates the model from the database.

### getCollection()

Returns the raw `MongoCollection`. Best not to use this and instead to use the extension wrapped editions - 'updateAll` and `deleteAll`. The only difference of said functions
from doing it manually on `getCollection()` is that the functions understand the write concern of the extension.

## Querying

I wanted to expose MongoDBs query language for what it was so even though I have added a `EMongoCriteria` class, it is not required and does not provide any more functionality
than just doing it via arrays. The `EMongoCriteria` class is not relied on anywhere and is not needed to query anything.

This means that this extension supports every operator in MongoDB since it literally passes its query document straight to the driver, no fiddling about in between of the two.

### Read Operations

#### `find()`

`find()` is really simply. It is essentially a 1-1 to the drivers own `find()` function and implements the same specifics. Just like the drivers edition, it also returns a cursor
instance (`EMongoCursor`) which can be used to lazy load results from the database.

It will return a cursor irrespective of whether it finds results or not. However if it cannot find results then `count` will return `0` and the iterator will not have any iterations
to it.

Note: The cursor does not eager load documents, instead if you wish to accomplish this please wrap the call to `find` in a `iterator_to_array` function.

#### `findOne` and `findOneBy_id`

`findOne`, just like `find` is a straight 1-1 implementation of the drivers own `findOne` method and return an active record document if found and null if not found.

The `findOneBy_id` function takes either a hexadeciminal representation of a ObjectId in string form or wrapped in the `MongoId` class and will seek out a record with that `_id` using
the `findOne` function, returning the exact same. It is basically a helper for `findOne` to make your life a little easier.

#### Scopes

The read functions of this extension have full support for scopes within models. Please refer to the models section to understand this better.

#### Example

Ok so now we have a basic grasp of querying lets look at an example:

	$c = User::model()->recently()->find(array('deleted' => 0))->sort(array('joined' => -1))->skip(2)->limit(3);

This may look complicated but I will now break it down for you:

- `User::model()` gets our model
- `->recently()` is actually a scope, this is not needed but good for demonstration purposes
- `->find()` is basically the MongoDB drivers `find` method and returns a `EMongoCursor` which implements a `MongoCursor`
- `->sort()` is basically the MongoDB driver `sort` method on the `MongoCursor`
- `->limit()` is again basically the MongoDB drivers own `limit` function on the `MongoCursor`

For a reference on what operators are supported please refer to the MongoDB documentation: http://docs.mongodb.org/manual/reference/operators/

Note: I omitted the other functions like `findByAttributes` since it seems pointless with MongoDBs querying language to implement those.

### Write Operations

A number of write operations are supported by this extension.

#### save()

This `save()`s the document and is used externally as a means to access either `insert` or `update` on the active record model, i.e.:

    if($user->validate()) $user->save();

#### insert()

This is used internally by the active record model. If the record is new it will attempt to insert it instead of update it.

#### update()

This is used internally the active record model. If the record is not new it will attempt to update it.

If you send in not attributes into either this function or the `save` function it will attempt to do a `save` otherwise it will `$set` those attributes.

#### delete()

This is used to delete the current active record from MongoDB.

#### deleteByPk() and updateByPk()

These are helpers to the update and delete except the act on the database directly, instead of through active record.

To show by example:

	User::model()->deleteByPk($_id[, array('deleted' => 1)[, array('w' => 2)]]);
	User::model()->updateByPk($_id, array('$set' => array('d' => 1)[, array('deleted' => 1)[, array('w' => 2)]]);

Arguments shown in `[]` are optional.

These functions can take both a string and a `MongoId` as the `$_id` parameter.

#### updateAll() and deleteAll()

Same as above really except these translate directly to the MongoDB drivers own `update` and `delete` functions.

Note: `UpdateAll` is `multi` `true` by default

## Validation

## Using the ActiveDataProvider

This extension comes with a `CActiveDataProvider` helper called `EMongoDataProvider`. It works exactly the same way except for how it is called.

Instead of using a `EMongoCriteria` or something similar you use use arrays like so:

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

Note: This does not work with CGridView due to how Yii core expects a `CActiveRecord` and uses that class directly for some parts of the widget.

## Known Flaws

- Covered queries are not supported, but then as I am unsure if they really fit with active record
- Subdocuments are not automated, but again I have stated why above
- the aggregation framework does not fit well with active record as such it is not directly supported within the models

I am sure there are more but that is the immediate flaws you need to consider in this extension.

## Bugs

Probably some, however, I will endevour to accept pull requests and fix reported bugs.

## Examples

Please look to the tests folder for examples of how to use this extension, it is quite comprehensive.