<?php
namespace Coupon\Models;

use Common\Library\ConfigHelper;
use Common\Library\HttpClient;
use Pay\Models\Order;
use Common\Models\Model;
use Common\Models\User;
use Common\Models\Client;
use Coupon\Exception\CouponOrderException;

/**
 * Class Coupon
 * @package Coupon\Model
 * RelationModel
 */
class Coupon extends Model
{
    public $coupon_id;
    public $pk = 'coupon_id';
    /** @var EventUser $e_user */
    protected $e_user;

    const STATUS_NEW = 'new';
    const STATUS_ACTIVATED = 'activated';
    const STATUS_USED = 'used';
    const STATUS_EXPIRED = 'expired';

    protected $_link = array(
        'coupon_client' => array(
            'coupon_id' => 'coupon_id'
        )
    );

    public function initialize()
    {
        $this->setSource("coupon");
    }

    /**
     * 生成抵用券唯一标识码
     * @access public
     * @param string $prefix - 前缀
     * @return static
     */
    public function generateCouponCode($prefix = 'CP')
    {
        $couponCode = $prefix . randString(16, 2, '0123456789');
        if (Coupon::findFirstSimple(array("coupon_code" => $couponCode))) {
            return $this->generateCouponCode($prefix);
        } else {
            $this->setData('coupon_code', $couponCode);
        }
        return $this;
    }

    public function getClientId()
    {
        $client = new CouponClient();
        /** @var CouponClient $query */
        $query = $client->findFirstSimple(array("coupon_id" => $this->getData('coupon_id')))->toArray();
        return $this->array_fetch($query, 'client_id');
    }

    public function getStatus()
    {
        return $this->getData('status');
    }

    public function getUserId()
    {
        return $this->getData('user_id');
    }

    public function isValid($userId, $clientId, $orderId)
    {
        if ($this->getStatus() != static::STATUS_ACTIVATED) {
            if ($orderId != $this->getData('order_id')) {
                return false;
            }
        }
        if ($this->getUserId() && $this->getUserId() != $userId) {
            return false;
        }
        if ($this->getClientId() && !in_array($clientId, $this->getClientId())) {
            return false;
        }
        return true;
    }

    /**
     * 使用抵用券
     * @param Order $order
     *
     * @return static
     */
    public function redeem($order)
    {
        if ($this->isValid($order->getData('user_id'), $order->getData('client_id'), $order->getId())) {
            $amount = $order->getData('cash_to_pay');
            $couponAmount = $this->getData('amount');
            $cashToPay = $amount - $couponAmount;
            $cashToPay < 0 and $cashToPay = 0;
            $order->setData('cash_to_pay', $cashToPay)
                ->setOrderData('coupon_amount', $couponAmount);
            $this->setData('status', static::STATUS_USED);
        } else {
            throw new CouponOrderException(__('抵用券无效'), CouponOrderException::ERROR_CODE_INVALID_COUPON);
        }
        return $this;
    }

    /**
     * 订单失败时恢复抵用券
     */
    public function refund()
    {
        if ($this->getData('status') == static::STATUS_USED) {
            $this->setData('status', static::STATUS_ACTIVATED)
                ->setData('order_id', null);
        }
        return $this;
    }

    /**
     * 添加用户
     * @param $data
     */
    public function eventTable($data)
    {
        $this->e_user = new EventUser();
        $userType = $data['tube'];   // 类型
        $userData = $data['data'];
        if ($userType === 'login') {
            $this->saveLoginEventUser($userData);
            $this->loginDate($userData);
        } else {
            if ($userType === 'register') {
                $this->saveRegisterEventUser($userData);  // 注册的用户
            } else {
                if ($userType === 'pay_event_callback') {
                    $this->singleMoney($userData); // 单次充值
                    $this->sumMoney($userData);  // 充值总额
                } else {
                    if ($userType === 'heartbeat') {
                        $this->saveHeartbeatEventUser($userData);
                        $this->onlineTime($userData);
                    }
                }
            }
        }
    }

    /**
     * 添加新注册的用户
     * @param $data
     */
    public function saveRegisterEventUser($data)
    {
        $username = $data['username'];
        $client = $data['client_id'];
        $time = $data['time'];
        $eventUserQuery = $this->e_user->findFirstSimple(array("type" => 'register', "username" => $username));  // 是否存在注册的用户
        if (!isset($eventUserQuery->id)) {
            $params = array(
                'type' => 'register',
                'username' => $username,
                'client' => $client,
                'time' => $time
            );
            $this->e_user->setData($params);
            $this->e_user->save();
        }
    }

    /**
     * 添加心跳数据
     * @param $data
     */
    public function saveHeartbeatEventUser($data)
    {
        $username = $data['uname'];
        $user_id = $this->getUserQuery($username);
        $time = $data['time'];
        $date = intval(date('Ymd', $time));
        /** @var EventUser $userQuery */
        $userQuery = $this->e_user->findFirstSimple(array("type" => 'heartbeat' ,"username" => $username));
        if (isset($userQuery->id)) {
            $userQueryArray = $userQuery->toArray();
            if (round(($userQueryArray['time'] - $time) / 86400) > 1) {
                return;
            }
            $params = $userQueryArray;
            $userData = (array)json_decode($userQueryArray['data'], true);
            if (array_key_exists($date, $userData)) {
                $dateArr = $userData[$date];
                if (!in_array($time, $dateArr['dateArr'])) {
                    array_push($dateArr['dateArr'], $time);
                    ksort($dateArr['dateArr']);
                    $dateArr['heartbeatCount'] = $dateArr['heartbeatCount'] + 1;
                    $userData[$date] = $dateArr;
                }
            } else {
                $userData[$date] = array(
                    'heartbeatCount' => 1,
                    'dateArr' => array($time)
                );
                ksort($userData);
            }
            $params['time'] = $time;
            $params['data'] = json_encode($userData);

            $userQuery->setData($params);
            $userQuery->save();
        } else {
            $userData = array(
                $date => array(
                    'heartbeatCount' => 1,
                    'dateArr' => array(
                        $time
                    )
                )
            );
            $params = array(
                'user_id' => $user_id,
                'username' => $username,
                'type' => 'heartbeat',
                'client' => '',
                'time' => $time,
                'data' => json_encode($userData)
            );
            $this->e_user->setData($params);
            $this->e_user->save();
        }
    }

    /**
     * 心跳表
     * @param $data
     */
    public function onlineTime($data)
    {
        $rules = new Rules();
        $rulesQuery = $rules->query()->where("type = 'onlineTime'")->execute();
        $e_user = new EventUser();
        $username = $data['uname'];
        $client = $data['appkey'];
        foreach ($rulesQuery as $query) {
            /** @var  Rules $query */
            $query = $query->toArray();
            $condition = (array)json_decode($query['condition'], true);  // 规则
            $triggerClient = array_key_exists('triggerClient', $condition) ? $condition['triggerClient'] : null;
            if (($triggerClient && !in_array($client, $triggerClient)) || $query['number'] <= 0) {
                continue;
            }
            $duration = $condition['duration'];  // 要求时长
            $signDay = $condition['signDay'];  // 注册天数
            $startDate = $condition['startDate']; // 活动初始日期
            $endDate = $condition['endDate']; //活动结束日期
            $bool = true;
            if ($signDay) {
                $time = 86400 * $signDay;
                $registerQuery = $e_user->findFirstSimple("type = 'register' and username = :username: and time + :time: <= unix_timestamp(now())",array('username'=>$username, 'time' => $time));
                if (!isset($registerQuery->id)) {
                    $bool = false;
                }
            }
            if ($bool) {
                $eventUserQuery = $e_user->findFirstSimple(array("type" => 'heartbeat' , "username" =>$username));
                $eventUserQueryArray = $eventUserQuery->toArray();
                $eventUserDataArr = json_decode($eventUserQueryArray['data'], true);    // 用户心跳的数据
                $count = 0;
                foreach ($eventUserDataArr as $key => $row) {
                    // 如果日期小于活动开始日期和结束日期
                    if (strtotime($key) > $endDate || strtotime($key) < $startDate) {
                        continue;
                    }
                    $count = $count + $row['heartbeatCount'];
                }
                // count 为心跳的次数, 心跳一次相隔5分钟
                if ($count * 5 >= $duration) {
                    $params = array(
                        'amount' => $query['amount'],
                        'client' => $query['client_id'],
                        'create_at' => $query['create_at'],
                        'expire' => $query['expire'],
                        'type' => 'heartbeat',
                        'username' => $username,
                        'rule_id' => $query['id']
                    );
                    $this->saveCoupon($params);
                }
            }
        }
    }

    /**
     * 单次充值的记录
     * @param $data
     */
    public function singleMoney($data)
    {
        $username = $data['username'];
        $time = $data['time'];
        $status = $data['status'];
        $amount = floatval($data['amount']);
        $client = $data['client_id'];
        $rules = new Rules();
        $rulesQuery = $rules->query()->where(" type = 'singleMoney' ")->execute();
        foreach ($rulesQuery as $query) {
            /** @var Rules $query */
            $query = $query->toArray();
            $condition = (array)json_decode($query['condition'], true);
            $triggerClient = array_key_exists('triggerClient', $condition) ? $condition['triggerClient'] : null;
            if (($triggerClient && !in_array($client, $triggerClient)) || $query['number'] <= 0) {
                continue;
            }
            // 条件满足
            if ($time >= $condition['startDate'] && $time <= $condition['endDate'] && $amount >= $condition['money'] && $status === 'complete') {
                // 抵用卷的参数
                $params = array(
                    'amount' => $query['amount'],
                    'client' => $query['client_id'],
                    'create_at' => $query['create_at'],
                    'expire' => $query['expire'],
                    'type' => 'singleMoney',
                    'username' => $username,
                    'rule_id' => $query['id']
                );
                $this->saveCoupon($params);
            }
        }
    }

    /**
     * 充值总额的记录
     */
    public function sumMoney($data)
    {
        $rules = new Rules();
        $username = $data['username'];
        $client = $data['client_id'];
        $rulesQuery = $rules->query()->where("type =  'sumMoney'")->execute();
        foreach ($rulesQuery as $query) {
            /** @var Rules $query */
            $query = $query->toArray();
            $condition = json_decode($query['condition'], true);
            $triggerClient = array_key_exists('triggerClient', $condition) ? $condition['triggerClient'] : null;
            if (($triggerClient && !in_array($client, $triggerClient)) || $query['number'] <= 0) {
                continue;
            }
            $orderModel = new Order();
            $bind = array();
            $bind['username'] = $username;
            $bind['start_date'] = $condition['startDate'];
            $bind['end_date'] = $condition['endDate'];
            $where = "username = :username: and completed_at between :start_date: and :end_date: and status = 'complete'";
            $bind['money'] = $condition['money'];
            $sql = "SELECT o.user_id FROM orders as o WHERE {$where} GROUP BY o.user_id HAVING SUM(o.amount) >= :money: limit 1";
            $users = $orderModel->sqlFetchOne($sql,$bind);
            if ($users) {
                $params = array(
                    'amount' => $query['amount'],
                    'client' => $query['client_id'],
                    'create_at' => $query['create_at'],
                    'expire' => $query['expire'],
                    'user_id' => $users['user_id'],
                    'type' => 'sumMoney',
                    'username' => $username,
                    'rule_id' => $query['id']
                );
                $this->saveCoupon($params);
            }
        }
    }

    /**
     * 用户登录时 可能触发多种事件
     * 1. 用户是否在指定日日期登录
     * 2. 用户是否符合指定登录的天数
     * @param $data
     * 登录事件
     */
    public function loginDate($data)
    {
        $rules = new Rules();
        $username = $data['username'];   // 用户名
        $time = $data['time'];          // 登录日期
        $client = $data['client_id'];
        $rulesQuery = $rules->query()->where('type in ("fixedDate", "loginDay")')->execute();   // 查规则
        foreach ($rulesQuery as $query) {
            /** @var Rules $query */
            $query = $query->toArray();
            $condition = (array)json_decode($query['condition'], true);
            $triggerClient = array_key_exists('triggerClient', $condition) ? $condition['triggerClient'] : null;
            if (($triggerClient && !in_array($client, $triggerClient)) || $query['number'] <= 0) {
                continue;
            }
            $type = $query['type'];
            $amount = $query['amount'];  // 金额

            $create_at = $query['create_at'];
            $expire = $query['expire'];


            $params = array();
            // 指定登录的日期
            if ($type === 'fixedDate') {
                $dateBetween = $condition['dateBetween'];
                $conditionBool = false;
                foreach ($dateBetween as $date) {
                    if ($date['startDate'] <= $time && $time <= $date['endDate']) {
                        $conditionBool = true;
                    }
                }
                // 条件满足
                if ($conditionBool) {
                    $params = array(
                        'amount' => $amount,
                        'create_at' => $create_at,
                        'expire' => $expire,
                        'username' => $username,
                        'type' => 'fixedDate',
                        'client' => $query['client_id'],
                        'rule_id' => $query['id']
                    );
                }
            } elseif ($type === 'loginDay') {
                $startDate = $condition['startDate'];  // 活动开始日期
                $endDate = $condition['endDate']; // 活动结束日期
                $day = $condition['day'];  // 天数
                $c_bool = $condition['continuous'];  // 是否连续登录 true or false
                $eventUserQuery = $this->e_user->findFirstSimple(array("type" => 'login',  "username" => $username , "client" => $client));
                $bool = false;
                if (isset($eventUserQuery->id)) {
                    /** @var EventUser $eventUserQuery */
                    $eventUserQuery = $eventUserQuery->toArray();
                    $userData = json_decode($eventUserQuery['data'], true);
                    $activityDateBetween = array();
                    for ($i = 0; $i < round(($endDate - $startDate) / 86400) + 1; $i++) {
                        array_push($activityDateBetween, intval(date('Ymd', $startDate + 86400 * $i)));
                    }
                    // 判断如果是连续登录的用户
                    if ($c_bool === 'true') {
                        foreach ($userData as $user_q) {
                            $continuousDay = $user_q['continuous'];
                            // 条件不满足连续的天数, 跳出
                            $cStartDate = strtotime($user_q['date']['startDate']);
                            $cEndDate = strtotime($user_q['date']['endDate']);
                            if ($continuousDay < $day || $cEndDate < $startDate || $cStartDate > $endDate) {
                                continue;
                            }
                            $loginDateBetween = array();
                            for ($j = 0; $j < round(($cEndDate - $cStartDate) / 86400) + 1; $j++) {
                                array_push($loginDateBetween, intval(date('Ymd', $cStartDate + 86400 * $j)));
                            }
                            $intersection = array_intersect($activityDateBetween, $loginDateBetween);
                            if (count($intersection) >= $day) {
                                $bool = true;
                                break;
                            }
                        }
                    } else {
                        $daysCount = 0;
                        foreach ($userData as $user_q) {
                            $cStartDate = strtotime($user_q['date']['startDate']);
                            $cEndDate = strtotime($user_q['date']['endDate']);
                            if ($cEndDate < $startDate || $cStartDate > $endDate) {
                                continue;
                            }
                            for ($j = 0; $j < round(($cEndDate - $cStartDate) / 86400) + 1; $j++) {
                                $days = $cStartDate + 86400 * $j;
                                if ($startDate <= $days && $days <= $endDate) {
                                    $daysCount = $daysCount + 1;
                                }
                            }
                            if ($daysCount >= $day) {
                                $bool = true;
                                break;
                            }
                        }
                    }
                    if ($bool) {
                        $params = array(
                            'amount' => $amount,
                            'client' => $query['client_id'],
                            'create_at' => $create_at,
                            'expire' => $expire,
                            'username' => $username,
                            'type' => 'loginDay',
                            'rule_id' => $query['id']
                        );
                    }
                }
            }
            if ($params) {
                $this->saveCoupon($params);
            }
        }
    }

    /**
     * 添加或更新登录数据
     * @param $data
     */
    public function saveLoginEventUser($data)
    {
        $username = $data['username'];
        $user_id = $this->getUserQuery($username);
        $time = $data['time'];
        $client = $data['client_id'];
        $date = intval(date('Ymd', $time));  // 队列推送的登录日期
        /** @var EventUser $userQuery */
        $userQuery = $this->e_user->findFirstSimple(array("type" => 'login' , "username" => $username,  "client" => $client));
        if (isset($userQuery->id)) {
            $userQueryArray = $userQuery->toArray();
            if (round(($userQueryArray['time'] - $time) / 86400) > 1) {
                return;
            }
            $userData = json_decode($userQueryArray['data'], true);
            $bool = false;
            $keySort = [];
            foreach ($userData as $key => $row) {
                $keySort[$key] = $row['date']['startDate'];
                if ($row['date']['startDate'] <= $date && $date <= $row['date']['endDate']) {
                    $bool = true;  //是否有过添加
                }
            }
            if (!$bool) {
                array_multisort($keySort, SORT_ASC, $userData);
                $endArr = end($userData);
                $enDate = $endArr['date']['endDate'];
                if (round(($time - strtotime($enDate)) / 86400) == 1) {
                    array_pop($userData);
                    $endArr['continuous'] = (int)$endArr['continuous'] + 1;
                    $endArr['date']['endDate'] = $date;
                    array_push($userData, $endArr);
                } else {
                    $endArr['continuous'] = 1;
                    $endArr['date']['startDate'] = $date;
                    $endArr['date']['endDate'] = $date;
                    array_push($userData, $endArr);
                }
                $userQueryArray['time'] = $time;
                $userQueryArray['data'] = json_encode($userData);
            }
            $userQuery->save();
        } else {
            $data = array(
                array(
                    'date' => array(
                        'startDate' => intval($date),
                        'endDate' => intval($date)
                    ),
                    'continuous' => 1
                )
            );
            $userQueryArray = array(
                'user_id' => $user_id,
                'username' => $username,
                'type' => 'login',
                'client' => $client,
                'time' => $time,
                'data' => json_encode($data)
            );
            $this->e_user->setData($userQueryArray);
            $this->e_user->save();
        }
    }

    /**
     * 添加活动抵用卷
     * @param $params
     */
    public function saveCoupon($params)
    {
        $coupon = new Coupon();
        $rules = new Rules();
        $client = json_decode($params['client']);  // 客户端
        $client_ids = $this->getClientQuery($client);
        $user_id = $this->getUserQuery($params['username']);
        if ($params && $client_ids && $user_id && count($client_ids) === count($client)) {
            $this->generateCouponCode();
            $clientIn = implode(',', $client_ids);
            $where = " co.user_id = '{$user_id}' AND cc.client_id IN ({$clientIn}) AND co.type = '{$params['type']}' And co.expire >= unix_timestamp(now()) ";
            $sql = "SELECT co.user_id FROM coupon AS co LEFT JOIN coupon_client AS cc ON cc.coupon_id = co.coupon_id LEFT JOIN client AS c ON c.id = cc.client_id WHERE {$where} LIMIT 1";
            $existCoupon = $coupon->sqlFetchOne($sql);
            if (!$existCoupon && $params['expire'] > time()) {
                $result = array(
                    'amount' => $params['amount'],
                    'create_at' => $params['create_at'],
                    'coupon_code' => $this->getData('coupon_code'),
                    'expire' => $params['expire'],
                    'user_id' => $user_id,
                    'activated_at' => time(),
                    'status' => 'activated',
                    'type' => $params['type'],
                    'coupon_name' => '抵用卷',
                    'coupon_description' => '抵用卷',
                    'rule_id' => intval($params['rule_id'])
                );
                $coupon->setData($result);
                $coupon->save();
                if (isset($coupon->coupon_id)) {
                    foreach ($client_ids as $client_id) {
                        $data = array(
                            'coupon_id' => intval($coupon->coupon_id),
                            'client_id' => intval($client_id)
                        );
                        $c_client = new CouponClient();
                        $c_client->setData($data);
                        $c_client->save();
                    }
                }
                /** @var Rules $ruleQuery */
                $ruleQuery = $rules->findFirstSimple(array("id" => $params['rule_id']));
                if (isset($ruleQuery->id)) {
                    $ruleQuery->setData('number', $ruleQuery->getData('number') - 1);
                    $ruleQuery->save();
                }
            }
        }
    }

    public function getUserQuery($username)
    {
        $time = time();
        $url = str_replace('internal-resource/user-info-by-username?', '',
                ConfigHelper::get('passport.passportUrl')) . 'internal-resource/user-info-by-username';
        $http = new HttpClient();
        $query = new User;
        $params = array(
            'app' => ConfigHelper::get('passport.passportApp'),
            'time' => $time,
            'username' => $username
        );
        ksort($params);
        $sign = md5(implode('', $params) . ConfigHelper::get('passport.passportSecret'));
        $params['sign'] = $sign;
        //检测用户是否已经保存
        /** @var User $hasUser */
        $userId = null;
        $hasUser = User::findFirstSimple(array("username"  => $username));
        if (!isset($hasUser->id) || !isset($hasUser->passport_id)) {
            $response = $http->request($url, $params);
            $data = json_decode($response, true);
            if (fnGet($data, 'id')) {
                $query->setData(array(
                    'username' => $username,
                    'email' => fnGet($data, 'email'),
                    'mobile' => fnGet($data, 'mobile'),
                    'passport_id' => fnGet($data, 'passport_id'),
                    'avatar' => fnGet($data, 'avatar'),
                    'nickname' => fnGet($data, 'nickname'),
                ));
                $query->save();
                $userId = $query->id;
            }
        } else {
            $userId = $hasUser->id;
        }
        return $userId;
    }

    public function getClientQuery($client_ids)
    {
        $url = str_replace('internal-resource/client-info-by-clientId?', '',
                ConfigHelper::get('passport.passportUrl')) . 'internal-resource/client-info-by-clientId';
        $returnArray = array();
        foreach ($client_ids as $client_id) {
            $http = new HttpClient;
            $query = new Client;
            $time = time();
            $params = array(
                'app' => ConfigHelper::get('passport.passportApp'),
                'time' => $time,
                'client' => $client_id
            );
            ksort($params);
            $sign = md5(implode('', $params) . ConfigHelper::get('passport.passportSecret'));
            $params['sign'] = $sign;
            $clientId = null;
            $hasClient = Client::findFirstSimple(array("client" => $client_id));
            if (!isset($hasClient->id)) {
                $response = $http->request($url, $params);
                $data = json_decode($response, true);
                if ($client_id = fnGet($data, 'client_info/id')) {
                    $query->setData(array(
                        'client' => $client_id,
                        'name' => fnGet($data, 'client_info/name'),
                        'app_secret' => fnGet($data, 'client_info/secret'),
                        'developerurl' => fnGet($data, 'client_info/endpoint'),
                        'scopes' => fnGet($data, 'client_info/scopes'),
                    ));
                    $query->save();
                    $clientId = $query->id;
                }
            } else {
                $clientId = $hasClient->id;
            }
            if ($clientId) {
                array_push($returnArray, $clientId);
            }
        }
        return $returnArray;
    }

    public function array_fetch($array, $key)
    {
        foreach (explode('.', $key) as $segment) {
            $results = [];

            foreach ($array as $value) {
                if (array_key_exists($segment, $value = (array)$value)) {
                    $results[] = $value[$segment];
                }
            }

            $array = array_values($results);
        }

        return array_values($results);
    }
}
