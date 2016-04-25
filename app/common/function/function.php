<?php
/**
 * File: functions.php
 * Functionality: Extra validate functions
 */

// check is a url
function isUrl($url)
{
    return preg_match("#https?://(.*?)($|/)#m", $url);
}


// Check var is a valid email or not
function isEmail($email)
{
    if (!$email) {
        return false;
    }

    return preg_match('/^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$/', $email);
}

// Check var is a valid Chinese mobile or not
function isMobile($mobile)
{
    if (!$mobile) {
        return false;
    }

    return preg_match('/^((\(d{2,3}\))|(\d{3}\-))?1(3|5|8|9)\d{9}$/', $mobile);
}

// Check var is a valid postal code or not
function isPostalCode($postalCode)
{
    if (!$postalCode) {
        return false;
    }

    return preg_match("/^[1-9]\\d{5}$/", $postalCode);
}

// Check var is a valid IP Address or not
function isIPAddress($IPAddress)
{
    if (!$IPAddress) {
        return false;
    }

    return preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])" .
        "(\\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $IPAddress);
}

// Check var is a valid ID card or not
function isIDCard($IDCard)
{
    if (!$IDCard) {
        return false;
    }

    return preg_match('/(^([\d]{15}|[\d]{18}|[\d]{17}x)$)/', $IDCard);
}


/**
 * 身份证校验
 * @param $vStr
 * @return bool
 */
function isCreditNo($vStr)
{
    $vCity = array(
        '11',
        '12',
        '13',
        '14',
        '15',
        '21',
        '22',
        '23',
        '31',
        '32',
        '33',
        '34',
        '35',
        '36',
        '37',
        '41',
        '42',
        '43',
        '44',
        '45',
        '46',
        '50',
        '51',
        '52',
        '53',
        '54',
        '61',
        '62',
        '63',
        '64',
        '65',
        '71',
        '81',
        '82',
        '91'
    );

    if (!preg_match('/^([\\d]{17}[xX\\d]|[\\d]{15})$/', $vStr)) {
        return false;
    }

    if (!in_array(substr($vStr, 0, 2), $vCity)) {
        return false;
    }

    $vStr = preg_replace('/[xX]$/i', 'a', $vStr);
    $vLength = strlen($vStr);

    if ($vLength == 18) {
        $vBirthday = substr($vStr, 6, 4) . '-' . substr($vStr, 10, 2) . '-' . substr($vStr, 12, 2);
    } else {
        $vBirthday = '19' . substr($vStr, 6, 2) . '-' . substr($vStr, 8, 2) . '-' . substr($vStr, 10, 2);
    }

    if (date('Y-m-d', strtotime($vBirthday)) != $vBirthday) {
        return false;
    }
    if ($vLength == 18) {
        $vSum = 0;

        for ($i = 17; $i >= 0; $i--) {
            $vSubStr = substr($vStr, 17 - $i, 1);
            $vSum += (pow(2, $i) % 11) * (($vSubStr == 'a') ? 10 : intval($vSubStr, 11));
        }

        if ($vSum % 11 != 1) {
            return false;
        }
    }

    return true;
}

/**
 * Show execution trace for debugging
 * @param bool $exit Set to true to exit after show trace.
 * @param bool $print Set to true to print trace
 *
 * @return string
 */
function showTrace($exit = true, $print = true)
{
    $e = new \Exception;
    if ($print) {
        echo '<pre>', $e->getTraceAsString();
    }
    if ($exit) {
        exit;
    }
    return $e->getTraceAsString();
}

/**
 * Safely get child value from an array or an object
 *
 * @param array|object $array Subject array or object
 * @param $key
 * @param mixed $default Default value if key not found in subject
 * @param string $separator Key level separator, default '/'
 *
 * @return mixed
 */
function fnGet(&$array, $key, $default = null, $separator = '/')
{
    if (($sub_key_pos = strpos($key, $separator)) === false) {
        if (is_object($array)) {
            return property_exists($array, $key) ? $array->$key : $default;
        }
        return isset($array[$key]) ? $array[$key] : $default;
    } else {
        $first_key = substr($key, 0, $sub_key_pos);
        if (is_object($array)) {
            $tmp = property_exists($array, $first_key) ? $array->$first_key : null;
        } else {
            $tmp = isset($array[$first_key]) ? $array[$first_key] : null;
        }
        return fnGet($tmp, substr($key, $sub_key_pos + 1), $default, $separator);
    }
}

/**
 * Translate
 *
 * @param string $name
 * @param array|null $params
 *
 * @return string
 */
function __($name = null, array $params = null)
{
    if (!$params) {
        return $name;
    }
    $replace = array();
    foreach ($params as $k => $v) {
        $replace[':' . $k] = $v;
    }
    $result = strtr($name, $replace);
    return $result;
}

/**
 * 判断是否SSL协议
 * @return boolean
 */
function is_ssl()
{
    if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
        return true;
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    }
    return false;
}

function url($path, $queries = array(), $secure = null)
{
    if (substr($path, 0, 2) == '//' || ($prefix = substr($path, 0, 7)) == 'http://' || $prefix == 'https:/') {
        return $path;
    }
    $secure === null and $secure = is_ssl();
    $protocol = $secure ? 'https://' : 'http://';
    $host = fnGet($_SERVER, 'HTTP_HOST');
    $base = $_SERVER['SCRIPT_NAME'];
    $base = trim(dirname($base), '/');
    $base and $base .= '/';
    $url = $protocol . $host . '/' . $base;
    $url .= $path;
    if ($queries && is_array($queries)) {
        $queries = http_build_query($queries);
    }
    $queries && is_string($queries) and $url .= '?' . str_replace('?', '', $queries);
    return $url;
}

function hashPassword($password)
{
    $option = array(
        'cost' => \Common\Library\ConfigHelper::get('application.password_hash_cost'),
    );
    return password_hash($password, PASSWORD_BCRYPT, $option);
}


/**
 * 缓存管理
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value 缓存值
 * @param mixed $expire 缓存时间
 * @return mixed
 */
function S($name, $value = null, $expire = 10)
{
    if ($value === null) {
        return \Common\Library\CacheHelper::get($name);
    }
    return \Common\Library\CacheHelper::set($name, $value, $expire);
}


/**
 * 发送HTTP状态
 * @param integer $code 状态码
 * @return void
 */
function send_http_status($code)
{
    static $_status = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily ',  // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );
    if (isset($_status[$code])) {
        header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
        // 确保FastCGI模式下正常
        header('Status:' . $code . ' ' . $_status[$code]);
    }
}


/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id 数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
{
    if (is_array($attr)) {
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr = trim($attr);
    $attr = empty($attr) ? '' : " {$attr}";
    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml .= "<{$root}{$attr}>";
    $xml .= data_to_xml($data, $item, $id);
    $xml .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id 数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item = 'item', $id = 'id')
{
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if (is_numeric($key)) {
            $id && $attr = " {$id}=\"{$key}\"";
            $key = $item;
        }
        $xml .= "<{$key}{$attr}>";
        $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml .= "</{$key}>";
    }
    return $xml;
}


/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type = 0)
{
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}


/**
 * 产生随机字串，可用来自动生成密码
 * 默认长度6位 字母和数字混合 支持中文
 * @param string $len 长度
 * @param string $type 字串类型
 * 0 字母 1 数字 其它 混合
 * @param string $addChars 额外字符
 * @return string
 */
function randString($len = 6, $type = '', $addChars = '')
{
    $str = '';
    switch ($type) {
        case 0:
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . $addChars;
            break;
        case 1:
            $chars = str_repeat('0123456789', 3);
            break;
        case 2:
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $addChars;
            break;
        case 3:
            $chars = 'abcdefghijklmnopqrstuvwxyz' . $addChars;
            break;
        case 4:
            $chars = "们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借" . $addChars;
            break;
        default :
            // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
            $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789' . $addChars;
            break;
    }
    if ($len > 10) {//位数过长重复字符串一定次数
        $chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
    }
    if ($type != 4) {
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
    } else {
        // 中文随机字
        for ($i = 0; $i < $len; $i++) {
            $str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1, 'utf-8', false);
        }
    }
    return $str;
}


/**
 * 字符串截取，支持中文和其他编码
 * @static
 * @access public
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true)
{
    if (function_exists("mb_substr")) {
        $slice = mb_substr($str, $start, $length, $charset);
    } elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
    } else {
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice . '...' : $slice;
}

/**
 * 记录 debug
 * @param String $msg
 * @return \Phalcon\Logger\AdapterInterface
 */
function debug($msg)
{
    return Log::write($msg, Log::DEBUG);
}



/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0,$adv=false) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if($adv){
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos) unset($arr[$pos]);
            $ip     =   trim($arr[0]);
        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

function exceptionHandler(Exception $exception)
{
    Log::logException($exception);
    /*if (\Common\Library\ConfigHelper::get('application.debug')) {
        echo '<pre>', $exception->__toString();
        return;
    }*/
    if ($exception instanceof \Common\Exception\NotFoundException) {
        $response = $exception->getResponse();
        $response->send();
        return;
    }
    send_http_status(500);
    echo json_encode(array(
        'error_code' => 500,
        'error_msg' => 'Server down, please check log!',
    ));
}

function errorHandler($errNo, $errStr, $errFile, $errLine)
{
    throw new RuntimeException("{$errNo} - {$errStr} in file '{$errFile}' line {$errLine}", 500);
}

function throw404Exception()
{
    throw new \Common\Exception\NotFoundException(json_encode(array(
        'error_code' => 404,
        'error_msg' => '404 Not Found',
    )), 404, array('content-type:application/json'));
}
