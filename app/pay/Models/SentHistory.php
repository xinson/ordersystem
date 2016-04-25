<?php
namespace Pay\Models;

use Common\Models\Model;

class SentHistory extends Model
{
    protected $pk = 'order_id';

    public function initialize()
    {
        $this->setSource("sent_history");
    }

    public function getByOrderId($orderId)
    {
        return $this->findFirstSimple(array('order_id' =>  $orderId));
    }
}
