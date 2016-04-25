<?php
namespace Common\Models;

use Common\Library\LogHelper;
use Phalcon\Db;
use \Phalcon\Mvc\Model as PhalconModel;

abstract class Model extends PhalconModel
{
    public $pk = 'id';

    public function addData(array $data)
    {
        $data = array_merge($this->toArray(), $data);
        foreach ($data as $key => $val) {
            $this->setData($key, $val);
        }
        return $this;
    }

    public function getData($key = null)
    {
        return $key === null ? $this->toArray() : (property_exists($this, $key) ? $this->$key : null);
    }

    public function getId()
    {
        return $this->getData($this->pk);
    }

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->$k = $v;
            }
        } else {
            $this->$key = $value;
        }
        return $this;
    }

    public function setId($id)
    {
        return $this->setData($this->pk, $id);
    }

    /**
     * @param $sql
     * @param null $bind
     * @return bool
     */
    public function sqlExecute($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql,$bind);
        return $this->getReadConnection()->execute($sql, $bind);
    }

    /**
     * @param $sql
     * @param null $bind
     * @return array
     */
    public function sqlFetchAll($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql,$bind);
        return $this->getReadConnection()->fetchAll($sql, Db::FETCH_ASSOC, $bind);
    }

    /**
     * @param $sql
     * @param null $bind
     * @return array
     */
    public function sqlFetchOne($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql,$bind);
        return $this->getReadConnection()->fetchOne($sql, Db::FETCH_ASSOC, $bind);
    }

    /**
     * @param $sql
     * @param null $bind
     * @return mixed
     */
    public function sqlFetchColumn($sql, $bind = null)
    {
        $row = $this->sqlFetchOne($sql, $bind);
        return reset($row);
    }

    private function translatePhalconBindIntoPDO($sql,&$bind = null)
    {
        if (!empty($bind) && is_array($bind)) foreach ($bind as $key => $val) {
            //优先处理int值,不使用PDO
            if (is_int($val)) {
                $sql = str_replace(":{$key}:", $val, $sql);
                unset($bind[$key]);
                continue;
            }
            //字符串处理
            $search = array(":{$key}:");
            $replace = array(":{$key}");
            //数组处理
            if (strstr($sql, ($inReplace = "{{$key}:array}")) !== false) {
                if (is_array($val)) {
                    $temp = '';
                    foreach ($val as $vkey => $vval) {
                        $realKey = "{$key}_{$vkey}";
                        $temp .= ($temp==''?'':' , '). " :{$realKey} ";
                        $bind[$realKey] = $vval;
                    }
                    array_push($search, $inReplace);
                    array_push($replace, $temp);
                    unset($bind[$key]);
                }
            }
            $sql = str_replace($search, $replace, $sql);
        }
        return $sql;
    }

    /**
     * 简单版查询
     * @param array | string | int $conditions
     * @param array $bind
     * @return PhalconModel
     */
    public static function findFirstSimple($conditions, $bind = array())
    {
        $params = array();
        if (empty($bind)) {
            if (is_array($conditions)) {
                $params['conditions'] = "";
                $params['bind'] = array();
                foreach ($conditions as $key => $value) {
                    if (!is_array($value)) {
                        $operater = '=';
                        $realValue = $value;
                    } else {
                        $operater = reset($value);
                        $realValue = next($value);
                    }
                    $params['conditions'] .= ( $params['conditions'] == "" ? "" : " AND " ) . " {$key} {$operater} :{$key}: ";
                    $params['bind'][$key] = $realValue;
                }
            } else {
                $params['conditions'] = $conditions;
            }
        } else {
            $params['conditions'] = $conditions;
            $params['bind'] = $bind;
        }
        return static::findFirst($params);
    }

}
