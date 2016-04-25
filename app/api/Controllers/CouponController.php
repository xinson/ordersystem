<?php
namespace Api\Controllers;

use Common\Library\EventData;
use Api\Events\ProcessCoupon;

class CouponController extends BaseController
{

    public function initialize()
    {
        parent::initialize();
        $this->checkUser($this->input['username'],$this->input['client']);
    }


    public function newbieCreate()
    {
        $params = array(
            'coupon_agent' => 'newbie',
            'method' => 'newbie_create',
            'data' => array('client_id' => $this->userInfo['client_id'], 'user_id' => $this->userInfo['user_id']),
        );

        $processCoupon = new ProcessCoupon();
        $processCoupon->run($event = new EventData($params));
        $tmpArr = $event->getData('result');

        if (isset($tmpArr[0]['error_code'])) {
            $error_code = $tmpArr[1];
            $reArr = $tmpArr[0];
        } else {
            $error_code = 200;
            $reArr['status'] = $tmpArr[0]['status'];
            $reArr['newbie_status'] = $tmpArr[0]['newbie_status'];
            $reArr['activation_wait'] = $tmpArr[0]['activation_wait'];
        }

        $this->ajaxReturn($reArr, $error_code);
    }


    public function newbieActivate()
    {
        $params = array(
            'coupon_agent' => 'newbie',
            'method' => 'newbie_activate',
            'data' => array('client_id' => $this->userInfo['client_id'], 'user_id' => $this->userInfo['user_id']),
        );

        $processCoupon = new ProcessCoupon();
        $processCoupon->run($event = new EventData($params));

        $tmpArr = $event->getData('result');

        if (isset($tmpArr[0]['error_code'])) {
            $error_code = $tmpArr[1];
            $reArr = $tmpArr[0];
        } else {
            $error_code = 200;
            $reArr['status'] = $tmpArr[0]['status'];
        }

        $this->ajaxReturn($reArr, $error_code);
    }


    public function couponList()
    {

        $this->input['page_size'] = !empty($this->input['page_size']) ?: 20;
        $this->input['current_client_id'] = $this->userInfo['client_id'];
        $this->input['user_id'] = $this->userInfo['user_id'];

        $params = array(
            'coupon_agent' => 'newbie',
            'method' => 'coupon_list',
            'data' => $this->input,
        );

        $processCoupon = new ProcessCoupon();
        $processCoupon->run($event = new EventData($params));

        $tmpArr = $event->getData('result');

        if (isset($tmpArr[0]['error_code'])) {
            $error_code = $tmpArr[1];
            $reArr = $tmpArr[0];
        } else {
            $error_code = 200;
            $reArr['coupon_list'] = $tmpArr[0]['coupon_list'];
            $reArr['position'] = $tmpArr[0]['position'];
        }

        $this->ajaxReturn($reArr, $error_code);
    }


}
