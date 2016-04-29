<?php
namespace Api\Controllers;

use Log, User, Client;
use Common\Exception\AjaxReturnException;
use Common\Library\ConfigHelper;
use Common\Library\EventHelper;
use Common\Library\HttpClient;
use Common\Library\LogHelper;
use Common\Library\Rsa;
use Common\Library\InputHelper;
use Common\Library\Session;
use Phalcon\Mvc\Controller;

class BaseController extends Controller
{
    protected $userInfo = array();
    protected $err = array('error_code' => '600020');
    protected $input = array();
    protected $sign = '';

    public $controllerName;
    public $actionName;

    public function initialize()
    {
        //保存所有输入参数
        $this->input = InputHelper::get();
        unset($this->input['_url']);
        $this->controllerName = $this->dispatcher->getControllerName();
        $this->actionName = $this->dispatcher->getActionName();
    }

    public function getInput($key, $default = null, $filter = '')
    {
        if (empty($key)) {
            return $this->input;
        }
        $result = fnGet($this->input, $key, $default);
        return is_null($result) ? $result : (!empty($filter) && function_exists($filter) ? $filter($result) : $result);
    }

    /**
     * 验证用户
     * @access protected
     * @param $username - 用户名
     * @param $client - 客户端
     */
    protected function checkUser($username, $client)
    {
        if ($username == '') {
            $this->ajaxReturn(array('error_code' => '600020', 'error_msg' => '参数[username]不能为空'), 400);
        }

        // 尝试从缓存获取 userInfo
        if ($this->userInfo = S($cacheKey = 'userInfo.' . $username)) {
            Session::getInstance()->setUser(User::findFirst(fnGet($this->userInfo, 'user_id')));
            Session::getInstance()->setClient(Client::findFirst(fnGet($this->userInfo, 'client_id')));
            return;
        }
        //检测用户是否已经保存
        /** @var User $user */
        $user = User::findFirstSimple(array("username"  => $username));
        if (isset($user->id)) {
            $this->userInfo['user_id'] = $user->id;
            Session::getInstance()->setUser(User::findFirst($user->id));
        }else{
            $this->ajaxReturn(array('error_code' => '600020', 'error_msg' => '用户无效', 'error' => 'unauthorized'), 400);
        }

        //检测客户端是否已经保存
        $client = Client::findFirstSimple(array("client" => $client));
        if (!isset($client->id)) {
            $this->userInfo['client_id'] = $client->id;
            Session::getInstance()->setClient(Client::findFirst($client->id));
        }else{
            $this->ajaxReturn(array('error_code' => '600020', 'error_msg' => '客户端无效', 'error' => 'unauthorized'), 400);
        }
        $this->userInfo['username'] = $username;

        S($cacheKey, $this->userInfo, 3600);
        return;
    }


    /**
     * 验证签名
     * @access protected
     * @param $sign - app签名字符串
     */
    protected function checkSign($sign = '')
    {
        $data = $this->input;
        unset($data['_url']);
        unset($data['sign']);
        unset($data['extra_data']);
        ksort($data);
        /** @var Client $client */
        $client = (new Client())->findFirst(fnGet($this->userInfo, 'client_id'));
        $signStr = md5(md5(urldecode(http_build_query($data))) . $client->getData('app_secret'));
        if ($signStr == $sign) {
            return;
        }else{
            $this->ajaxReturn(array(
                'error_code' => '600020',
                'error_msg' => '参数[签名]错误！' . (ConfigHelper::get('application.debug') ? '测试模式提示：正确签名为 ' . $signStr : '')
            ), 400);
            if(ConfigHelper::get('application.debug')) {
                LogHelper::write('CheckSign: ' .var_export($data) . ' Secret: ' . $client->getData('app_secret') . ' Sign: ' . $signStr,
                    LogHelper::INFO);
            }
        }
    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param integer $code http状态码
     * @param String $type AJAX返回数据格式
     * @return void
     */
    protected function ajaxReturn($data = array(), $code = 200, $type = '')
    {
        isset($data['error_code']) && !isset($data['error']) and $data['error'] = $data['error_code'];
        EventHelper::listen('before_ajax_return', $data);
        if (empty($type)) {
            $type = 'JSON';
        }
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                $this->_response($code, 'Content-Type:application/json; charset=utf-8', json_encode($data));
                break;
            case 'XML'  :
                // 返回xml格式数据
                $this->_response($code, 'Content-Type:text/xml; charset=utf-8', xml_encode($data));
                break;
            case 'JSONP':
                $handler = isset($_GET[ConfigHelper::get('application.VAR_JSONP_HANDLER')]) ? $_GET[ConfigHelper::get('application.VAR_JSONP_HANDLER')] : ConfigHelper::get('application.DEFAULT_JSONP_HANDLER');
                // 返回JSON数据格式到客户端 包含状态信息
                $this->_response($code, 'Content-Type:application/json; charset=utf-8', $handler . '(' . json_encode($data) . ');');
                break;
            case 'EVAL' :
                // 返回可执行的js脚本
                $this->_response($code, 'Content-Type:text/html; charset=utf-8', $data);
                break;
        }
    }

    public function _response($code, $header, $content)
    {
        if (defined('SERVICE_MODE')) {
            throw new AjaxReturnException($content, $code, $header);
        } else {
            send_http_status($code);
            header($header);
            exit($content);
        }
    }

    /**
     * @param array $skipParams 跳过的参数,不过滤
     */
    protected function checkPaymentParameters($skipParams = array())
    {
        //检测参数是否输入有误
        if (!fnGet($this->input, 'developerurl') && !in_array('developerurl',$skipParams)) {
            $this->err['error_msg'] = '回调url不能为空';
            $this->ajaxReturn($this->err, 400);
        }

        if ((!fnGet($this->input, 'trade_id') || strlen($this->input['trade_id']) > 50) && !in_array('trade_id',$skipParams)) {
            $this->err['error_msg'] = '商家订单不能空,不能超过50位';
            $this->ajaxReturn($this->err, 400);
        }

        if (fnGet($this->input, 'amount') <= 0 && !in_array('amount',$skipParams)) {
            $this->err['error_msg'] = '金额错误';
            $this->ajaxReturn($this->err, 400);
        }

        if (fnGet($this->input, 'username') <= 0 && !in_array('username',$skipParams)) {
            $this->err['error_msg'] = '用户名不能为空';
            $this->ajaxReturn($this->err, 400);
        }


        if ((!fnGet($this->input, 'product_name') || mb_strlen($this->input['product_name']) > 50) && !in_array('product_name',$skipParams)) {
            $this->err['error_msg'] = '产品名称不能空,不能超过50位';
            $this->ajaxReturn($this->err, 400);
        }

        if (mb_strlen(fnGet($this->input, 'terminalid')) > 20 && !in_array('terminalid',$skipParams)) {
            $this->err['error_msg'] = '设备标识符不能超过20位';
            $this->ajaxReturn($this->err, 400);
        }
    }

    protected function log($message)
    {
        Log::write($message, Log::DEBUG);
    }

    /**
     * @return \Common\Phalcon\Db\Adapter\Pdo\Mysql
     */
    protected function getDb()
    {
        return $this->getDI()->getShared('db');
    }
}
