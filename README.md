# Wordpress ORM
A simple wordpress ORM forked from [rjjakes/wordpress-orm](https://github.com/rjjakes/wordpress-orm) by Ray Jakes

## Why?
The world is full of chaos, but as representatives of the human species, we strive to bring order to everything.

## Version history

2.0.0 - Refactoring for PHP 7.4+:
- Changed composer dependency to `doctrine/nannotations` (annotation reader).
- Refactored the `Mapping` class, renaming it to `Mapper`.
- Integrated `doctrine/annotations` into the Mapper class.
- Added composer dependency phpunit/phpunit.
- Integrated nested `WHERE` query builder with support for the `OR` operator.
- Moved the `$wpdb` object to the `WpDbAdapter` class, which implements the `AdapterInterface`, to allow for future integration of different database drivers.
- Added some PHPUnit tests.
 
1.0.0 - forked from https://github.com/rjjakes/wordpress-orm

master branch = active development

0.1.0 tag = previous master. The version most people would be using circa last 2022.  

## Installation

```
composer require nicemodules/wordpress-orm
```

## Usage

### Create a model

To use the ORM, first create a class that extends the ORM base model and add a number of properties as protected
variables. The property names must exactly match the desired SQL column names (there is no name mapping).
 
Note that a property of `$ID` is added to the model when you do `extends BaseModel` so you do not need to add that.   

Example:

```php
<?php

namespace NiceModules\ORM\Models\Test;

use NiceModules\ORM\Annotations\Column;
use NiceModules\ORM\Annotations\Index;
use NiceModules\ORM\Annotations\ManyToOne;
use NiceModules\ORM\Annotations\Table;
use NiceModules\ORM\Models\BaseModel;

/**
 * @Table(
 *     type="Entity",
 *     name="foo",
 *     allow_schema_update=true,
 *     allow_drop=true,
 *     prefix="prefix",
 *     indexes={@Index(name="name_index", columns={"name"})},
 *     repository="NiceModules\ORM\Repositories\Test\FooRepository",
 *     inherits="NiceModules\ORM\Models\BaseModel"
 *     )
 */
class Foo extends BaseModel
{
    /**
     * @Column(type="datetime", null ="NOT NULL")
     */
    protected string $date_add = '';

    /**
     * @Column(type="timestamp", null="NOT NULL")
     */
    protected string $date_update = '';

    /**
     * @Column(type="varchar", length="25")
     * @var string
     */
    protected string $name = '';

    /**
     * @Column(
     *     type="bigint",
     *     length="20",
     *     null="NOT NULL",
     *     type="bigint",
     *     length="20",
     *     many_to_one=@ManyToOne(modelName="NiceModules\ORM\Models\Test\Bar", propertyName="ID")
     *     )
     */
    protected ?int $bar_ID;

}

```

The annotations which you can use are defined as a classes of the NiceModules\ORM\Annotations namespace.

### Update the database schema

Once you have a model object, you need to tell Wordpress to create a table to match your model. 

Use the mapper class:

```php
use NiceModules\ORM\Mapper;
```


First, get an instance of the ORM mapper object. This static function below makes sure `new Mapping()` is called once
per request. Any subsequent calls to `::instance()` will return the same object. This means you don't need to
continually create a new instance of Mapper() and parse the annotations.     

```php
$mapper = Mapping::instance(Foo::class);
```

Now update the database schema for this class as follows: 

```php
$mapper->updateSchema();
```

This function uses the internal Wordpress dbDelta system to compare and update database tables. If your table doesn't
exist, it will be created, otherwise it checks the schema matches the model and modifies the database table if needed.
 
You should only run this function either when your plugin is activated or during development when you know you have
made a change to your model schema and want to apply it to the database 

### Persisting objects to the database. 

Use the ORM manager: 

```php
use NiceModules\ORM\Manager;
```

Create a new instance of your model:

```php
$Foo = new Foo();
$Foo->set('title', 'Some title');
$Foo->set('time', '2017-11-03 10:04:02');
$Foo->set('views', 34);
$Foo->set('short_name', 'something_here');
```

Get an instance of the ORM manager class. Like the Mapping class, this static function returns
a reusable instance of the manager class. 

```php
$orm = Manager::instance();
```

Now queue up these changes to apply to the database. Calling this does NOT immediately apply the changes to the
database. The idea here is the same as Doctrine: you can queue up many different changes to happen and once you're
ready to apply them, the ORM will combine these changes into single SQL queries where possible. This helps reduce the 
number of calls made to the database. 

```php
$orm->persist($Foo);
```

Once, you're ready to apply all changes to your database (syncing what you have persisted to the database), call
flush():

```php
$orm->flush();
```

Now check your database and you'll see a new row containing your model data. 

### Querying the database

Use the ORM manager: 

```php
use NiceModules\ORM\Manager;
```

Get an instance of the ORM manager class. 

```php
$orm = Manager::getManager();
```

Get the object repository. Repositories are classes that are specific to certain object types. They contain functions
for querying specific object types. 

By default all object types have a base repository which you can get access to by passing in the object type as follows:

```php
$repository = $orm->getRepository(Foo::class);
```

**With the query builder**

You can create a query though this repository like so:

```php
$query = $repository->createQueryBuilder()
  ->where('ID', 3, '=')
  ->orderBy('ID', 'ASC')
  ->limit(1)
  ->buildQuery();
```

Available where() operators are: 

```php
'<', '<=', '=', '!=', '>', '>=', 'IN', 'NOT IN', 'LIKE', 'NOT LIKE', 'IS NULL', 'NOT NULL'
```

Available orderBy() operators are: 

```php
'ASC', 'DESC'
```

To use the "IN" and "NOT IN" clauses of the ->where() function, pass in an array of values like so:

```php
$query = $repository->createQueryBuilder()
  ->where('id', [1, 12], 'NOT IN')
  ->orderBy('id', 'ASC')
  ->limit(1)
  ->buildQuery();
```

Now you have your query, you can use it to get some objects back out of the database.

```php
$results = $query->getResult();
```

Note that if there was just one result, `$results` will contain an object of the repository type. Otherwise it will
contain an array of objects. 
 
To force `getResult()` to always return an array (even if it's just one results), call it with `TRUE` like this:  

```php
$results = $query->getResult(TRUE);
```

**Built-in repository query functions**

Building a query every time you want to select objects from the database is not best practice. Ideally you would create
some helper functions that abstract the query builder away from your controller. 
 
There are several built-in functions in the base repository. 

Return an object by id:

```php
$results = Manager::getManager()
            ->getRepository(Foo::class)
            ->find($id);
```
 
Return all objects sorted by ascending id. 

```php
$results = Manager::getManager()
            ->getRepository(Foo::class)
            ->findAll();
```
 
Return all objects matching pairs of property name and value: 

```php
$results = Manager::getManager()
            ->getRepository(Foo::class)
            ->findBy([$property_name_1 => $value_1, $property_name_2 => $value_2]);
```
 
To add more repository query functions, you can subclass the `BaseRepository` class and tell your object to use that
instead of `BaseRepository`. That is covered in the section below called: *Create a custom repository*   


### Saving modified objects back to the database

To modify an object, load the object from the database modfiy one or more of it's values, call `flush()` to apply the
changes back to the database.
 
For example:
 
 ```php
$orm = Manager::getManager();
$repository = $orm->getRepository(Foo::class);

$Foo = $repository->find(9);   // Load an object by known ID=9
$Foo->set('title', 'TITLE HAS CHANGED!');

$orm->flush();
```

This works because whenever an object is persisted ot loaded from the database, Wordpres ORM tracks any changes made to
the model data. `flush()` syncronizes the differences made since the load (or last `flush()`). 

### Deleting objects from the database

To remove an object from the database, load an object from the database and pass it to the `remove()` method on the
manager class. Then call `flush()` to syncronize the database. 

For example:

```php
$orm = Manager::getManager();
$repository = $orm->getRepository(Foo::class);

$Foo = $repository->find(9);   // Load an object by known ID=9

$orm->remove($Foo);   // Queue up the object to be removed from the database. 

$orm->flush();
```

### Dropping model tables from the database.

It's good practice for your plugin to clean up any data it has created when the user uninstalls. With that in mind, the
ORM has a method for removing previously created tables. If you have created any custom models, you should use this 
function as part of your uninstall hook.  

Use the mapper class:

```php
use NiceModules\ORM\Mapping;
```


First, get an instance of the ORM mapper object.    

```php
$mapper = Mapping::getMapper();
```

Now pass the model classname to dropTable() like this:

```php
$mapper->dropTable(Foo::class);
```


### Create a custom repository

@todo

### Relationships

@todo

## Exceptions

Wordpress ORM uses Exceptions to handle most failure states. You'll want to wrap calls to ORM functions in 
`try {} catch() {}` blocks to handle these exceptions and send errors or warning messages to the user.
    
For example:
    
```php
try {
    $query = $repository->createQueryBuilder()
      ->where('ID', 3, '==')  // Equals operator should be '=' not '=='
      ->buildQuery();
} catch (\NiceModules\ORM\Exceptions\InvalidOperatorException $e) {
    // ... warn the user about the bad operator or handle it another way. 
}

```
    
The exceptions are as follows.     

```php
AllowSchemaUpdateIsFalseException
FailedToInsertException
InvalidOperatorException
NoQueryException
PropertyDoesNotExistException
RepositoryClassNotDefinedException
RequiredAnnotationMissingException
UnknownColumnTypeException
```


## Pre-defined models

@todo


## Dependencies 

(Dependencies are automatically handled by Composer). 

https://github.com/doctrine/nannotations
https://github.com/sebastianbergmann/phpunit


## Credits

Forked from [rjjakes/wordpress-orm](https://github.com/rjjakes/wordpress-orm) by Ray Jakes
