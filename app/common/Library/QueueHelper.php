<?php
namespace Common\Library;

use Pheanstalk_Pheanstalk;

class QueueHelper
{

    public static $instance = null;

    /**
     * @return null | Pheanstalk_Pheanstalk
     * @throws \Exception
     */
    public static function getInstance()
    {
        $beanstalkConfig = ConfigHelper::get('queue.beanstalk');
        if (!fnGet($beanstalkConfig, 'enable') || !fnGet($beanstalkConfig, 'host')) {
            return false;
        }
        if (static::$instance == null) {
            static::$instance = new Pheanstalk_Pheanstalk(fnGet($beanstalkConfig, 'host'), fnGet($beanstalkConfig, 'port'));
        }
        return static::$instance;
    }

    /**
     * @param $tubename
     * @param $jobData
     * @param array $extraData
     */
    public static function Putintube($tubename, $jobData, $extraData = array())
    {
        /** @var Pheanstalk_Pheanstalk $beanstalkd */
        $beanstalkd = self::getInstance();
        $jobData['extra_data'] = $extraData;
        //检查服务器连接状态
        if($beanstalkd && $beanstalkd->getConnection()->isServiceListening()){
            $beanstalkd->putInTube($tubename, json_encode($jobData));
        }else{
            self::mkfileQueuedata($tubename, $jobData);
        }
    }

    /**
     * @param $tubename
     * @param $jobData
     */
    public static function mkfileQueuedata($tubename, $jobData)
    {
        is_dir($dir = APP_PATH . '/storage/queuedata') or mkdir($dir, 0777, true);
        $filename = $dir . '/' . time() . substr(microtime(), 0,
                6) . '.' . $tubename . '.' . md5($tubename . json_encode($jobData)) . '.data';
        file_put_contents($filename, json_encode(array('tube' => $tubename, 'data' => $jobData)));
    }

    /**
     * Cron 任务处理
     */
    public function processUntreatedQueue()
    {
        /** @var Pheanstalk_Pheanstalk $beanstalkd */
        $beanstalkd = self::getInstance();
        //检查服务器连接状态
        if($beanstalkd && $beanstalkd->getConnection()->isServiceListening()){
            $dir = APP_PATH . '/storage/queuedata';
            $files = glob($dir . '/*.data');
            foreach ($files as $file) {
                try {
                    $content = json_decode(file_get_contents($file), true);
                    $tube = fnGet($content, 'tube');
                    $data = fnGet($content, 'data');
                    $beanstalkd->putInTube($tube, json_encode($data));
                    unlink($file);
                } catch (\Exception $e) {
                    LogHelper::write($e->getMessage() . "\n" . $e->getTraceAsString(), LogHelper::ERROR);
                }
            }
        }
    }
}
