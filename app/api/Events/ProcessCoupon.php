<?php
namespace Api\Events;

use Coupon\Type\CouponTypeAbstract;
use Common\Library\EventData;
use Common\Library\ConfigHelper;

class ProcessCoupon
{
    public function run(EventData $event)
    {
        $params = $event->getData();
        $couponConfig = ConfigHelper::get('coupon.coupon_agents');
        $agent = fnGet($params, 'coupon_agent');
        $couponAgent = fnGet($couponConfig, $agent);
        $class = fnGet($couponAgent, 'class');
        if ($class && class_exists($class)) {
            /* @var $processor \Coupon\Type\Newbie */
            /* @var $processor \Coupon\Type\Activity */
            /* @var $processor \Coupon\Type\Trigger */
            $processor = new $class;
            if ($processor instanceof CouponTypeAbstract) {
                if (is_array($data = fnGet($params, 'data')) && fnGet($couponAgent,
                        'methods/' . ($method = fnGet($params, 'method')))
                ) {
                    $data['coupon_agent'] = $agent;
                    $data['coupon_method'] = $method;
                    $params['data'] = $data;
                }
                $result = $processor->process($params);
                $event->setData('result', $result);
            }
        }
    }
}


