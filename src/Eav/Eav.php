<?php
namespace Eav;

use Zend\Db\Adapter\Adapter,
    Zend\Db\TableGateway\TableGateway,
    Zend\Db\RowGateway\RowGateway,
    Zend\Db\TableGateway\Feature,
    Eav\ValuesCache;

/**
 * Eav class
 *
 * @author Taras Taday
 * @version 0.2.0
 */
class Eav
{
    /*
     * Name of primary table
     */
    protected $_entityTableName = 'eav_entity';
    protected $_entityFieldId = 'id';
    protected $_rowCacheFieldName = 'eav_attributes';

    /*
     * Name of attribute table
     */
    protected $_attributeTableName = 'eav_attribute';
    protected $_attributeFieldId   = 'id';
    protected $_attributeFieldType = 'type';
    protected $_attributeFieldName = 'name';

    /**
     * Attributes model
     * @var TableGateway
     */
    protected $_attributeModel;
    protected $_attributes;

    /*
     * Eav model objects
     */
    protected $_eavModels = array();

    public function  __construct(TableGateway $table)
    {
        $this->_entityModel = $table;
        $this->_entityTableName = $this->getTableName($table);
        $this->_attributeModel = new TableGateway(
            $this->_attributeTableName, $table->getAdapter(), new Feature\RowGatewayFeature('id'));
    }

    public function getTableName($table)
    {
        return $table->getTable();
    }

    public function getEavTableName($type)
    {
        return $this->_entityTableName . '_' . strtolower($type);
    }

    public function getEavModel($attribute)
    {
        $type = $this->getAttributeType($attribute);
        if (!isset($this->_eavModels[$type])) {
            $eavTable = new TableGateway(
                $this->getEavTableName($type), $this->_entityModel->getAdapter(), new Feature\RowGatewayFeature('id'));
            $this->_eavModels[$type] = $eavTable;
        }

        return $this->_eavModels[$type];
    }

    public function getEavModels($attributes)
    {
        $models = array();
        foreach ($attributes as $attribute) {
            $type = $this->getAttributeType($attribute);
            if (!isset($models[$type])) {
                $models[$type] = $this->getEavModel($attribute);
            }
        }
        return $models;
    }

    public function getAttributeType($attribute)
    {
        return $attribute->{$this->_attributeFieldType};
    }

    public function getAttributeId($attribute)
    {
        return $attribute->{$this->_attributeFieldId};
    }

    public function getAttributeName($attribute)
    {
        return $attribute->{$this->_attributeFieldName};
    }

    public function getEntityId($row)
    {
        if (is_array($row)) {
            return $row[$this->_entityFieldId];
        } else {
            return $row->{$this->_entityFieldId};
        }
    }

    public function getAttribute($id)
    {
        if (isset($this->_attributes[$id])) {
            return $this->_attributes[$id];
        } elseif (is_numeric($id)) {
            $where = array($this->_attributeFieldId => $id);
            $attribute = $this->_attributeModel->select($where)->current();
        } else {
            $where = array($this->_attributeFieldName => $id);
            $attribute = $this->_attributeModel->select($where)->current();
        }
        $this->cacheAttribute($attribute);
        return $attribute;
    }

    public function cacheAttribute($attribute)
    {
        $this->_attributes[$this->getAttributeId($attribute)] = $attribute;
        $this->_attributes[$this->getAttributeName($attribute)] = $attribute;
    }

    public function cacheAttributes($attributes)
    {
        foreach ($attributes as $attribute) {
            $this->cacheAttribute($attribute);
        }
    }

    public function getValueRow($entityRow, $attributeRow)
    {
        $eavModel = $this->getEavModel($attributeRow);
        $attributeId = $this->getAttributeId($attributeRow);
        $where = array(
            'entity_id' => $this->getEntityId($entityRow),
            'attribute_id' => $attributeId
        );
        return $eavModel->select($where)->current();
    }

    /**
     * Return attribute value
     *
     * @param RowGateway $row entity object
     * @param RowGateway $attribute attribute object
     * @param ValuesCache $valuesCache try to use values cache
     * @param boolean $loadIfNotInCache do query if value is not in cache
     * @return mixed
     */
    public function getAttributeValue($row, $attribute, $valuesCache = null, $loadIfNotInCache = true)
    {
        if (is_string($attribute)) {
            $attribute = $this->getAttribute($attribute);
        }
        $attributeId = $this->getAttributeId($attribute);
        $entityId = $this->getEntityId($row);

        if ($valuesCache) {
            if ($valuesCache->hasAttributeValue($entityId, $attributeId)) {
                return $valuesCache->getAttributeValue($entityId, $attributeId);
            } elseif (!$loadIfNotInCache) {
                return '';
            }
        }

        $valueRow = $this->getValueRow($row, $attribute);
        $value = $valueRow ? $valueRow->value : '';

        if ($valuesCache) {
            $valuesCache->setAttributeValue($entityId, $attributeId, $value);
        }

        return $value;
    }

    /**
     * Set attribute value
     *
     * @param RowGateway $entityRow entity object
     * @param RowGateway $attribute attribute object
     * @param mixed $value attribute value
     */
    public function setAttributeValue($entityRow, $attribute, $value)
    {
        if (is_string($attribute)) {
            $attribute = $this->getAttribute($attribute);
        }
        $eavModel = $this->getEavModel($attribute);
        $valueRow = $this->getValueRow($entityRow, $attribute);
        if (!$valueRow) {
            $valueRow = new RowGateway('id', $eavModel->getTable(), $eavModel->getAdapter());
            $valueRow->attribute_id = $this->getAttributeId($attribute);
            $valueRow->entity_id = $this->getEntityId($entityRow);
        }

        $valueRow->value = $value;
        $valueRow->save();
    }

    /**
     * Load attributes values with single query
     *
     * @param ResultSet $rows
     * @param ResultSet $attributes
     * @return array
     */
    public function loadAttributes($rows, $attributes)
    {
        if (!$rows->valid()) {
            return;
        }
        $this->cacheAttributes($attributes);

        $entityIds = array();
        foreach ($rows as $row) {
            array_push($entityIds, $this->getEntityId($row));
        }

        $eavModels = $this->getEavModels($attributes);

        $queries = array();
        foreach ($eavModels as $type => $eavModel) {
            $select = $eavModel->getSql()->select();
            $select->where(array('entity_id' => $entityIds));

            $attributeIds = array();
            foreach ($attributes as $attribute) {
                if ($type == $this->getAttributeType($attribute)) {
                    $attributeIds[] = $this->getAttributeId($attribute);
                }
            }
            $select->where(array('attribute_id' => $attributeIds));
            $queries[] = $select->getSqlString($eavModel->getAdapter()->getPlatform());
        }

        /* build query */
        $query = '(' . implode(') UNION ALL (', $queries) . ')';

        $db = $this->_entityModel->getAdapter();
        $valuesRows = $db->query($query, Adapter::QUERY_MODE_EXECUTE);
        $result = new ValuesCache();
        foreach ($valuesRows as $row) {
            $result->setAttributeValue($row['entity_id'], $row['attribute_id'], $row['value']);
        }
        return $result;
    }
}