<?php
if (!empty($_REQUEST['fpm'])) {
    return;
}

$config = is_file($configFile = dirname(__DIR__) . '/app/common/config/production/service.php') ? include($configFile) : array();
$runDir = isset($config['run_dir']) ? $config['run_dir'] : '/tmp/pay/';

if(!is_file($runDir . 'service-port.php')) return;
$port = include($runDir . 'service-port.php');

$sockFile = $runDir . 'service-' . $port . '.sock';
$client = new swoole_client(SWOOLE_UNIX_STREAM);
$client->set(array(
    'open_length_check' => true,
    'package_max_length' => 262144,
    'package_length_type' => 'N',
    'package_length_offset' => 0,
    'package_body_offset' => 4,
));

//发起网络连接
if (!@$client->connect($sockFile, 0, 20)) {
    return;
}
//构建headers
$request = array(
    'request' => $_REQUEST,
    'cookies' => $_COOKIE,
    'server' => $_SERVER,
    'files' => $_FILES
);
//序列化对象
$request = serialize($request);
//无号短整数 (32位, 高位在后的顺序)
$request = pack('N', strlen($request)) . $request;

$client->send($request);
$response = $client->recv();
$client->close();

//if ($response === false) {
//    header('Bad Gateway', true, 502);
//    echo '<html><head><title>502 Bad Gateway</title></head><body bgcolor="white"><center><h1>502 Bad Gateway</h1></center><hr><center>nginx</center><div style="display:none">err ' . $client->errCode . ': ' . swoole_strerror($client->errCode) . '</div></body></html>';
//    exit;
//}

$length = unpack('N', $response)[1];
$response = unserialize(substr($response, -$length));

if (isset($response['headers']) && is_array($headers = $response['headers'])) {
    foreach ($headers as $k => $v) {
        if (empty($v)) {
            header($k, false);
        } else {
            header($k.":".$v, false);
        }
    }
}
if (isset($response['cookies']) && is_array($response['cookies'])){
    foreach ($response['cookies'] as $cookie) {
        call_user_func_array('setcookie', $cookie);
    }
}
if (isset($response['meta']) && is_array($response['meta'])) foreach ($response['meta'] as $k => $v) {
    header('X-Meta-' . $k . ': ' . $v);
}
echo $response['body'];
exit;
