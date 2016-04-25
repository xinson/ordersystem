<?php
namespace Pay\Models;

use Common\Models\Model;

/**
 * Class OrderData
 * @package Pay\Model
 *
 */
class OrderData extends Model
{
    public $pk = 'order_id';

    public function initialize()
    {
        $this->setSource("order_data");
    }

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if ($k != 'order_id') {
                    $v = json_encode($v);
                }
                $this->$k = $v;
            }
        } else {
            if ($key != 'order_id') {
                $value = json_encode($value);
            }
            $this->$key = $value;
        }
        return $this;
    }

    public function getData($key = null)
    {
        if ($key === null) {
            return $this->toArray();
        }
        if (property_exists($this, $key)) {
            if ($key !== 'order_id') {
                if (@$value = json_decode($this->$key, true)) {
                    return $value;
                }
            }
            return $this->$key;
        }
        return null;
    }

    /**
     * @param $id
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function getByOrderId($id)
    {
        return $this->findFirstSimple(array("order_id" => $id));

    }
}
