<?php
/**
 * 订单模块.
 * User: dgw
 */

namespace Api\Controllers;

use Common\Library\LogHelper;
use Common\Models\Client;
use Pay\Models\Order;
use Pay\Models\Helper;
use Pay\Exception\OrderException;
use Pay\Method\PaymentMethodAbstract;
use Common\Library\Session;

class OrderController extends BaseController
{
    public function initialize()
    {
        parent::initialize();
        $this->checkUser($this->input['username'],$this->input['client']);
    }

    public function cancel()
    {
        $this->checkSign($this->getInput('sign'));
        if (!$tradeId = $this->getInput('trade_id')) {
            $this->err['error_msg'] = '订单号无效';
            $this->err['error_details'] = 'trade_id 为空';
            $this->ajaxReturn($this->err, 400);
        }
        try {
            $orderHelper = Helper::getInstance();
            $session = Session::getInstance();
            $order = $orderHelper->initOrder($tradeId, $session->getClient()->getId(), $session->getUser()->getId());
            if ($order->getStatus() != $order::STATUS_CANCELED) {
                $this->db->begin();
                $order->cancel();
                $order->save();
                $this->db->commit();
            }
            $result = array(
                array(
                    'trade_id' => $order->getData('trade_id'),
                    'appgame_order_id' => $order->getData('appgame_order_id'),
                    'amount' => $order->getData('amount') * 1,
                    'status' => $order->getData('status'),
                ),
                $order
            );
            $responseStatus = 200;
        } catch (OrderException $e) {
            $result = PaymentMethodAbstract::prepareErrorResponse($e);
            $responseStatus = $result[1];
            $this->db->rollback();
        } catch (\Exception $e) {
            LogHelper::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = PaymentMethodAbstract::prepareErrorResponse($e, __('服务器内部错误'), 500, 500);
            $responseStatus = $result[1];
            $this->db->rollback();
        }
        $this->ajaxReturn($result[0], $responseStatus);
    }

    public function payedClientList()
    {
        $list = (new Order())->sqlFetchAll("SELECT distinct c.id,c.client AS client_id, c.name AS client_name FROM orders AS o LEFT JOIN client AS c ON c.id=o.client_id WHERE o.user_id= :user_id: ",array('user_id' => $this->userInfo['user_id']));
        $this->ajaxReturn(array('clients' => $list));
    }

    public function orderList()
    {
        $clientId = $this->getInput('client_id', '');
        $position = $this->getInput('position', 0, 'intval');
        $pageSize = $this->getInput('page_size', 20, 'intval');
        $condition = $this->getInput('condition', '');

        if (!$clientId) {
            $this->err['error_msg'] = '参数[client_id]不能为空';
            $this->ajaxReturn($this->err, 400);
        }
        $collection = (new Order())->query();
        $collection->where('user_id = '.$this->userInfo['user_id']);
        $conditionArray = empty($condition) ?: json_decode($condition, true);
        if (is_array($conditionArray) && !empty($conditionArray)) {
            $collection->andWhere(" status IN ('".implode("','",$conditionArray)."') ");
        }

        $client = false;
        switch ($clientId) {
            case 'all':
                break;
            case 'current':
                $collection->andWhere('client_id = '.$this->userInfo['client_id']);
                $client = Session::getInstance()->getClient();
                break;
            default:
                $client = Client::findFirstSimple(array("client" => $clientId));
                isset($client->id) and $collection->andWhere('client_id = '.$client->id);
        }
        $position > 0 and $collection->andWhere('id < '.$position);

        $collection->orderBy('id DESC')->limit($pageSize);
        $orders = $collection->execute()->toArray();

        $position = 0;
        $list = array();
        if ($orders) {
            foreach ($orders as $data) {
                $order = new Order;
                $order->setData($data);
                $orderClient = $client ?: $order->getClient();
                $row = array(
                    'trade_id' => $order->getData('trade_id'),
                    'appgame_order_id' => $order->getData('appgame_order_id'),
                    'client_name' => $orderClient->getData('name'),
                    'product_name' => $order->getData('product_name'),
                    'amount' => $order->getData('amount') * 1,
                    'payment_method' => $order->getPaymentMethodTitle(),
                    'status' => $order->getData('status'),
                    'created_at' => $order->getData('created_at'),
                    'completed_at' => $order->getData('completed_at'),
                    'failed_at' => $order->getData('failed_at'),
                    'coupon_code' => $order->getCouponCode(),
                    'coupon_amount' => $order->getCouponAmount(),
                    'balance_amount' => $order->getOrderData('balance_amount') * 1,
                    'cash_paid' => $order->getData('cash_paid') * 1,
                );
                $list[] = $row;
                $position = $order->getId();
                unset($order);
            }
        }
        if ($position) {
            $hasMoreOrders = (new Order())->query();
            $hasMoreOrders->columns('id');
            $hasMoreOrders->where('user_id = '.$this->userInfo['user_id']);
            if (is_array($conditionArray) && !empty($conditionArray)) {
                $hasMoreOrders->andWhere(" status IN ('".implode("','",$conditionArray)."') ");
            }
            if($clientId!='all'){
                if($clientId=='current') {
                    $hasMoreOrders->andWhere('client_id = ' . $this->userInfo['client_id']);
                }else{
                    isset($client->id) and $hasMoreOrders->andWhere('client_id = '.$client->id);
                }
            }
            $hasMoreOrders->andWhere("id < {$position}");
            $hasMoreOrders->limit(1);
            if (!$hasMoreOrders->execute()->toArray()) {
                $position = -1;
            }
        }

        $result = array('position' => $position, 'order_list' => $list);
        $this->ajaxReturn($result);
    }

}
