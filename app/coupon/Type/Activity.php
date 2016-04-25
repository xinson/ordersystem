<?php
namespace Coupon\Type;

use Exception, Log;
use Common\Library\ConfigHelper;
use Common\Models\User;
use Coupon\Exception\CouponException;
use Coupon\Models\CouponClient;
use Coupon\Models\Coupon;

class Activity extends CouponTypeAbstract
{
    /** @var Coupon $coupon */
    private $coupon = null;

    private $coupon_client = null;

    private $couponConfig = null;

    protected function _processActivityCreate($data)
    {
        try {
            $users = $data['users'];
            foreach ($users as $user) {
                $bind = array();
                $bind['type'] = $this->couponConfig['type'];
                $bind['user_id'] = $user['user_id'];
                $bind['clientids'] = $data['clients'];
                $where = "co.type = :type: And co.user_id = :user_id: And cc.client_id In ({clientids:array}) And co.expire >= unix_timestamp(now())";
                $sql = "SELECT co.user_id FROM coupon co INNER JOIN coupon_client AS cc ON cc.coupon_id = co.coupon_id INNER JOIN client AS c ON c.id = cc.client_id WHERE {$where}";
                $existCoupon = $this->coupon->sqlFetchOne($sql, $bind);
                if ($existCoupon) {
                    continue;
                }
                $this->coupon->generateCouponCode();
                $coupon_array = array(
                    'coupon_name' => $this->couponConfig['coupon_name'],
                    'coupon_description' => $this->couponConfig['coupon_description'],
                    'type' => $this->couponConfig['type'],
                    'coupon_code' => $this->coupon->getData('coupon_code'),
                    'amount' => $user['amount'],
                    'user_id' => $user['user_id'],
                    'create_at' => $data['create_at'],
                    'expire' => $data['expire'],
                    'activated_at' => time(),
                    'status' => 'activated'
                );

                $coupon = new Coupon();
                /** @var  $couponRs */
                $coupon->save($coupon_array);  // 存在返回ID
                if ($coupon_id = $coupon->getId()) {
                    foreach ($data['clients'] as $client_id) {
                        $coupon_client_array = array(
                            'coupon_id' => $coupon_id,
                            'client_id' => $client_id
                        );
                        $couponClient = new CouponClient();
                        $couponClient->save($coupon_client_array);
                    }
                }
            }
            $result = array(array('status' => 1));
        } catch (CouponException $e) {
            $code = (string)$e->getCode();
            $result = array(array('error_msg' => $e->getMessage(), 'error_code' => $code), $code{0} . '00');
        } catch (Exception $e) {
            Log::logException($e);
            $result = array(array('error_msg' => '内部服务器错误', 'error_code' => 500), 500);
        }
        return $result;
    }

    public function getUserInfo($username)
    {
        $user = User::findFirstSimple(array("username" =>  $username));
        if(isset($user->id)) {
            return $user->id;
        }else{
            return false;
        }
    }

    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $data = fnGet($params, 'data');
        if (method_exists($this, $method)) {
            $this->coupon = new Coupon();
            $this->coupon_client = new CouponClient();
            $couponConfig = ConfigHelper::get('coupon.coupon_agents');
            $this->couponConfig = (array)fnGet($couponConfig, fnGet($params, 'coupon_agent'));
            return $this->$method($data);
        }

        return false;
    }

}
