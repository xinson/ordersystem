<?php
namespace Commands\Tasks;

use Common\Library\EventData;
use Common\Library\EventHelper;
use Common\Library\QueueHelper;
use Coupon\Models\Coupon;
use Pay\Method\Provider\Yingyongbao\YingyongbaoSdk;
use Pay\Method\Provider\Yingyongbao\YingyongbaoException;
use Pay\Models\Order;
use Commands\BaseTask;
use Exception;

class SupervisorTask extends BaseTask
{
    protected $initializedJobs;
    protected $jobs;

    public function ordercallbackAction()
    {
        /** @var \Pheanstalk_Pheanstalk $beanstalk */
        /** @var Order $order */
        $beanstalk = QueueHelper::getInstance();
        if ($beanstalk && $beanstalk->getConnection()->isServiceListening()) {
            while ($job = $beanstalk->reserveFromTube('order_callback', 600)) {
                $jobData = $job->getData();
                if (!empty($jobData)) {
                    $jobData = json_decode($jobData, true);
                    $order = Order::findFirst(fnGet($jobData, 'order_id'));
                    if (isset($order->id)) {
                        try {
                            $order->callback(true);
                            $order->save();
                            $beanstalk->delete($job);
                        } catch (Exception $e) {
                            $beanstalk->bury($job);
                        }
                    }
                    unset($order);
                }
            }
        }
    }

    public function couponeventAction()
    {
        /** @var \Pheanstalk_Pheanstalk $beanstalk */
        $beanstalk = QueueHelper::getInstance();
        if ($beanstalk && $beanstalk->getConnection()->isServiceListening()) {
            $coupon = new Coupon();
            while ($job = $beanstalk->reserveFromTube('coupon_event', 600)) {
                $jobData = $job->getData();
                $jobData = json_decode($jobData, true);
                $coupon->eventTable($jobData);
                $beanstalk->delete($job);
            }
        }
    }

}
