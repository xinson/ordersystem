<?php

namespace Common\Models;


class SentHistory extends Model
{
    public $pk = 'order_id';

    public function initialize()
    {
        $this->setSource("sent_history");
    }

    public function getByOrderId($orderId)
    {
        return $this->findFirstSimple(array("order_id" =>  $orderId));
    }
}
