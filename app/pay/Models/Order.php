<?php
namespace Pay\Models;

use Api\Events\BalancePayment;
use Api\Events\CouponPayment;
use Common\Library\Session;
use Common\Models\Model;
use Common\Library\ConfigHelper;
use Common\Library\EventHelper;
use Common\Library\QueueHelper;
use Common\Library\EventData;
use Common\Models\Client;
use Common\Models\User;
use Common\Models\User\BalanceHistory;
use Coupon\Models\Coupon;
use Pay\Exception\OrderException;
use Common\Library\HttpClient;


/**
 * Class Order
 * @package Pay\Model
 *
 * @property string $appgame_order_id Order increment ID
 * @property integer $id Order increment ID
 * @property string $trade_id Order increment ID
 * @property string $txn_id Order increment ID
 *
 */
class Order extends Model
{
    const STATUS_CANCELED = 'canceled';
    const STATUS_COMPLETE = 'complete';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';

    const CALLBACK_STATUS_DONE = 'done';
    const CALLBACK_STATUS_FAILED = 'failed';
    const CALLBACK_STATUS_TRIED = 'tried';

    protected $keyFields = array(
        'product_name',
        'user_id',
        'amount',
    );
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var OrderData
     */
    protected $orderData;

    protected $protectedFieldsOnPreparation = array(
        'id',
        'appgame_order_id',
        'created_at',
        'confirmed_at',
        'completed_at',
        'failed_at',
        'status',
    );

    protected $cachedVars = array();

    public $appgameOrderId;
    public $status;
    public $id;
    public $user_id;

    public function initialize()
    {
        $this->setSource("orders");
        $this->allowEmptyStringValues(array('callback_status'));
    }


    public function save($data = null, $whiteList = null)
    {
        parent::save($data, $whiteList);
        if ($id = $this->getData('id')) {
            $orderData = $this->getOrderData();
            $orderData->setData('order_id', $id);
            $orderData->save();
        }
    }


    public function calculateCallbackNextRetry($triedCount)
    {
        $config = ConfigHelper::get('payment.payment');
        $retryInterval = fnGet($config, 'callback_retry_interval');
        $time = fnGet($retryInterval, (int)$triedCount);
        $time and $time += time();
        return $time;
    }

    public function callback($run = false)
    {
        $save = !$run;
        $run or $run = !ConfigHelper::get('cron.CRON_ON');
        if ($run && ($url = $this->getData('callback_url')) && $this->canCallback()) {
            $client = $this->getClient();
            $secret = $client->getData('app_secret');
            $extra_data = $this->getOrderData('extra_data') ? json_decode($this->getOrderData('extra_data'),
                true) : array();
            $data = array(
                'trade_id' => $this->getData('trade_id'),
                'appgame_order_id' => $this->getData('appgame_order_id'),
                'provider' => empty($this->getOrderData('provider')) ? 'Appgame' : $this->getOrderData('provider'),
                'amount' => $this->getData('amount') * 1,
                'status' => $this->getStatus(),
                'time' => time(),
            );
            ($privateInfo = fnGet($extra_data, 'private_info')) and $data['private_info'] = $privateInfo;
            ksort($data);
            $sign = md5(md5(urldecode(http_build_query($data))) . $secret);
            $data['sign'] = $sign;
            $http = new HttpClient();
            $response = trim($http->request($url, $data));
            if ($response && $response{0} == '{') {
                $response = json_decode($response, true);
                (int)fnGet($response, 'ret') == 0 and $response = 'ok';
            }
            if ($response == 'ok') {
                $this->setData('callback_status', static::CALLBACK_STATUS_DONE);
            } else {
                $callbackStatus = $this->getData('callback_status');
                // Get tried count
                $triedCount = (int)substr($callbackStatus, -2);
                $triedCount < 0 and $triedCount = 0;
                $triedCount += 1;
                // Convert 1, 2 to 01, 02
                $triedCount = substr('0' . $triedCount, -2);
                $callbackConfig = ConfigHelper::get('payment.payment');
                $maxRetry = fnGet($callbackConfig,'callback_max_retry_count');
                // Set callback status to failed or tried_01, tried_02 ...
                $newCallbackStatus = ($triedCount >= $maxRetry ? static::CALLBACK_STATUS_FAILED : static::CALLBACK_STATUS_TRIED . '_' . $triedCount);
                $nextRetry = $triedCount >= $maxRetry ? '' : $this->calculateCallbackNextRetry($triedCount);
                $this->setData('callback_next_retry', $nextRetry);
                $this->setData('callback_status', $newCallbackStatus);
            }
            $save and $this->save();
            if ($this->getId()) {
                EventHelper::listen('after_sent_cpdata', $event = array(
                    'orderId' => $this->getId(),
                    'requestData' => $data,
                    'responseData' => $response
                ));
            }
        } else {
            if (($url = $this->getData('callback_url')) && $this->canCallback()) {
                $data = array('order_id' => $this->getId());
                QueueHelper::Putintube('order_callback', $data);
            }
        }
        return $this;
    }

    public function canCallback()
    {
        $callbackStatus = $this->getData('callback_status');
        return !in_array($callbackStatus, array(
            static::CALLBACK_STATUS_DONE,
            static::CALLBACK_STATUS_FAILED,
        ));
    }

    public function canCancel()
    {
        $status = $this->getStatus();
        return in_array($status, array(
            self::STATUS_PENDING,
        ));
    }

    public function cancel($comment = null)
    {
        if (!$this->canCancel()) {
            throw new OrderException(__('无法取消订单'), OrderException::ERROR_CODE_ORDER_CANNOT_BE_CANCELED);
        }
        $this->setData('canceled_at', time());
        CouponPayment::order_cancel(new EventData(array('order' => $this)));
        $this->callback();
        $this->updateStatus(static::STATUS_CANCELED, $comment ?: __('订单被取消'));
    }

    public function canFinishTransaction()
    {
        $status = $this->getStatus();
        return in_array($status, array(
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ));
    }

    public function complete($comment = null)
    {
        $status = $this->getStatus();
        if ($status == static::STATUS_COMPLETE) {
            throw new OrderException(__('订单已经完成'), OrderException::ERROR_CODE_ORDER_COMPLETED);
        }
        $this->setData('completed_at', time());
        $this->setData('cash_paid', $this->getData('cash_to_pay'));
        $this->setData('cash_to_pay', 0);
        $this->updateStatus(static::STATUS_COMPLETE, $comment ?: __('订单完成'));
    }

    public function confirm($comment = null)
    {
        $status = $this->getStatus();
        if ($status != static::STATUS_PENDING) {
            throw new OrderException(__('无法确认订单'), OrderException::ERROR_CODE_ORDER_PROCESSING);
        }
        $this->setData('confirmed_at', time());
        $this->updateStatus(static::STATUS_PROCESSING, $comment ?: __('用户确认订单'));
    }

    public function fail($comment = null)
    {
        $status = $this->getStatus();
        if ($status == static::STATUS_FAILED) {
            throw new OrderException(__('订单已经失败'), OrderException::ERROR_CODE_ORDER_FAILED);
        }
        $this->setData('failed_at', time());
        CouponPayment::order_fail(new EventData(array('order' => $this)));
        $this->updateStatus(static::STATUS_FAILED, $comment ?: __('订单失败'));
    }

    public function getAmount()
    {
        return $this->getData('amount');
    }

    public function generateAppgameOrderId($prefix = '')
    {
        $appgameOrderId = $prefix . (date('Y') - 2004) . date('md') . substr(time(), -5) . substr(microtime(), 2,
                5) . sprintf('%02d', mt_rand(1000, 9999));
        $hasOrderId = $this->findFirst(array(
            'appgame_order_id = :appgame_order_id:',
            'bind' => array(
                'appgame_order_id' => $appgameOrderId,
            ),
        ));
        if ($hasOrderId) {
            return $this->generateAppgameOrderId($prefix);
        } else {
            $this->setData('appgame_order_id', $appgameOrderId);
        }
        return $this;
    }

    /**
     * @param string $tradeId
     * @param integer $clientId
     *
     * @return array
     */
    public function getByTradeId($tradeId, $clientId)
    {
        return $this->findFirst(array(
            'trade_id = :tradeId: AND client_id = :clientId:',
            'bind' => compact('tradeId', 'clientId'),
        ));
    }

    public function getClient()
    {
        if ($this->client === null) {
            $this->client = Client::findFirst(array(
                'id = :id:',
                'bind' => array(
                    'id' => $this->getData('client_id'),
                ),
            ));
        };
        return $this->client;
    }

    /**
     * @return false|Coupon
     */
    public function getCoupon()
    {
        if (!isset($this->cachedVars[$key = 'coupon'])) {
            $coupon = false;
            if ($code = $this->getCouponCode()) {
                $coupon = Coupon::findFirst(array(
                    'coupon_code = :code:',
                    'bind' => array(
                        'code' => $code,
                    ),
                ));
                !isset($coupon->coupon_id) and $coupon = false;
            }
            $this->cachedVars[$key] = $coupon;
        }
        return $this->cachedVars[$key];
    }

    /**
     * @return float
     */
    public function getCouponAmount()
    {
        $amount = $this->getOrderData('coupon_amount');
        if ($this->getCouponCode() && !$amount) {
            $amount = $this->getCoupon() ? $this->getCoupon()->getData('amount') : 0;
        }
        return $amount * 1;
    }

    public function getCouponCode()
    {
        return (string)$this->getOrderData('coupon_code');
    }

    /**
     * @return false/Model\User\BalanceHistory
     */
    public function getBalanceHistory()
    {
        if (!isset($this->cachedVars[$key = 'balance_history'])) {
            $balance_history = false;
            if ($appgameOrderId = $this->getData('appgame_order_id')) {
                $balance_history = BalanceHistory::findFirst(array(
                    'appgame_order_id = :appgame_order_id:',
                    'bind' => array(
                        'appgame_order_id' => $appgameOrderId,
                    ),
                ));
                !isset($balance_history->id) and $balance_history = false;
            }
            $this->cachedVars[$key] = $balance_history;
        }
        return $this->cachedVars[$key];
    }

    /**
     * @return false/User
     */
    public function getUser()
    {
        if (!isset($this->cachedVars[$key = 'user'])) {
            $session = Session::getInstance();
            if ($session->getUser()) {
                $user = $session->getUser();
            } else {
                $user = User::findFirst(array(
                    'id = :id:',
                    'bind' => array(
                        'id' => $this->getData('user_id'),
                    ),
                ));
                !isset($user->id) and $user = false;
            }
            $this->cachedVars[$key] = $user;
        }
        return $this->cachedVars[$key];
    }


    /**
     * @return array
     */
    public function getKeyFields()
    {
        $fields = array_values($this->keyFields);
        return array_combine($fields, $fields);
    }

    public function getOrderData($key = null)
    {
        if ($this->orderData === null) {
            $this->orderData = new OrderData;
            $hasOrderData = $this->orderData->getByOrderId($this->id);
            if (isset($hasOrderData->order_id)) {
                $this->orderData = $hasOrderData;
            }
        };
        $data = $this->orderData->getData('data');
        return $key ? fnGet($data, $key) : $this->orderData;
    }


    public function setOrderData($key, $value = null)
    {
        $orderData = $this->getOrderData();
        $data = $orderData->getData('data') ?: array();
        if (is_array($key)) {
            $data = $key;
        } else {
            if (is_scalar($key)) {
                if ($value === null) {
                    unset($data[$key]);
                } else {
                    $data[$key] = $value;
                }
            }
        }

        $orderData->setData('data', $data);
        return $this;
    }


    public function getPaymentMethodTitle()
    {
        if (!isset($this->cachedVars[$key = 'payment_method_title'])) {
            $methods = ConfigHelper::get('payment.payment_agents');
            $value = fnGet($methods,
                $this->getData('payment_agent') . '/methods/' . $this->getData('payment_method') . '/description');
            $this->cachedVars[$key] = $value;
        }
        return $this->cachedVars[$key];
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getStatusHistories()
    {
        return $this->getOrderData()->getData('status_history');
    }

    /**
     * @param $data
     *
     * @return static
     */
    public static function prepareOrder($data)
    {
        $order = new static;
        // 尝试加载已有订单
        /** @var Order $hasOrder */
        $hasOrder = $order->getByTradeId(fnGet($data, 'trade_id'), fnGet($data, 'client_id'));
        if (isset($hasOrder->id)) {
            $order = $hasOrder;
            if ($hasOrder->status != static::STATUS_PENDING) {
                $message = '订单正在处理中，请勿重复提交';
                $hasOrder->status == static::STATUS_COMPLETE and $message = '订单已完成，请勿重复提交';
                $hasOrder->status == static::STATUS_FAILED and $message = '订单已失败，请勿重复提交';
                throw new OrderException(__($message), OrderException::ERROR_CODE_ORDER_PROCESSING);
            }
        }
        // 过滤受保护字段
        foreach ($order->getProtectedFieldsOnPreparation() as $field) {
            unset($data[$field]);
        }
        // 删除 object 值
        $objectKeys = array();
        foreach ($data as $k => $v) {
            is_object($v) and $objectKeys[] = $k;
        }
        foreach ($objectKeys as $key) {
            unset($data[$key]);
        }
        // 创建 appgame_order_id
        $order->getData('appgame_order_id') or $order->generateAppgameOrderId(fnGet($data, 'order_prefix'));
        unset($data['order_prefix']);
        $order->getData('created_at') or $order->setData('created_at', time());
        $keyFields = $order->getKeyFields();
        // 设置订单数据，并检测有效性
        $amount = $data['amount'] = fnGet($data, 'amount') * 1;
        if ($amount <= 0) {
            throw new OrderException(__('订单金额无效'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
        $cashToPay = fnGet($data, 'cash_to_pay');
        $cashToPay === null and $cashToPay = $amount;
        if ($cashToPay < 0) {
            throw new OrderException(__('订单金额无效'), OrderException::ERROR_CODE_BAD_PARAMETERS);
        }
        $data['cash_to_pay'] = $cashToPay;
        foreach ($order->getData() as $key => $vel) {
            $oldValue = $vel;
            $newValue = fnGet($data, $key);
            if (isset($keyFields[$key]) && $oldValue && $oldValue != $newValue) {
                throw new OrderException(__('订单关键数据[:' . $key . ']发生改变'),
                    OrderException::ERROR_CODE_KEY_PARAMETERS_CHANGED);
            }
            $newValue === null or $order->setData($key, $newValue);
        }
        $order->setOrderData($data);
        //使用抵用卷
        CouponPayment::order_preparation(new EventData(array('order' => $order)));
        //附加使用余额支付
        if (fnGet($data, 'use_balance') && fnGet($data, 'payment_agent') != 'balance_pay') {
            BalancePayment::use_balance(new EventData(array('order' => $order)));
        }
        //直接使用余额支付
        if (fnGet($data, 'payment_agent') == 'balance_pay') {
            BalancePayment::balance_pay(new EventData(array('order' => $order)));
        }
        $order->updateStatus(static::STATUS_PENDING, __('初始化订单'));
        return $order;
    }

    /**
     * @return array
     */
    public function getProtectedFieldsOnPreparation()
    {
        return $this->protectedFieldsOnPreparation;
    }


    /**
     * @param array $protectedFieldsOnPreparation
     * @return static
     */
    public function setProtectedFieldsOnPreparation($protectedFieldsOnPreparation)
    {
        $this->protectedFieldsOnPreparation = $protectedFieldsOnPreparation;
        return $this;
    }


    /**
     * @param array $keyFields
     * @return static
     */
    public function setKeyFields($keyFields)
    {
        $this->keyFields = $keyFields;
        return $this;
    }

    public function updateStatus($status, $comment)
    {
        $this->setData('status', $status);
        $orderData = $this->getOrderData();
        $statusHistory = $orderData->getData('status_history') ?: array();
        $time = explode(' ', microtime());
        $statusHistory[$time[1] . substr($time[0], 1)] = array(
            'status' => $status,
            'comment' => $comment,
        );
        $orderData->setData('status_history', $statusHistory);
        return $this;
    }

    /**
     * @param $appgameOrderId
     * @return \Phalcon\Mvc\Model
     */
    public function getByAppgameOrderId($appgameOrderId)
    {
        return $this->findFirstSimple(array("appgame_order_id" => $appgameOrderId));
    }

}
