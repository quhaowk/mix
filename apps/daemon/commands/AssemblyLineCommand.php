<?php

namespace apps\daemon\commands;

use mix\console\ExitCode;
use mix\facades\Input;
use mix\task\CenterProcess;
use mix\task\LeftProcess;
use mix\task\RightProcess;
use mix\task\TaskExecutor;

/**
 * 流水线模式范例
 * @author 刘健 <coder.liu@qq.com>
 */
class AssemblyLineCommand extends BaseCommand
{

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 获取程序名称
        $this->programName = Input::getCommandName();
        // 设置pidfile
        $this->pidFile = "/var/run/{$this->programName}.pid";
    }

    /**
     * 获取服务
     * @return TaskExecutor
     */
    public function getTaskService()
    {
        return create_object(
            [
                // 类路径
                'class'         => 'mix\task\TaskExecutor',
                // 服务名称
                'name'          => "mix-daemon: {$this->programName}",
                // 执行类型
                'type'          => \mix\task\TaskExecutor::TYPE_DAEMON,
                // 执行模式
                'mode'          => \mix\task\TaskExecutor::MODE_ASSEMBLY_LINE,
                // 左进程数
                'leftProcess'   => 1,
                // 中进程数
                'centerProcess' => 5,
                // 右进程数
                'rightProcess'  => 1,
                // POP退出等待时间 (秒)
                'popExitWait'   => 3,
            ]
        );
    }

    // 启动
    public function actionStart()
    {
        // 预处理
        if (!parent::actionStart()) {
            return ExitCode::UNSPECIFIED_ERROR;
        }
        // 启动服务
        $service = $this->getTaskService();
        $service->on('LeftStart', [$this, 'onLeftStart']);
        $service->on('CenterStart', [$this, 'onCenterStart']);
        $service->on('RightStart', [$this, 'onRightStart']);
        $service->start();
        // 返回退出码
        return ExitCode::OK;
    }

    // 左进程启动事件回调函数
    public function onLeftStart(LeftProcess $worker)
    {
        try {
            // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
            $queueModel = new \apps\common\models\QueueModel();
            // 保持任务执行状态，循环结束后当前进程会退出，主进程会重启一个新进程继续执行任务，这样做是为了避免长时间执行内存溢出
            for ($j = 0; $j < 16000; $j++) {
                // 从消息队列中间件阻塞获取一条消息
                $data = $queueModel->pop();
                // 将消息推送给中进程去处理，push有长度限制 (https://wiki.swoole.com/wiki/page/290.html)
                $worker->push($data);
            }
        } catch (\Exception $e) {
            // 休息一会，避免 cpu 出现 100%
            sleep(1);
            // 抛出错误
            throw $e;
        }
    }

    // 中进程启动事件回调函数
    public function onCenterStart(CenterProcess $worker)
    {
        // 保持任务执行状态，循环结束后当前进程会退出，主进程会重启一个新进程继续执行任务，这样做是为了避免长时间执行内存溢出
        for ($j = 0; $j < 16000; $j++) {
            $data = $worker->pop();
            if (empty($data)) {
                continue;
            }
            try {
                // 对消息进行处理，比如：IP转换，经纬度转换等
                // ...
                // 将处理完成的消息推送给右进程去处理，push有长度限制 (https://wiki.swoole.com/wiki/page/290.html)
                $worker->push($data);
            } catch (\Exception $e) {
                // 回退数据到消息队列
                $worker->rollback($data);
                // 休息一会，避免 cpu 出现 100%
                sleep(1);
                // 抛出错误
                throw $e;
            }
        }
    }

    // 右进程启动事件回调函数
    public function onRightStart(RightProcess $worker)
    {
        // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
        $tableModel = new \apps\common\models\TableModel();
        // 保持任务执行状态，循环结束后当前进程会退出，主进程会重启一个新进程继续执行任务，这样做是为了避免长时间执行内存溢出
        for ($j = 0; $j < 16000; $j++) {
            // 从进程队列中抢占一条消息
            $data = $worker->pop();
            if (empty($data)) {
                continue;
            }
            try {
                // 将处理完成的消息存入数据库
                // ...
            } catch (\Exception $e) {
                // 回退数据到消息队列
                $worker->rollback($data);
                // 休息一会，避免 cpu 出现 100%
                sleep(1);
                // 抛出错误
                throw $e;
            }
        }
    }

}
