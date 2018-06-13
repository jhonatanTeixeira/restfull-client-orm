[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/badges/build.png?b=master)](https://scrutinizer-ci.com/g/jhonatanTeixeira/restfull-client-orm/build-status/master)
[![Build Status](https://travis-ci.org/jhonatanTeixeira/restfull-client-orm.svg?branch=master)](https://travis-ci.org/jhonatanTeixeira/restfull-client-orm)

## Rest Mapper
### A Restfull Resource Relational Mapper

## 1. Instalation
```shell
composer require vox/restfull-client-mapper
```

## 2. Creating a Transfer Manager

```php
// uses guzzle to reach webservices
$guzzleClient = new GuzzleHttp\Client();

// obtain a client registry, and register all guzzle clients on it
$registry = new Vox\Webservice\ClientRegistry();
$registry->set('some_client', $guzzleClient);

// instantiate a metadata factory, the second argument for the annotation driver
// is a string with the metadata classes that will be created by the driver
$metadataFactory = new Metadata\MetadataFactory(
    new Vox\Metadata\Driver\AnnotationDriver(
        new Doctrine\Common\Annotations\AnnotationReader(),
        Vox\Webservice\Metadata\TransferMetadata::class
    )
);

// A symfony serializer using the provided normalizers on this lib
// is important, however, other normalizers/denormalizers can be used
$serializer = new Symfony\Component\Serializer\Serializer(
	[
    	new Vox\Serializer\Normalizer($metadataFactory),
        new Vox\Serializer\Denormalizer(new ObjectHydrator($metadataFactory))
    ], 
    [
    	new Symfony\Component\Serializer\Encoder\JsonEncoder()
    ]
);
// create a webservice client, its the guy who actualy calls your webservices
$webserviceClient = Vox\Webservice\WebserviceClient($registry, $metadataFactory, $serializer, $serializer);

// Finaly you can obtain a transfer manager
$transferManager = new Vox\Webservice\TransferManager($metadataFactory, $webserviceClient)
```
### 3.1. Using the TransferManagerBuilder
The builder class enables a more configuration style creation of the transfer manager.

The most basic setup would be the following, to obtain a manager with annotations driver, with no caching system:
```php
use Vox\Webservice\ClientRegistry;
use Vox\Webservice\Factory\ClientFactory;
use Vox\Webservice\Factory\TransferManagerBuilder;

$clientRegistry = new ClientRegistry();
$clientFactory = (new ClientFactory())
    ->addClient('foo', 'http://foo.localhost', $clientRegistry)
    ->addClient('bar', 'http://bar.localhost', $clientRegistry);
    
$builder = new TransferManagerBuilder();
$builder->withClientRegistry($clientRegistry)
    ->withClientFactory($clientFactory);
    
$transferManager = $builder->createTransferManager();
```

to add caching system simply change it on the builder
```php
$builder->withMetadataCache('file')
    ->withCacheDir('/tmp/cache');
    
// Or if you want doctrine caches
$builder->withDoctrineCache(new ApcCache());

//Note that the method withCacheDir, also sets the proxy cache folder
```

to create with yml driver:
```php
$builder->withMetadataDriver('yaml')
    ->withMetadataPath('src/Resources/metadata');
```

### 3.2 Using transactional mode
Sometimes, you need things to be rolled back when something goes wrong just like you can using a database. That behavior is possible with RRM too. Using the builder just call the method isTransactional.

```php
$builder->isTransactional();
// you can also disable by calling $builder->isTransactional(false);
```

Now, when something goes wrong, the manager will try to restore all data by doing posts, updates and deletions to the previows values on your webservice

## 3. Mapping a Transfer

```php

use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

/**
 * Remember the name setted on the client registry? it will be resolved to the name used on the
 * client property on the resource annotation. The route of the resource can be configured on the
 * route property of the annotation
 *
 * @Resource(client="some_client", route="/related")
 */
class Stub
{
    /**
     * Maps an property as an id, this is mandatory to update and find by id
     *
     * @Id
     *
     * @var int
     */
    private $id;
    
    /**
     * bind this property to receive the id value of a foreign key
     *
     * @Bindings(source="relation")
     * 
     * @var int
     */
    private $relationId;
    
    /**
     * does the belongs to relationship mapping, a existing field containing the id of the relation must be indicated
     *
     * @BelongsTo(foreignField = "relationId")
     * 
     * @var RelationStub
     */
    private $belongs;
    
    /**
     * does the has one relationship mapping, must indicate a field on the related class that will be matched against
     * the value contained on the id of this class
     *
     * @HasOne(foreignField = "relatedId")
     * 
     * @var RelationStub
     */
    private $hasOne;

    /**
     * does the has many relationship mapping, must indicate a field on the related classes that will be matched against
     * the value contained on the id of this class
     *
     * @HasMany(foreignField = "relatedId")
     * 
     * @var RelationStub
     */
    private $hasMany;
    
    public function getRelationId()
    {
        return $this->relationId;
    }

    public function getBelongs(): RelationStub
    {
        return $this->belongs;
    }
    
    public function setBlongs(RelationStub $relation)
    {
        $this->belongs = $relation;
    }

    public function getHasOne(): RelationStub
    {
        return $this->hasOne;
    }
    
    public function getHasMany(): TransferCollection
    {
        return $this->hasMany;
    }
}

/**
 * @Resource(client="some_client", route="/relation")
 */
class RelationStub
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    private $name;
    
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
}
```

### 3.1. the yml driver

if you want to leave the mapping metadata out of your object to keep it clean or keep it decoupled from this lib
you can also use the yml metadata reader

```php
// the first argument is the path of where the yml files will be located, the second one is the metadata class to be used
$metadataFactory = new Metadata\MetadataFactory(
    new Vox\Metadata\Driver\YmlDriver(
        '/project/metadata',
        Vox\Webservice\Metadata\TransferMetadata::class
    )
);
```

### 3.1.1 - Yml mapping

the yml mapping must be named after the complete class namespace replacing backslashes by dots. 
Ex: /propject/metadata/Project.Package.ClassName.yml

```yml
resource: 
    client: some_client
    route: http://lorem-dolor.cc/some/route

id: id

parameters:
    id:
        bindings:
            source: id
    authorId:
        bindings:
            source: author_id
    date:
        bindings:
            source: post_date
    author:
        belongsTo: 
            foreignField: authorId
    details:
        hasOne:
            foreignField: blogPostId
    comments:
        hasMany:
            foreignField: blogPostId
```

### 3.2. Composite Ids

Composite id's are supported, however there are some limitations.

To map composite ids using annotations is really simple

```php
class Foo
{
    /**
     * @Id()
     *
     * @var int
     */
    private $firstId;
    
    /**
     * @Id()
     *
     * @var int
     */
    private $secondId;
}
```
The yaml mapping is also trivial

```yaml
resource: 
    client: some_client
    route: http://lorem-dolor.cc/some/route

id: [firstId, secondId]
```

### 3.2.1 Mapping relationships with composite ids transfers
For now it is only possible to map belongs to relationships when linking to a composite ids transfer.

```php
class Bar
{
    /**
     * @var int
     */
    private $foreignKeyOne;
    
    /**
     * @var int
     */
    private $foreignKeyTwo;
    
   /**
    * All you need is to use an array of foreign keys instead of a single one
    *
    * @BelongsTo(foreignField={"foreignKeyOne", "foreignKeyTwo"})
    *
    * @var Relationship
    */
    private $relationship;
}
```

```yaml
resource: 
    client: some_client
    route: http://lorem-dolor.cc/some/route

id: id

parameters:
    relationship:
        belongsTo: 
            foreignField: [foreignKeyOne, foreignKeyTwo]
```

There are some limitations however, never use setters for ids or else it will not be able to post new transfers. new transfers needs to pull the ids from the foreign keys automaticaly by the unity of work

## 4. Using the Transfer Manager

```php
// fetches a single transfer from the webservice
$stub = $transferManager->find(Stub::class, 1);
// thanks to the proxy pattern and the mapping the relation can be retrieved lazily and automaticly
$relation = $stub->getRelation();

// changes to a proxyed transfer will be tracked
$relation->setName('lorem ipsum');

$stub2 = $transferManager->find(Stub::class, 2);
$stub2->setRelation(new Relation());

$stub3 = new Stub();
$stub3->setRelation(new Relation());

// any new created transfer must be persisted into the unity of work, so it can be posted by the persister
$transferManager->persist($stub3);

// flushes all changes, all posts, puts, etc. will happen here
$transferManager->flush();
```

## 5. Iri relationships support

if your webservice uses iri relationships, you can map it using the following

```php
/**
 * @Resource(client="foo", route="/foo")
 */
class IriRelated
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    /**
     * @var array
     */
    private $related;
    
    /**
     * @HasMany(iriCollectionField="related")
     * 
     * @var IriRelated[]
     */
    private $relatedTransfers;
}
```

or with Yaml

```yaml
resource: 
    client: foo
    route: /foo
id: id

parameters:
    relatedTransfers:
        hasMany:
            iriCollectionField: related
```

the iriCollectionField is the field that conatiains an array of iri addresses, a collection of objects will be created
as soon as you call the relatedTransfers, but have in mind, it will call the webservice one by one.

The other situations like belongs to, will be taken care automaticaly, just map as commom relationships, the lib detects
if its an iri address using a regexp that looks for the format ".+/.+$"

## 6. Doctrine Interop

This lib uses the doctrine commom interfaces, so you can do code wich is interoperable with doctrine. The annotation mapping however is completely different, hence the use of the yaml mapping is encouraged (also using yml or xml mapping for doctrine projects is also encouraged, in order to create a domain decoupled from the framework)

## 7. The event system

The event system is made to be compatible with doctrine's, so you can have easy time importing plain objects from other system and have its events on your consumer

Create a listener class
```php
class ExampleListener
{
    public function prePersist(LifecycleEventInterface $event) {}
    public function postPersist(LifecycleEventInterface $event) {}
    public function preUpdate(LifecycleEventInterface $event) {}
    public function preRemove(LifecycleEventInterface $event) {}
    public function postUpdate(LifecycleEventInterface $event) {}
    public function preFlush(LifecycleEventInterface $event) {}
    public function postFlush(LifecycleEventInterface $event) {}
}
```

Register the listener
```php
use Vox\Webservice\Event\PersistenceEvents;
use Vox\Webservice\Factory\TransferManagerBuilder;
use Vox\Webservice\EventDispatcher;

//create the event dispatcher
$eventDispatcher = new EventDispatcher();

//register the listsner
$eventDispatcher
    ->addEventListener(
	[
	    PersistenceEvents::PRE_FLUSH, 
	    PersistenceEvents::PRE_PERSIST, 
	    PersistenceEvents::PRE_UPDATE, 
	    PersistenceEvents::PRE_REMOVE, 
	    PersistenceEvents::POST_FLUSH, 
	], 
	new ExampleListener()
    );
    
//register the event dispatcher
$managerBuilder = new TransferManagerBuilder();
$managerBuilder->withEventDispatcher($eventDispatcher);
```

## Limitations

* The unity of work implementation of this lib uses the ids of the entities to determine the state of the entity, so using setters for the id fields may result in the entity being considered as untouched even if its a new entity wich should be posted. To avoid problems, prefer to let the lib manage the id fields automaticaly.
* Theres no many to many relationships handling, therefore if needed the associative table of the many to many association has to be mapped on the api and managed manualy, setting the transfers on it, do not set the ids manualy, manage only the objects around
