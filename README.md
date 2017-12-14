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
$metadataFactory = new MetadataFactory(
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
As you can see, its actualy a good idea to use a dependency injection container to build this.

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
     * does the relationship mapping, a existing field conyaining the id of the relation must be indicated
     *
     * @BelongsTo(foreignField = "relationId")
     * 
     * @var RelationStub
     */
    private $relation;
    
    public function getRelationId()
    {
        return $this->relationId;
    }

    public function getRelation(): RelationStub
    {
        return $this->relation;
    }
    
    public function setRelation(RelationStub $relation)
    {
        $this->relation = $relation;
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