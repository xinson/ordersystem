<?php
namespace Tests;

class BaseController extends UnitTestCase
{
    public $base_url = '';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }

    /**
     * 初始化
     */
    public function init()
    {
        $db = $this->getDB();
        /* @var $db \Phalcon\Db\Adapter\Pdo\Mysql */
        $user = $db->fetchOne("select * from `user` where username = 'tester2015' ");
        if (empty($user)) {
            $password = hashPassword('123456');
            $avatar = 'http://test.passport.com/uploads/avatar/2f/FT/4gi8.png';
            $db->insert('user', array('tester2015', $password , 'tester2015@appgame.com', '13122223333', $avatar ),array('username','password','email','mobile','avatar'));
        }

        $client = $db->fetchOne("select * from `client` where `name` = 'tester2015'");
        if (empty($client)) {
            $db->insert('client', array('tester2015', 'tester2015', 'tester2015'), array( 'client', 'name', 'app_secret'));
        }

        $this->base_url = $this->getConfigByKey('application.base_url');
    }

}
