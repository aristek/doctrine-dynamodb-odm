# Aristek Doctrine DynamoDB ODM Bundle

## To use package you need:
- Configure `aristek_dynamodb`
    ```yaml
    aristek_dynamodb:
        item_namespace: 'App\Item'
        item_dir: '%kernel.project_dir%/src/Item'
        dynamodb_config:
            endpoint: '%env(AWS_ENDPOINT)%'
            region: '%env(AWS_REGION)%'
            version: latest
            credentials:
                key: "%env(AWS_KEY)%"
                secret: '%env(AWS_SECRET)%'
        table: 'users'
    ```
    - Where:
        - `item_namespace` - Namespace item classes (required)
        - `item_dir` - Path item classes (required)
        - `dynamodb_config` - DynamoDB Config (required)
        - `table` - Item default table (optional)

## Mapping
The fundamental functionality of an ODM library is to map object models (i.e. classes) to database structure. DynamoDb ODM provides a handy way to establish this mapping with the help of attributes:

```php
<?php

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceOne;

#[Document]
final class User
{
    #[Id]
    private int $id;

    #[Field]
    private string $name;

    #[Field]
    private int $age;
    
     #[ReferenceOne(targetDocument: Address::class, cascade: 'all')]
    private int $address;
}
```

```php
<?php

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceMany;
use Doctrine\Common\Collections\Collection;

#[Document]
final class Address
{
    #[Id]
    private int $id;

    #[Field]
    private string $street;

    #[Field]
    private int $apartment;
    
    /**
    * @var Collection<User>
    */
    #[ReferenceMany(targetDocument: User::class, cascade: "all")]
    private Collection $users;
}
```

## Usage
```php
<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserAddAction
{
    public function __construct(
        private readonly DocumentManager $dm
    ) {    
    }
    
    #[Route(path: "/user/add", methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): Response {
        $user = (new User())
            ->setName($request->get('name'))
            ->setAge($request->get('age'))
            ->setAddress((new Address())
                ->setStreet($request->get('street'))
                ->setAppartment($request->get('apartment'))
            );
        
        $this->dm->persist($user);
        $this->dm->flush();
        
        return new Response();    
    }
}
```

## Usage Query Builder

* [find() and delete()](#find-and-delete)
* [Conditions](#conditions)
* [all() and first()](#all-and-first)
* [Pagination](#pagination)
* [update](#update) / [updateAsync()](#updateasync)
* [save](#save) / [saveAsync()](#saveasync)
* [delete](#delete) / [deleteAsync()](#deleteasync)
* [chunk](#chunk)
* [limit() and take()](#limit-and-take)
* [firstOrFail()](#firstorfail)
* [findOrFail()](#findorfail)
* [refresh()](#refresh)
* [REMOVE — Deleting Attributes From An Item](#remove--deleting-attributes-from-an-item)
* [toSql() Style](#tosql-style)
* [Decorate Query](#decorate-query)

#### find() and delete()

```php
$model->find($id, array $columns = []);
$model->findMany($ids, array $columns = []);
$model->delete();
$model->deleteAsync()->wait();
```

#### Conditions

```php
// Using getIterator()
// If 'key' is the primary key or a global/local index and it is a supported Query condition,
// will use 'Query', otherwise 'Scan'.
$model->where('key', 'key value')->get();

$model->where(['key' => 'key value']);

// Chainable for 'AND'.
$model->where('foo', 'bar')
    ->where('foo2', '!=', 'bar2')
    ->get();
    
// Chainable for 'OR'.
$model->where('foo', 'bar')
    ->orWhere('foo2', '!=', 'bar2')
    ->get();
 
// Other types of conditions
$model->where('count', '>', 0)->get();
$model->where('count', '>=', 0)->get();
$model->where('count', '<', 0)->get();
$model->where('count', '<=', 0)->get();
$model->whereIn('count', [0, 100])->get();
$model->whereNotIn('count', [0, 100])->get();
$model->where('count', 'between', [0, 100])->get();
$model->where('description', 'begins_with', 'foo')->get();
$model->where('description', 'contains', 'foo')->get();
$model->where('description', 'not_contains', 'foo')->get();

// Nested conditions
$model->where('name', 'foo')
    ->where(function ($query) {
        $query->where('count', 10)->orWhere('count', 20);
    })
    ->get();

// Nested attributes
$model->where('nestedMap.foo', 'bar')->where('list[0]', 'baz')->get();
```

##### whereNull() and whereNotNull()

> NULL and NOT_NULL only check for the attribute presence not its value being null  
> See: http://docs.aws.amazon.com/amazondynamodb/latest/APIReference/API_Condition.html

```php
$model->whereNull('name');
$model->whereNotNull('name');
```

#### all() and first()

```php
// Using scan operator, not too reliable since DynamoDb will only give 1MB total of data.
$model->all();

// Basically a scan but with limit of 1 item.
$model->first();
```

#### Pagination

Unfortunately, offset of how many records to skip does not make sense for DynamoDb.
Instead, provide the last result of the previous query as the starting point for the next query.

**Examples:**

For query such as:

```php
$query = $model->where('count', 10)->limit(2);
$items = $query->all();
$last = $items->last();
```

Take the last item of this query result as the next "offset":

```php
$nextPage = $query->after($last)->limit(2)->all();
// or
$nextPage = $query->afterKey($items->lastKey())->limit(2)->all();
// or (for query without index condition only)
$nextPage = $query->afterKey($last->getKeys())->limit(2)->all();
```

#### update()

```php
// update
$model->update($attributes);
```

#### updateAsync()

```php
// update asynchronously and wait on the promise for completion.
$model->updateAsync($attributes)->wait();
```

#### save()

```php
$model = new Model();
// Define fillable attributes in your Model class.
$model->fillableAttr1 = 'foo';
$model->fillableAttr2 = 'foo';
// DynamoDb doesn't support incremented Id, so you need to use UUID for the primary key.
$model->id = 'de305d54-75b4-431b-adb2-eb6b9e546014';
$model->save();
```

#### saveAsync()

Saving single model asynchronously and waiting on the promise for completion.

```php
$model = new Model();
// Define fillable attributes in your Model class.
$model->fillableAttr1 = 'foo';
$model->fillableAttr2 = 'bar';
// DynamoDb doesn't support incremented Id, so you need to use UUID for the primary key.
$model->id = 'de305d54-75b4-431b-adb2-eb6b9e546014';
$model->saveAsync()->wait();
```  

Saving multiple models asynchronously and waiting on all of them simultaneously.

```php
for($i = 0; $i < 10; $i++){
    $model = new Model();
    // Define fillable attributes in your Model class.
    $model->fillableAttr1 = 'foo';
    $model->fillableAttr2 = 'bar';
    // DynamoDb doesn't support incremented Id, so you need to use UUID for the primary key.
    $model->id = uniqid();
    // Returns a promise which you can wait on later.
    $promises[] = $model->saveAsync();
}

\GuzzleHttp\Promise\all($promises)->wait();
```  

#### delete()

```php
$model->delete();
```

#### deleteAsync()

```php
$model->deleteAsync()->wait();
```

#### chunk()

```php
$model->chunk(10, function ($records) {
    foreach ($records as $record) {

    }
});
```

#### limit() and take()

```php
// Use this with caution unless your limit is small.
// DynamoDB has a limit of 1MB so if your limit is very big, the results will not be expected.
$model->where('name', 'foo')->take(3)->get();
```

#### firstOrFail()

```php
$model->where('name', 'foo')->firstOrFail();
// for composite key
$model->where('id', 'foo')->where('id2', 'bar')->firstOrFail();
```

#### findOrFail()

```php
$model->findOrFail('foo');
// for composite key
$model->findOrFail(['id' => 'foo', 'id2' => 'bar']);
```

#### refresh()

```php
$model = Model::first();
$model->refresh();
```

#### Query Scope

```php
class Foo extends DynamoDbModel
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('count', function (DynamoDbQueryBuilder $builder) {
            $builder->where('count', '>', 6);
        });
    }

    public function scopeCountUnderFour($builder)
    {
        return $builder->where('count', '<', 4);
    }

    public function scopeCountUnder($builder, $count)
    {
        return $builder->where('count', '<', $count);
    }
}

$foo = new Foo();
// Global scope will be applied
$foo->all();
// Local scope
$foo->withoutGlobalScopes()->countUnderFour()->get();
// Dynamic local scope
$foo->withoutGlobalScopes()->countUnder(6)->get();
```

#### REMOVE — Deleting Attributes From An Item

> See: http://docs.aws.amazon.com/amazondynamodb/latest/developerguide/Expressions.UpdateExpressions.html#Expressions.UpdateExpressions.REMOVE

```php
$model = new Model();
$model->where('id', 'foo')->removeAttribute('name', 'description', 'nested.foo', 'nestedArray[0]');

// Or
Model::find('foo')->removeAttribute('name', 'description', 'nested.foo', 'nestedArray[0]');
```


#### toSql() Style

For debugging purposes, you can choose to convert to the actual DynamoDb query

```php
$raw = $model->where('count', '>', 10)->toDynamoDbQuery();
// $op is either "Scan" or "Query"
$op = $raw->op;
// The query body being sent to AWS
$query = $raw->query;
```

where `$raw` is an instance of [RawDynamoDbQuery](.src/QueryBuilder/RawDynamoDbQuery.php)


#### Decorate Query

Use `decorate` when you want to enhance the query. For example:

To set the order of the sort key:

```php
$items = $model
    ->where('hash', 'hash-value')
    ->where('range', '>', 10)
    ->decorate(function (RawDynamoDbQuery $raw) {
        // desc order
        $raw->query['ScanIndexForward'] = false;
    })
    ->get();
```

To force to use "Query" instead of "Scan" if the library fails to detect the correct operation:

```php
$items = $model
    ->where('hash', 'hash-value')
    ->decorate(function (RawDynamoDbQuery $raw) {
        $raw->op = 'Query';
    })
    ->get();
```
