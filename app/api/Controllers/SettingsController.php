<?php
namespace Api\Controllers;

use Common\Library\ConfigHelper;
use Common\Models\User;
use Common\Library\Session;
use Pay\Method\PayLog;

class SettingsController extends BaseController
{

    public function initialize()
    {
        parent::initialize();
        $this->checkUser($this->input['username'],$this->input['client']);
        $this->checkSign($this->getInput('sign'));
    }

    protected function _checkPassword($password, $password_confirmation)
    {
        if (empty($password) || $password == '') {
            $this->err['error_msg'] = '密码不能为空';
            $this->ajaxReturn($this->err, 400);
        }

        if (strlen($password) < ($minLength = ConfigHelper::get('application.password_min_length'))) {
            $this->err['error_msg'] = '密码不能小于' . $minLength . '位';
            $this->ajaxReturn($this->err, 400);
        }

        if ($password != $password_confirmation) {
            $this->err['error_msg'] = '两次输入密码不相同';
            $this->ajaxReturn($this->err, 400);
        }
    }

    public function index()
    {
        $user = Session::getInstance()->getUser();
        $ret = array(
            'password' => $user->hasPassword()
        );
        $this->ajaxReturn($ret);
    }

    /**
     * 设置密码
     */
    public function password() {
        $password = $this->getInput('password','');
        $password_confirmation = $this->getInput('password_confirmation');

        $this->_checkPassword($password,$password_confirmation);

        $user = Session::getInstance()->getUser();

        if ($user->hasPassword()) {
            $old_password  = $this->getInput('old_password');
            if(!password_verify($old_password,$user->getData('password'))){
                $this->err['error_msg'] = '旧密码不正确';
                $this->ajaxReturn($this->err, 400);
            } elseif(password_verify($password,$user->getData('password'))){
                $this->err['error_msg'] = '新旧密码不能一样';
                $this->ajaxReturn($this->err, 400);
            }
        }
        $ret = $user->changePassword($password);
        if(!$ret){
            $this->err['error_msg'] = '设置密码错误';
            $this->ajaxReturn($this->err,400);
        }
        $this->ajaxReturn(array('status'=>'success'));
    }

    /**
     * 修改密码
     */
    public function changePassword() {
        $this->password();
    }

    /**
     * 忘记密码
     */
    public function forgetPassword(){
        $user = Session::getInstance()->getUser();

        //用户未设置密码,不允许忘记密码
        if ( !$user->hasPassword() ) {
            $this->err['error_msg'] = '用户未设置密码,不能使用忘记密码';
            $this->ajaxReturn($this->err, 400);
        }

        $inputs = array(
            "password" => $this->getInput('password',''),
            "password_confirmation" => $this->getInput('password_confirmation',''),
            'login_password' => $this->getInput('login_password'),
        );

        $this->_checkPassword($inputs['password'],$inputs['password_confirmation']);

        /**
         * 验证用户密码
         */
        /*
        if ( !$user->verifyLoginPassword($inputs['login_password']) ) {
            $this->err['error_msg'] = '登录密码错误!';
            $this->ajaxReturn($this->err, 400);
        }
         */

        if(!$user->changePassword($inputs['password'])){
            $this->err['error_msg'] = '保存密码失败';
            $this->ajaxReturn($this->err, 400);
        }
        $this->ajaxReturn(array('status'=>'success'));
    }



}
