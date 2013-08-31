Zend Frawemork 2 EAV
====================
Zend Framework Entity-Attribute-Value library.

[What is EAV?](http://en.wikipedia.org/wiki/Entity-attribute-value_model)

## Quick start

### Prepare tables
For example if you have *products* table and you want to save EAV attributes for this table. Then create several tables where EAV attributes will be stored.
Example:
  * products_int,
  * products_decimal
  * products_string
  * products_text

Each table should contain next fields:
  * id(INT) – primary key(auto increment)
  * entity_id(INT) – identifier of your record in the products table
  * attribute_id(INT) – identifier of attribute
  * value – in this field the value of attribute will be stored. Type of this field should be different for each table(int, decimal, varchar, text)

Also you should have table of attributes with next fields inside:
  * id, primary key
  * type, type of attribute
  * name, name of attribute

The names of these fields do not necessarily have to be called as above. Here we have the names of fields by default. If you don't have such table then create it.

Then create several records in the ```attributes``` table

```
|  *attribute_id* | *attribute_type*  | *attribute_name* |
| --------------- | ----------------- |------------------|
| 1               | int               | quantity         |
| 2               | string            | title            |
| 3               | text              | description      |
```


### Extends Eav
You can specify the names of required fields:

```php
use Eav\Eav;

class EavProduct extends Eav
{
    protected $_entitiesTableFieldId = 'id'; // name of primary key of products table

    protected $_attributesTableName = 'attributes'; // name of 'attributes' table
    protected $_attributesTableFieldId   = 'attribute_id'; // name of primary key of attributes table
    protected $_attributesTableFieldType = 'attribute_type'; // field where attribute type is stored
    protected $_attributesTableFieldName = 'attribute_name'; // field where attribute name is stored
}

```

### Save attributes
```php
use Zend\Db\TableGateway\TableGateway;

$productsTable = new TableGateway('products', $adapter);
$eav = new EavProduct($productsTable);

$products = $productsTable->select(array('id' => array(1,2,3)));

foreach ($products as $product) {
    $eav->setAttributeValue($product, 'quantity', 10);
    $eav->setAttributeValue($product, 'title', 'product title');
    $eav->setAttributeValue($product, 'description', 'my description');
}

```

### Get attributes
```php
$productsTable = new TableGateway('products');
$eav = new EavProduct($productsTable);

$product = $productsTable->select(array('id' => 1))->current();
echo $eav->getAttributeValue($product, 'quantity');
echo $eav->getAttributeValue($product, 'title');
echo $eav->getAttributeValue($product, 'description');

// or using attribute id
echo $eav->getAttributeValue($product, '1');

// or using attribute object(Row)
$attributesTable = $eav->getAttributesTable();
$attribute = $attributeTable->select(array('id' => 2))->current();
echo $eav->getAttributeValue($product, $attribute);

// or using eav object
$attribute = $eav->getAttribute('title');
echo $eav->getAttributeValue($product, $attribute);
```

## Full example
Controller:
```php

$productsTable = new TableGateway('products', $adapter);
$eav = new EavProduct($productsTable);
$attributesTable = $eav->getAttributesTable();

$products = $productsTable->select(array('id' => array(1,2,3)));
$attributes = $attributesTable->select();

```

View:
```php
<?php foreach ($this->products as $product): ?>

    <b><?php echo $product->title; ?></b><br />
    <?php foreach ($this->attributes as $attribute): ?>

        <?php echo $attribute->label; ?>:
        <?php echo $this->eav->getAttributeValue($product, $attribute); ?><br />

    <?php endforeach; ?>

<?php endforeach; ?>
```

## Speed up
With the approach above, every time you want to get the value of attribute you will have query to the database. You can get the attribute values using only one query to the database. Below are some examples:

```php

$productsTable = new TableGateway('products', $adapter);
$eav = new EavProduct($productsTable);
$attributesTable = $eav->getAttributesTable();

$products = $productsTable->select(array('id' => array(1,2,3)));

// Loading all attributes
$attributes = $attributesTable->select();
$cache = $eav->loadAttributes($products, $attributes);

foreach ($products as $product) {
    // no more queries
    echo $eav->getAttributeValue($product, 'title', $cache);
    echo $eav->getAttributeValue($product, 'description', $cache);
    echo $eav->getAttributeValue($product, 'quantity', $cache);
}

// Loading some attributes
$where = array("name" => array('title', 'description'))
$attributes = $attributesTable->select($where);

$cache = $eav->loadAttributes($products, $attributes);

foreach ($products as $product) {
    // no more queries
    echo $eav->getAttributeValue($product, 'title', $cache);
    echo $eav->getAttributeValue($product, 'description', $cache);

    // here we have query to database since this attribute was not cached
    echo $eav->getAttributeValue($product, 'quantity', $cache);
}

$cachedValues = $cache->toArray(); // returns array('entity_id' => array('attribute_id' => 'value'))

```

