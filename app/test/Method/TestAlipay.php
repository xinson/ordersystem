<?php
namespace Test\Method;

use Common\Library\InputHelper;
use Pay\Method\Alipay\AlipaySubmit;
use Pay\Method\Alipay\AlipayNotify;
use Pay\Method\Alipay;
use Exception;

class TestAlipay extends Alipay
{


    protected function _processNotifyAction()
    {
        try {
            $notifyData = InputHelper::get();
            //验证是否是支付宝发来的消息
            $alipayNotify = new TestAlipayNotify($this->config);
            $verifyAlipay = $alipayNotify->verifyNotify($notifyData);
            if (!$verifyAlipay) {
                $result = array(array('error_msg' => __('服务器配置设置错误,签名错误'), 'error_code' => 500), 500);
            } else {
                $order = $this->_initOrderByAppgameOrderId(fnGet($notifyData, 'out_trade_no'));
                $status = $order->getData('status');
                $result = array(
                    array(
                        'trade_id' => $order->getData('trade_id'),
                        'appgame_order_id' => $order->getData('appgame_order_id'),
                        'amount' => $order->getData('amount'),
                        'status' => $status
                    )
                );
                $AliPayStatus = fnGet($notifyData, 'trade_status');
                if ($AliPayStatus === 'WAIT_BUYER_PAY') {
                    $result = array(array('error_msg' => __('支付宝等待支付'), 'error_code' => 500), 500);
                } else {
                    if ($order->canFinishTransaction()) {
                        if ($AliPayStatus) {
                            ($txnId = fnGet($notifyData, 'trade_no')) and $order->setData('txn_id', $txnId);
                            if (($AliPayStatus === 'TRADE_FINISHED' || $AliPayStatus === 'TRADE_SUCCESS')) {
                                $order->complete(__('支付宝状态更改[异步通知]'));
                            } else {
                                $failureMessage = ' STATUS:' . $AliPayStatus;
                                $order->fail(__('支付宝状态更改[异步通知]:message', array('message' => $failureMessage)));
                            }
                            $order->setOrderData('status_callback_result', $notifyData);
                            if (!empty($failureMessage)) {
                                $order->setOrderData('failure_message', $failureMessage);
                            }
                            $order->save();
                            $order->callback();
                            $result[0]['status'] = $order->getData('status');
                            /*$eventData = new EventData(array(
                                'model' => 'AlipayBeanstalkd',
                                'method' => 'AlipayCallBack',
                                'order' => $order,
                                'queryResult' => $notifyData
                            ));
                            EventHelper::listen('process_beanstalk', $eventData);*/
                        }
                    }
                }
            }
        } catch (Exception $e) {
            //LogHelper::write($e->getCode() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = array(array('error_msg' => __('服务器内部错误'), 'error_code' => 500), 500);
        }
        return $result;
    }
}

class TestAlipayNotify extends AlipayNotify
{
    public function verifyNotify($notifyData)
    {
        return true;
    }

}

class TestAlipaySubmit extends AlipaySubmit
{

}
