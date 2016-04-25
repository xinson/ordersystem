<?php

namespace Common\Models;

use Common\Library\LogHelper;
use Common\Models\User\Balance;
use Common\Library\ConfigHelper;
use Common\Library\HttpClient;

/**
 * Class User
 * @package Common\Model
 *
 * @method array|null findFirstByUsername(string $username) Load user by username
 */
class User extends Model
{
    public $client;

    public $id;

    public $passport_id;

    public $username;

    public $password;

    /**
     * @var User\Balance
     */
    protected $balance;

    public function getBalance()
    {
        if ($this->balance === null) {
            $this->balance = Balance::findFirstSimple(array("user_id" => $this->getId()));
            if (!isset($this->balance->user_id)) {
                $this->balance = new Balance();
                $this->balance->setUser($this)->save();
            }
        }
        return $this->balance;
    }

    public function hasPassword()
    {
        return $this->password !== null;
    }

    public function changePassword($password, $needhash = true)
    {
        $this->password = $needhash ? hashPassword($password) : $password;
        return $this->save();
    }

}
