<?php

namespace Common\Library;

use Exception;

class HttpClient
{
    protected $_methodMapping = array(
        'POST' => array('method' => CURLOPT_POST, 'value' => true),
        'PUT' => array('method' => CURLOPT_CUSTOMREQUEST, 'value' => 'PUT'),
        'DELETE' => array('method' => CURLOPT_CUSTOMREQUEST, 'value' => 'DELETE'),
    );
    public $connect_time_out = 30;
    public $proxy = null;
    public $http_code = '';
    public $http_info = array();
    public $response_headers = false;
    public $ssl_verify_host = false;
    public $ssl_verify_peer = false;
    public $time_out = 30;
    public $throw_errors = false;
    public $user_agent = 'Appgame Payment System pay.appgame.com';
    public $last_error;
    public $last_request;
    public $last_response;

    public function __construct(array $options = array())
    {
        if (ConfigHelper::get('application.HTTP_CLIENT_SSL_VERIFY')) {
            $this->ssl_verify_host = 2;
            $this->ssl_verify_peer = true;
        }
        foreach ($options as $k => $v) {
            property_exists($this, $k) and $this->$k = $v;
        }
    }

    public function request($url, $params = false, $method = 'GET', $headers = array())
    {
        $ch = curl_init();
        $this->last_error = $this->last_response = null;
        $this->last_request = compact('url', 'params', 'method', 'headers');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, $this->response_headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->time_out);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_time_out);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->ssl_verify_peer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->ssl_verify_host);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        if ($method == 'GET') {
            $querySeparator = (strpos($url, '?') === false ? '?' : '&');
            if (is_array($params)) {
                $url .= $querySeparator . http_build_query($params);
            } else {
                if (is_string($params)) {
                    $url .= $querySeparator . $params;
                }
            }
        } else {
            curl_setopt($ch, fnGet($this->_methodMapping, $method . '/method', CURLOPT_POST),
                fnGet($this->_methodMapping, $method . '/value', true));
            if ($params) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);

        $this->last_response = $response = curl_exec($ch);
        if ($errNo = curl_errno($ch)) {
            $this->last_error = array(
                'err_no' => $errNo,
                'error' => $error = curl_error($ch),
            );
        }

        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ch));

        curl_close($ch);
        if ($this->throw_errors && $this->last_error) {
            throw new Exception($this->last_error['error'], $this->last_error['err_no']);
        }

        return $response;
    }
}
