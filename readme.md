## 订单系统

## pay
本项目是新版支付系统，使用phalcon扩展框架。
尽管Phalcon并不直接依赖其他PHP扩展，但还是使用了一部分扩展用于提供某些功能。所使用的扩展如下:

    mbstring
    mcrypt
    openssl
    PDO
    PDO/Mysql


## 1.1 安装phalcon扩展 ( 暂时只支持 php5.* 版本, php7 暂时未支持 )

    https://docs.phalconphp.com/zh/latest/reference/install.html#linux-solaris-mac (官方)

## 1.2 重启 php-fpm：
```bash
service php5-fpm restart
```

## 2.在根目录新建 `storage` 赋予777权限


## 3.在根目录运行 `composer install --no-dev` 和  `composer update --no-dev` ; 当编写了新的类文件时, 需要运行 `composer dump-autoload` 来确保类能自动加载


## 4.生产环境新建 `app/common/config/production` production目录覆盖config对应文件的配置


## 5.配置crontab计划任务(在根目录运行)
###执行 执行程序的计划任务

    php tool Cron

###执行 每天定期清除缓存

    php tool Storage clear


## 6.配置Supervisor,执行程序的任务(在根目录运行)

###执行 异步回调CP信息(注意cron.php配置CRON_ON为true 才能正常跑)

    php tool Supervisor ordercallback

###执行 处理管道coupon_event

    php tool Supervisor couponevent


## 7.更新数据库结构(在根目录运行)

###更新版本(更新数据库结构)

    php tool Update


## 8.app/common/config/production 配置文件详解

### 应用配置application.php
    return array(
        'debug' => true,
    );

### 数据库配置database.php
    return array(
        'adapter'     => 'Mysql',  //数据库类型
        'host'        => '',  //数据库地址
        'username'    => '', //数据库用户名
        'password'    => '',//数据库密码
        'dbname'      => '',//数据库表
        'charset'     => 'utf8',//数据库编码
    );

### 计划任务cron.php 开启异步回调
    return array(
        'CRON_ON' => true,
    );

### 队列配置queue.php
    return array(
        'beanstalk' => array(
            'enable' => true, //是否开启队列
            'host' => '', //队列IP
            'port' => '' //队列端口
        )
    );

### 支付配置payment.php
        'alipay' => array(
            'partner' => '',  //合作身份者id，以2088开头的16位纯数字
            'private_key_path' => '', //商户的私钥（后缀是.pen）文件相对路径
            'ali_public_key_path' => '', //支付宝公钥（后缀是.pen）文件相对路径
            'sign_type' => '',   //签名方式 不需修改
            'input_charset' => '',  // //字符编码格式 目前支持 gbk 或 utf-8
            'cacert' => '',  //ca证书路径地址，用于curl中ssl校验 请保证cacert.pem文件在当前文件夹目录中
            'transport' => '', //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
            'notify_url' => 'api/alipay/notifyAction',  //异步通知地址
            'return_url' => 'api/alipay/returnAction',  //页面跳转 同步通知 可空
            'payment_type' => 1  //支付类型 仅支持商品购买(1)
        ),

## 9.服务模式

### 9.1 Swoole 扩展安装
可以用 PECL 安装，或者直接从源代码编译安装，参考：[Swoole 安装手册](http://wiki.swoole.com/wiki/page/6.html)。
```bash
apt-get install php5-dev
pecl install swoole
```
安装成功后会生成 swoole.so，我们需要把它加载到 PHP 中：

```bash
vim /etc/php5/mods-available/swoole.ini
```

```ini
extension=swoole.so
```

```bash
ln -s /etc/php5/mods-available/swoole.ini /etc/php5/cli/conf.d/20-swoole.ini
ln -s /etc/php5/mods-available/swoole.ini /etc/php5/fpm/conf.d/20-swoole.ini
```

重启 php-fpm：
```bash
service php5-fpm restart
```

### 9.2 服务模式配置service.php
          return array(
              'debug' => true,
              'user' => 'vagrant',
              'group' => 'vagrant',
              'worker_num' => 16,
              'daemonize' => 1,
              'max_request' => 10000,
              'backlog' => 8192
          );

#### 9.3 Nginx 流量转发
参考 [4. Nginx相关：Fast CGI 参数](#4-nginx-fast-cgi)，把 `USE_SERVICE` 设为 1：
```conf
fastcgi_param USE_SERVICE 1;
```
重载 Nginx 配置：
```bash
nginx -t
nginx -s reload
```

### 9.4 启用服务模式

#### 9.4.1 安装 Linux 服务
此操作需要在所有 PHP 服务器上执行。
```bash
cd /path/to/pay
chmod 755 tool
sudo php tool Service install
```
此操作会在 /etc/init.d/ 目录下创建 pay 文件。

安装完成后请确保服务会在系统启动后自动运行。

若需卸载服务，则执行：
```bash
sudo php tool Service uninstall
```

#### 9.4.2 服务操作命令
```bash
service pay start|reload|restart|stop|status
```


## 10.单元测试功能(在本地开发配置,生产环境不配置) ,并且需关闭服务模式.

###根目录运行 `composer update --dev` 和 `composer dump-autoload`

###配置应用 app/common/config/production/application.php 开启debug模式

###配置数据库 app/test/config/production/database.php

###配置根URl tests/config/production/application.php

###配置数据库 tests/config/production/database.php

