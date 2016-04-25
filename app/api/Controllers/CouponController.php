<?php
namespace Api\Controllers;

use Common\Library\EventData;
use Api\Events\ProcessCoupon;
use Common\Models\Client;
use Common\Models\User;

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


    public function activityCreate()
    {
        if (!fnGet($this->input, 'client')) {
            $this->err['error_msg'] = '客户端名称不能为空';
            $this->ajaxReturn($this->err, 400);
        }
        if (!fnGet($this->input, 'create_at')) {
            $this->err['error_msg'] = '抵用卷可用开始日期不能为空';
            $this->ajaxReturn($this->err, 400);
        }
        if (!fnGet($this->input, 'expire')) {
            $this->err['error_msg'] = '抵用卷过期日期不能为空';
            $this->ajaxReturn($this->err, 400);
        }
        if (!fnGet($this->input, 'users')) {
            $this->err['error_msg'] = '抵用卷用户不能为空';
            $this->ajaxReturn($this->err, 400);
        }
        $clients = json_decode($this->input['client'], true);
        $create_at = $this->input['create_at'];
        $expire = $this->input['expire'];
        $users = User::findFirstSimple(array("username"  => $this->input['users']));
        $clientIds = $client = Client::findFirstSimple(array("client" => $clients));
        if ($users && $clientIds) {
            $this->ajaxReturn(array('error_code' => '500', 'error_msg' => '内部服务器错误'), 500);
        }
        $params = array(
            'coupon_agent' => 'activity',    //Type层下的Activity类
            'method' => 'activity_create',
            'data' => array(
                'users' => $users,
                'create_at' => $create_at,
                'expire' => $expire,
                'clients' => $clientIds
            ),
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


}
