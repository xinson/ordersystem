<?php
namespace Commands\Tasks;

use Commands\BaseTask;
use Common\Library\LogHelper;
use Common\Library\Cron\TdCron;
use Common\Models\Cron;

class CronTask extends BaseTask
{
    protected $initializedJobs;
    protected $jobs;
    protected $now;

    public function mainAction()
    {
        $this->now = strtotime(date('Y-n-j H:i'));
        $this->cron();
    }


    public function cron()
    {
        $db = $this->getDB();
        restore_error_handler();
        restore_exception_handler();
        $this->initializedJobs = array();
        $jobs = $db->fetchAll("select * from cron where status = 'initialized'");

        /**
         * @var $cron Cron
         * 已存在 cron (initialized 状态)
         */
        if ($jobs) {
            $cron = new Cron();
            foreach ($jobs as $data) {
                $cron->setData($data);
                $this->initializedJobs[$data['name']] = $cron;
            }
        }

        /**
         * 新 cron
         */
        foreach ($this->getCronJobs() as $name => $cronJob) {
            if (isset($cronJob['expression'])) {
                $expression = $cronJob['expression'];
            } else {
                LogHelper::write('Cron expression is required for cron job "' . $name . '"', LogHelper::WARNING);
                continue;
            }
            if ($this->now != TdCron::getNextOccurrence($expression, $this->now)) {
                continue;
            }
            $cronJob['name'] = $name;
            $cron = isset($this->initializedJobs[$name]) ? $this->initializedJobs[$name] : $this->initializedJobs[$name] = new Cron();
            $cron->cronInitialize((array)$cronJob);
        }

        /* @var $cron Cron 处理 */
        foreach ($this->initializedJobs as $cron) {
            $cron->run();
        }

    }


    /**
     * Get All Defined Cron Jobs
     * 获取配置
     * @return array
     */
    public function getCronJobs()
    {
        if ($this->jobs === null) {
            $this->jobs = (array)$this->getConfig('cron.cron');
        }
        return $this->jobs;
    }
}
