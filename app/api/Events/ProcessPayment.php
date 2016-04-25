<?php
namespace Api\Events;

use Pay\Method\PaymentMethodAbstract;
use Common\Library\EventData;
use Common\Library\ConfigHelper;
class ProcessPayment
{
    protected static $_processed;

    public static function run(EventData $event)
    {
        if (!defined('SERVICE_MODE') && static::$_processed && !$event->getData('force')) {
            return;
        }
        static::$_processed = true;
        $params = $event->getData();
        $paymentAgentConfig = ConfigHelper::get('payment.payment_agents');
        $paymentAgent = fnget($paymentAgentConfig,($agent = fnGet($params, 'payment_agent')));
        $class = fnGet($paymentAgent, 'class');
        if ($class && class_exists($class)) {
            /* @var $processor \Pay\Method\Alipay */
            $processor = new $class;
            if ($processor instanceof PaymentMethodAbstract) {
                $isPayRequest = false;
                if (is_array($data = fnGet($params, 'data')) && fnGet($paymentAgent,
                        'methods/' . ($method = fnGet($params, 'method')))
                ) {
                    $data['payment_agent'] = $agent;
                    $data['payment_method'] = $method;
                    $params['data'] = $data;
                    $isPayRequest = true;
                }
                $event->setData($params);
                $event->setData('is_pay_request', $isPayRequest);
                //Hook::listen('before_process_payment', $event);
                $result = $processor->process($event->getData());
                $event->setData('result', $result);
                CouponPayment::after_process_payment($event);
            }
        }
    }
}
