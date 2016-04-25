<?php
namespace Common\Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\Adapter\Pdo\Mysql as PhalconMysql;

class Mysql extends PhalconMysql
{
    public function rollback($nesting = true)
    {
        if ($this->_transactionLevel) {
            parent::rollback($nesting);
        }
        return false;
    }
}
