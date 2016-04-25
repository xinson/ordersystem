<?php
namespace Common\Exception;

use Phalcon\Http\Response;

class ServiceModeBaseException extends \Exception
{
    protected $header;

    public function __construct($message, $code, $header = null)
    {
        parent::__construct($message, $code);
        $this->header = $header;
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getResponse()
    {
        $response = new Response($this->getMessage(),$this->getCode());
        $headers = $this->getHeader();
        if (!is_null($headers)) {
            if (!is_array($headers)) {
                $headers = array($headers);
            }
            foreach ($headers as $header) {
                list($name,$value) = explode(':',$header);
                $response->setHeader($name,$value);
            }
        }
        return $response;
    }


}
