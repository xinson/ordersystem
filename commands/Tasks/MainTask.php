<?php
namespace Commands\Tasks;

use Commands\BaseTask;

class MainTask extends BaseTask
{
    public function mainAction()
    {
        echo "\nUsage:\n\n";
        echo "1. php tool update [ 更新数据库 ] \n\n";
        echo "2. php tool cron [ 定时执行 ] \n\n";
        echo "3. php tool service [ 服务模式 ] \n\n";
        echo "4. php tool supervisor [ supervisor监控 ] \n\n";
        echo "5. php tool storage [ 缓存管理 ] \n\n";
    }
}
