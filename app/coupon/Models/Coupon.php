<?php
namespace Coupon\Models;

use Common\Library\ConfigHelper;
use Common\Library\HttpClient;
use Pay\Models\Order;
use Common\Models\Model;
use Common\Models\User;
use Common\Models\Client;
use Coupon\Exception\CouponOrderException;

/**
 * Class Coupon
 * @package Coupon\Model
 * RelationModel
 */
class Coupon extends Model
{
    public $coupon_id;
    public $pk = 'coupon_id';

    const STATUS_NEW = 'new';
    const STATUS_ACTIVATED = 'activated';
    const STATUS_USED = 'used';
    const STATUS_EXPIRED = 'expired';

    protected $_link = array(
        'coupon_client' => array(
            'coupon_id' => 'coupon_id'
        )
    );

    public function initialize()
    {
        $this->setSource("coupon");
    }

    /**
     * 生成抵用券唯一标识码
     * @access public
     * @param string $prefix - 前缀
     * @return static
     */
    public function generateCouponCode($prefix = 'CP')
    {
        $couponCode = $prefix . randString(16, 2, '0123456789');
        if (Coupon::findFirstSimple(array("coupon_code" => $couponCode))) {
            return $this->generateCouponCode($prefix);
        } else {
            $this->setData('coupon_code', $couponCode);
        }
        return $this;
    }

    public function getClientId()
    {
        $client = new CouponClient();
        /** @var CouponClient $query */
        $query = $client->findFirstSimple(array("coupon_id" => $this->getData('coupon_id')))->toArray();
        return $this->array_fetch($query, 'client_id');
    }

    public function getStatus()
    {
        return $this->getData('status');
    }

    public function getUserId()
    {
        return $this->getData('user_id');
    }

    public function isValid($userId, $clientId, $orderId)
    {
        if ($this->getStatus() != static::STATUS_ACTIVATED) {
            if ($orderId != $this->getData('order_id')) {
                return false;
            }
        }
        if ($this->getUserId() && $this->getUserId() != $userId) {
            return false;
        }
        if ($this->getClientId() && !in_array($clientId, $this->getClientId())) {
            return false;
        }
        return true;
    }

    /**
     * 使用抵用券
     * @param Order $order
     *
     * @return static
     */
    public function redeem($order)
    {
        if ($this->isValid($order->getData('user_id'), $order->getData('client_id'), $order->getId())) {
            $amount = $order->getData('cash_to_pay');
            $couponAmount = $this->getData('amount');
            $cashToPay = $amount - $couponAmount;
            $cashToPay < 0 and $cashToPay = 0;
            $order->setData('cash_to_pay', $cashToPay)
                ->setOrderData('coupon_amount', $couponAmount);
            $this->setData('status', static::STATUS_USED);
        } else {
            throw new CouponOrderException(__('抵用券无效'), CouponOrderException::ERROR_CODE_INVALID_COUPON);
        }
        return $this;
    }

    /**
     * 订单失败时恢复抵用券
     */
    public function refund()
    {
        if ($this->getData('status') == static::STATUS_USED) {
            $this->setData('status', static::STATUS_ACTIVATED)
                ->setData('order_id', null);
        }
        return $this;
    }

    public function array_fetch($array, $key)
    {
        foreach (explode('.', $key) as $segment) {
            $results = [];
            foreach ($array as $value) {
                if (array_key_exists($segment, $value = (array)$value)) {
                    $results[] = $value[$segment];
                }
            }
            $array = array_values($results);
        }
        return array_values($results);
    }
}
