<?php

require_once dirname(__FILE__).'/Bootstrap.php';

use EavTest\Bootstrap;

abstract class DatabaseTestCase extends PHPUnit_Extensions_Database_TestCase
{
    protected function getConnection()
    {
        $config = Bootstrap::config();
        $host = $config['db']['host'];
        $user = $config['db']['username'];
        $password = $config['db']['password'];
        $dbname = $config['db']['dbname'];

        $pdo = new PDO("mysql:host={$host};dbname={$dbname}", $user, $password);
        return $this->createDefaultDBConnection($pdo, $dbname);
    }
}