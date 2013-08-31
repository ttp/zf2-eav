<?php
namespace EavTest;
define('TESTS_PATH', dirname(__FILE__));

use Zend\Db\Adapter\Adapter;

class Bootstrap
{
    protected static $_config;
    protected static $_loader;
    protected static $_adapter;

    public static function init() {
        static::$_loader = require TESTS_PATH . '/../vendor/autoload.php';
        static::$_config = require TESTS_PATH . '/config.php';
        static::$_adapter = new Adapter(array(
            'driver' => 'Mysqli',
            'host'      => static::$_config['db']['host'],
            'database' => static::$_config['db']['dbname'],
            'username' => static::$_config['db']['username'],
            'password'  => static::$_config['db']['password'],
            'driver_options'=> array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'
            ),
            'options' => array('buffer_results' => true)
        ));
    }

    public static function config() {
        return static::$_config;
    }

    public static function loader() {
        return static::$_loader;
    }

    public static function adapter() {
        return static::$_adapter;
    }
}


Bootstrap::init();