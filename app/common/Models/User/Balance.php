<?php

namespace Common\Models\User;

use Common\Models\User;
use Common\Models\Model;

/**
 * Class Balance
 * @package Common\Model\User
 *
 */
class Balance extends Model
{
    public $pk = 'user_id';
    public $user_id;
    public $amount;

    /**
     * @var User
     */
    protected $_user;

    /**
     * @var BalanceHistory
     */
    protected $_balance;

    public function initialize()
    {
        $this->setSource("user_balance");
    }

    /**
     * @param $userId
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function getByUserId($userId)
    {
        return $this->findFirstSimple(array("user_id" => $userId));
    }

    public function getAmount()
    {
        return $this->getData('amount') * 1;
    }

    public function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findFirstSimple(array("id" => $this->getData('user_id')));
        }
        return $this->_user;
    }

    public function setUser(User $user)
    {
        $this->_user = $user;
        $this->setData('user_id', $user->getId());
        return $this;
    }

    public function getBalanceHistory()
    {
        if (!$this->_balance) {
            $this->_balance = new BalanceHistory();
        }
        return $this->_balance;
    }

    public function updataAmount($balance_delta, $comment, $appgame_order_id, $order_id = null, $client_id = null)
    {
        if ($this->save()) {
            $this->getBalanceHistory()->addBalanceHistory($this->getUser()->getId(), $appgame_order_id,
                $this->amount, $balance_delta, $comment, $order_id, $client_id);
        }
        return $this;
    }

    public function sub($minuend, $comment, $appgame_order_id, $order_id = null, $client_id = null)
    {
        $newAmount = $this->amount - $minuend;
        $this->setData('amount', $newAmount);
        $this->updataAmount('-' . $minuend, $comment, $appgame_order_id, $order_id, $client_id);
    }

    public function plus($augend, $comment, $appgame_order_id, $order_id = null, $client_id = null)
    {
        $newAmount = $this->amount + $augend;
        $this->setData('amount', $newAmount);
        $this->updataAmount('+' . $augend, $comment, $appgame_order_id, $order_id, $client_id);
    }

}
