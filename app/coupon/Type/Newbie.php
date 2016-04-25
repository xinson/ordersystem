<?php
namespace Coupon\Type;

use Exception, Log;
use Common\Library\ConfigHelper;
use Common\Models\Client;
use Coupon\Exception\CouponException;
use Coupon\Models\CouponClient;
use Coupon\Models\Coupon;

class Newbie extends CouponTypeAbstract
{
    /** @var Coupon $coupon */
    private $coupon = null;
    private $couponConfig = null;
    /** @var CouponClient $coupon */
    private $coupon_client = null;
    /** @var Client $client */
    private $client = null;

    protected function _processNewbieCreate($data)
    {
        try {
            $where = " co.user_id = :user_id: AND cc.client_id = :client_id: AND co.type = :type: ";
            $sql = "SELECT * FROM coupon co LEFT JOIN coupon_client AS cc ON co.coupon_id=cc.coupon_id WHERE {$where} LIMIT 1";
            $hasNewbie = $this->coupon->sqlFetchOne($sql, array('user_id' => $data['user_id'] , 'client_id' => $data['client_id'], 'type' => $this->couponConfig['type'] ));
            if (!empty($hasNewbie)) {
                $result = array(
                    array(
                        'status' => 1,
                        'newbie_status' => $hasNewbie['status'],
                        'activation_wait' => $this->couponConfig['activation_wait']
                    )
                );
                return $result;
            }
            $this->coupon->generateCouponCode();
            $coupon_array = array(
                'coupon_name' => $this->couponConfig['name'],
                'coupon_description' => $this->couponConfig['description'],
                'type' => $this->couponConfig['type'],
                'coupon_code' => $this->coupon->getData('coupon_code'),
                'amount' => $this->couponConfig['amount'],
                'status' => Coupon::STATUS_NEW,
                'user_id' => $data['user_id'],
                'create_at' => time(),
                'expire' => time() + $this->couponConfig['expire'],
            );
            $this->coupon->setData($coupon_array);
            $this->coupon->save();
            if ($this->coupon->getId()) {
                $this->coupon_client->setData(array(
                    'coupon_id' => $this->coupon->getId(),
                    'client_id' => $data['client_id']
                ));
                $this->coupon_client->save();
            }
            $result = array(
                array(
                    'status' => $this->coupon->getId() ? 1 : 0,
                    'newbie_status' => $this->coupon->getData('status'),
                    'activation_wait' => $this->couponConfig['activation_wait'],
                )
            );
        } catch (CouponException $e) {
            $code = (string)$e->getCode();
            $result = array(array('error_msg' => $e->getMessage(), 'error_code' => $code), $code{0} . '00');
        } catch (Exception $e) {
            Log::logException($e);
            $result = array(array('error_msg' => '内部服务器错误', 'error_code' => 500), 500);
        }
        return $result;
    }

    protected function _processNewbieActivate($data)
    {
        try {
            $field = "co.coupon_id,co.coupon_name,co.coupon_description,co.type,co.coupon_code,co.amount,co.user_id,co.client_id,co.order_id,co.create_at,co.expire,co.activated_at,co.status,co.rule_id";
            $where = "co.user_id = :user_id: AND cc.client_id = :client_id: AND co.type = :type: ";
            $sql = "SELECT {$field} FROM coupon co LEFT JOIN coupon_client AS cc ON co.coupon_id=cc.coupon_id WHERE {$where} LIMIT 1";
            $arr = $this->coupon->sqlFetchOne($sql, array('user_id' => $data['user_id'], 'client_id' => $data['client_id'], 'type' => $this->couponConfig['type'] ));
            if (!empty($arr)) {
                $now = time();
                if ($arr['status'] == Coupon::STATUS_NEW && $arr['expire'] > $now) {
                    $this->coupon->setData($arr);
                    $this->coupon->setData('activated_at', $now)->setData('status',
                        Coupon::STATUS_ACTIVATED);
                    $this->coupon->save();
                    $result = array(array('status' => $this->coupon->getId() ? 1 : 0));
                } else {
                    throw new CouponException('抵用券已激活或者已使用', CouponException::ERROR_CODE_NEWBIE_FAILD);
                }
            } else {
                throw new CouponException('抵用券不存在', CouponException::ERROR_CODE_NEWBIE_NOT_EXISTS);
            }
        } catch (CouponException $e) {
            $code = (string)$e->getCode();
            $result = array(array('error_msg' => $e->getMessage(), 'error_code' => $code), $code{0} . '00');
        } catch (Exception $e) {
            $result = array(array('error_msg' => '内部服务器错误', 'error_code' => 500), 500);
        }
        return $result;
    }

    protected function _processCouponList($data)
    {
        try {
            $bind = array();
            $where = ' co.user_id = :user_id: ';
            $bind['user_id'] = $data['user_id'];
            switch ($data['client_id']) {
                case 'current':
                    $where .= ' AND cc.client_id = :current_client_id: ';
                    $bind['current_client_id'] = $data['current_client_id'];
                    break;
                case 'all':
                    break;
                default:
                    /** @var  Client $client_id */
                    $this->client = $this->client->findFirstSimple(array("client" => $data['client_id']));
                    $where .= ' AND cc.client_id = :current_client_id: ';
                    $bind['current_client_id'] = $this->client->getId() ?: 0;
            }
            $statusArray = empty($data['status']) ?: json_decode($data['status'], true);
            if (is_array($statusArray)) {
                $queryString = '';
                foreach ($statusArray as $d => $v) {
                    $d = intval($d);
                    if ($d != 0) {
                        $queryString .= ' or';
                    }
                    $queryString .= " co.status = :status_{$d}: ";
                    $bind['status_'.$d] = $v;
                }
                $where .= " And ( " . $queryString . " )";
            }
            if (isset($data['day_overdue'])) {
                $overdue = (int)$data['day_overdue'] * 24 * 60 * 60;
                $where .= " And " . time() . " < ( co.expire + :overdue: )";
                $bind['overdue'] = $overdue;
            }
            $total = $this->coupon->sqlFetchColumn("SELECT COUNT(distinct(co.coupon_id)) AS tp_count FROM coupon co LEFT JOIN coupon_client AS cc ON co.coupon_id=cc.coupon_id WHERE {$where} LIMIT 1", $bind);
            $totalPage = ceil($total / $data['page_size']);

            if ($data['position'] + 1 > $totalPage) {
                $data['position'] = 0;
            }
            $field = 'co.coupon_id,co.coupon_code,co.coupon_name,co.coupon_description,co.amount,co.create_at as created_at,co.status,co.expire,co.activated_at,';
            $field .= '(SELECT GROUP_CONCAT(ct.name) from coupon_client cn LEFT JOIN client ct on cn.`client_id` = ct.`id` where cn.`coupon_id` = co.`coupon_id`) client_name';
            $start = $data['position'] * $data['page_size'];
            $sql = "SELECT DISTINCT {$field} FROM `coupon` co LEFT JOIN `coupon_client` AS cc ON co.coupon_id=cc.coupon_id WHERE {$where} ORDER BY co.coupon_id DESC LIMIT :start:, :page_size: ";
            $bind['start'] = (int)$start;
            $bind['page_size'] = (int)$data['page_size'];
            $list = $this->client->sqlFetchAll($sql,$bind);
            return array(array('coupon_list' => $list, 'position' => $data['position']));
        } catch (Exception $e) {
            Log::logException($e);
            $result = array(array('error_msg' => '内部服务器错误', 'error_code' => 500), 58300);
        }
        return $result;
    }

    public function process($params)
    {
        $method = '_process' . parse_name(fnGet($params, 'method'), 1);
        $data = fnGet($params, 'data');
        if (method_exists($this, $method)) {
            $this->coupon = new Coupon();
            $this->coupon_client = new CouponClient();
            $this->client = new Client();
            $couponConfig = (array)ConfigHelper::get('coupon.coupon_agents');
            $this->couponConfig = fnGet($couponConfig, fnGet($data, 'coupon_agent'));
            return $this->$method($data);
        }
        return false;
    }

}
