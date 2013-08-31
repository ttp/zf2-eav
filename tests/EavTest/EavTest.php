<?php
namespace EavTest;

require_once dirname(__FILE__).'/../DatabaseTestCase.php';

use Eav\Eav,
    Eav\ValuesCache,
    EavTest\Bootstrap,
    Zend\Db\TableGateway\TableGateway,
    Zend\Db\RowGateway\RowGateway;

/**
 * Test class for Eav.
 */
class EavTest extends \DatabaseTestCase
{
    /**
     * @var    Eav
     * @access protected
     */
    protected $_eav;

    /**
     * Entity table
     * @var TableGateway
     * @access protected
     */
    protected $_entityTable;

    protected function getDataSet()
    {
        return $this->createXMLDataSet(TESTS_PATH . '/fixtures/eav.xml');
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        parent::setUp();
        $entityTable = new TableGateway('eav_entity', Bootstrap::adapter());
        $this->_entityTable = $entityTable;
        $this->_eav = new Eav($entityTable);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
        parent::tearDown();
        $pdo = $this->getDatabaseTester()->getConnection()->getConnection();
        $pdo->exec("DELETE FROM eav_entity;");
        $pdo->exec("DELETE FROM eav_entity_int;");
        $pdo->exec("DELETE FROM eav_entity_decimal;");
        $pdo->exec("DELETE FROM eav_entity_string;");
        $pdo->exec("DELETE FROM eav_entity_text;");
        $pdo->exec("DELETE FROM eav_attribute;");
    }

    public function testGetTableName()
    {
        $tableName = $this->_eav->getTableName($this->_entityTable);
        $this->assertEquals('eav_entity', $tableName);
    }

    public function testGetEavTableName()
    {
        $this->assertEquals('eav_entity_int', $this->_eav->getTypeTableName('int'));
        $this->assertEquals('eav_entity_string', $this->_eav->getTypeTableName('string'));
    }

    public function testGetEavTable()
    {
        $attribute = $this->_eav->getAttribute('secname');
        $typeTable = $this->_eav->getTypeTable($attribute);
        $this->assertTrue($typeTable instanceof TableGateway);

        $tableName = $this->_eav->getTypeTableName($attribute->type);
        $this->assertEquals($tableName, $typeTable->getTable());
    }

    public function testGetAttributeType()
    {
        $attribute = $this->_eav->getAttribute('secname');
        $this->assertEquals('string', $this->_eav->getAttributeType($attribute));
    }

    public function testGetAttributeId()
    {
        $attribute = $this->_eav->getAttribute('secname');
        $this->assertEquals(1, $this->_eav->getAttributeId($attribute));
    }

    public function testGetAttributeName()
    {
        $attribute = $this->_eav->getAttribute('secname');
        $this->assertEquals('secname', $this->_eav->getAttributeName($attribute));
    }

    public function testGetEntityId()
    {
        $entity = $this->_entityTable->select(array('id' => 1))->current();
        $this->assertEquals(1, $this->_eav->getEntityId($entity));
    }

    public function testGetAttribute()
    {
        $attribute = $this->_eav->getAttribute('secname');
        // TODO $this->assertTrue($attribute instanceof RowGateway);
        $this->assertEquals('secname', $attribute->name);
    }

    public function testGetAttributeValue()
    {
        $row = $this->_entityTable->select(array('id' => 1))->current();
        $secname = $this->_eav->getAttributeValue($row, 'secname');
        $this->assertEquals('one secname', $secname);

        $row = $this->_entityTable->select(array('id' => 2))->current();
        $secname = $this->_eav->getAttributeValue($row, 'secname');
        $this->assertEquals('two secname', $secname);

        $row = $this->_entityTable->select(array('id' => 3))->current();
        $secname = $this->_eav->getAttributeValue($row, 'secname');
        $this->assertEquals('', $secname);
    }

    public function testSetAttributeValue()
    {
        $attribute = $this->_eav->getAttribute('secname');
        $row = $this->_entityTable->select(array('id' => 1))->current();

        $values = array('changed', 'changed2');
        foreach ($values as $value) {
            $this->_eav->setAttributeValue($row, $attribute, $value);
            $storedValue = $this->_eav->getAttributeValue($row, $attribute);
            $this->assertEquals($value, $storedValue);
        }
    }

    public function testLoadAttributes()
    {
        $rows = $this->_entityTable->select();
        $attributes = array();
        $attributes[] = $this->_eav->getAttribute('secname');
        $attributes[] = $this->_eav->getAttribute('age');

        $cache = $this->_eav->loadAttributes($rows, $attributes);
        $this->assertTrue($cache instanceof ValuesCache);
        $this->assertCount(2, $cache->toArray());

        $rows = $rows->toArray();

        // changing value in db for row[0]
        $this->_eav->setAttributeValue($rows[0], 'age', 100);
        $newValue = $this->_eav->getAttributeValue($rows[0], 'age');
        $this->assertEquals(100, $newValue);
        // value from cache should contains old value
        $cachedValue = $this->_eav->getAttributeValue($rows[0], 'age', $cache);
        $this->assertEquals(10, $cachedValue);

        // row[3] does not have age attribute in db
        $this->_eav->setAttributeValue($rows[3], 'age', 100);
        // so it should be loaded from db
        $this->assertEquals(100, $this->_eav->getAttributeValue($rows[3], 'age', $cache));

        // row[2] does not have age attribute in db
        // and we do not want to load value if it's not in cache
        $this->assertEquals('', $this->_eav->getAttributeValue($rows[2], 'age', $cache, false));
    }
}