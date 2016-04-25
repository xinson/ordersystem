<?php
namespace Common\Library;

class EventData
{
    protected $_data = array();

    public function __construct($data = null)
    {
        is_array($data) and $this->_data = $data;
    }

    public function getData($key = null)
    {
        return $key === null ? $this->_data : fnGet($this->_data, $key);
    }

    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->_data = $key;
        } else {
            if (is_scalar($key)) {
                $this->_data[$key] = $value;
            }
        }
        return $this;
    }
}
