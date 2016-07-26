<?php

namespace kak\clickhouse;


use yii\db\TableSchema;
use yii\helpers\ArrayHelper;

class Schema extends \yii\db\Schema
{
    /** @var $db Connection */
    public $db;

    public $typeMap  = [
        'UInt8' => self::TYPE_SMALLINT,
        'UInt16'=> self::TYPE_INTEGER,
        'UInt32'=> self::TYPE_INTEGER,
        'UInt64'=> self::TYPE_INTEGER,
        'Int8'=> self::TYPE_SMALLINT,
        'Int16'=> self::TYPE_INTEGER,
        'Int32'=> self::TYPE_INTEGER,
        'Int64'=> self::TYPE_INTEGER,
        'Float32'=> self::TYPE_FLOAT,
        'Float64' => self::TYPE_FLOAT,
        'String' => self::TYPE_STRING,
        'FixedString' => self::TYPE_STRING,
        'Date' => self::TYPE_DATE,
        'DateTime'  => self::TYPE_DATETIME,
        'Enum'  => self::TYPE_STRING,
        'Enum8' => self::TYPE_STRING,
        'Enum16'=> self::TYPE_STRING,
        //'Array' => null,
        //'Tuple' => null,
        //'Nested' => null,
    ];


    private $_builder;


    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return null|TableSchema DBMS-dependent table metadata, null if the table does not exist.
     */
    protected function loadTableSchema($name)
    {

        $sql = 'SELECT * FROM system.columns WHERE table=:name FORMAT JSON';
        $result = ArrayHelper::getValue($this->db->createCommand($sql,[':name' => $name ])->queryAll(),'data');

        if($result && isset($result[0])) {
            $table = new TableSchema();
            $table->schemaName = $result[0]['database'];
            $table->name       = $name;
            $table->fullName   = $table->schemaName . '.' . $table->name;

            foreach($result as $info) {
                $column = $this->loadColumnSchema($info);
                $table->columns[$column->name] = $column;
            }
            return $table;
        }
        return null;
    }

    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return ColumnSchema the column schema object
     */
    protected function loadColumnSchema($info)
    {
        $column = $this->createColumnSchema();
        $column->name = $info['name'];
        $column->dbType = $info['type'];
        $column->type = isset($this->typeMap[$column->dbType ]) ? $this->typeMap[$column->dbType]  : self::TYPE_STRING;

        if (preg_match('/^([\w ]+)(?:\(([^\)]+)\))?$/', $column->dbType, $matches)) {
            $type = strtolower($matches[1]);
            $column->dbType = $type . (isset($matches[2]) ? "({$matches[2]})" : '');
            if (isset($this->typeMap[$type])) {
                $column->type = $this->typeMap[$type];
            }
        }
        $column->phpType = $this->getColumnPhpType($column);
        if (empty($info['default_type'])) {
                $column->defaultValue = $info['default_expression'];
        }
        return $column;
    }

    /**
     * @return QueryBuilder the query builder for this connection.
     */
    public function getQueryBuilder()
    {
        if ($this->_builder === null) {
            $this->_builder = $this->createQueryBuilder();
        }
        return $this->_builder;
    }


    public function createQueryBuilder()
    {
        return new QueryBuilder($this->db);
    }

    /**
     * Quotes a simple table name for use in a query.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is already quoted, this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteSimpleTableName($name)
    {
        return strpos($name, "`") !== false ? $name : "`" . $name . "`";
    }

    /**
     * Quotes a simple column name for use in a query.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is already quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName($name)
    {
        return strpos($name, '`') !== false || $name === '*' ? $name : '`' . $name . '`';
    }

}