<?php

namespace Common\Library;

use Phalcon\Mvc\Model;

/**
 * Class Session
 * @package Common
 */
class Session
{
    /**
     * @var static
     */
    protected static $_instance;
    /**
     * @var \Common\Models\Client
     */
    protected $_client;
    /**
     * @var \Common\Models\User
     */
    protected $_user;

    public static function getInstance()
    {
        static::$_instance === null and static::$_instance = new static;
        return static::$_instance;
    }

    public function getClient()
    {
        return $this->_client;
    }

    public function getUser()
    {
        return $this->_user;
    }

    public function setClient(Model $client)
    {
        $this->_client = $client;
        return $this;
    }

    public function setUser(Model $user)
    {
        $this->_user = $user;
        return $this;
    }
}
