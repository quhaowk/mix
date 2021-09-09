# Swoole Coroutine

[Swoole](https://www.swoole.com/) 驱动，该模式为单进程协程模型，非常适合编写 `WebSocket`、`gRPC` 服务。

## 入口文件

骨架路径 `bin/swooleco.php`

```php
#!/usr/bin/env php
<?php
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('memory_limit', '1G');

require __DIR__ . '/../vendor/autoload.php';

use App\Container\Logger;
use App\Vega;
use Dotenv\Dotenv;

Dotenv::createUnsafeImmutable(__DIR__ . '/../', '.env')->load();
define("APP_DEBUG", env('APP_DEBUG'));

App\Error::register();

Swoole\Coroutine\run(function () {
    $vega = Vega::new();
    $host = '0.0.0.0';
    $port = 9502;
    $server = new Swoole\Coroutine\Http\Server($host, $port, false, false);
    $server->handle('/', $vega->handler());

    App\Container\DB::enableCoroutine();
    App\Container\RDS::enableCoroutine();

    foreach ([SIGHUP, SIGINT, SIGTERM] as $signal) {
        Swoole\Process::signal($signal, function () use ($server) {
            Logger::instance()->info('Shutdown swoole coroutine server');
            $server->shutdown();
        });
    }

    echo <<<EOL
                              ____
 ______ ___ _____ ___   _____  / /_ _____
  / __ `__ \/ /\ \/ /__ / __ \/ __ \/ __ \
 / / / / / / / /\ \/ _ / /_/ / / / / /_/ /
/_/ /_/ /_/_/ /_/\_\  / .___/_/ /_/ .___/
                     /_/         /_/


EOL;
    printf("System    Name:       %s\n", strtolower(PHP_OS));
    printf("PHP       Version:    %s\n", PHP_VERSION);
    printf("Swoole    Version:    %s\n", swoole_version());
    printf("Listen    Addr:       http://%s:%d\n", $host, $port);
    Logger::instance()->info('Start swoole coroutine server');

    $server->start();
});
```

## 启动服务

```
php bin/swooleco.php
```