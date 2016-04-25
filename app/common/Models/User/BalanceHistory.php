<?php

namespace Common\Models\User;

use Common\Models\Model;

/**
 * Class BalanceHistory
 * @package Common\Model\User
 */
class BalanceHistory extends Model
{

    public function initialize()
    {
        $this->setSource("user_balance_history");
    }

    public function addBalanceHistory(
        $user_id,
        $appgame_order_id,
        $balance_amount,
        $balance_delta,
        $additional_info,
        $order_id = null,
        $client_id = null
    ) {
        return !$this->save(array(
            'user_id' => $user_id,
            'balance_amount' => $balance_amount,
            'appgame_order_id' => $appgame_order_id,
            'order_id' => $order_id,
            'client_id' => $client_id,
            'balance_delta' => $balance_delta,
            'additional_info' => $additional_info,
            'update_time' => time()
        )) ?: true;
    }
}
