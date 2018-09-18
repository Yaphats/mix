<?php

namespace apps\console\commands;

use mix\client\PDOPersistent;
use mix\console\ExitCode;
use mix\facades\Input;
use mix\task\CenterWorker;
use mix\task\LeftWorker;
use mix\task\ProcessPoolTaskExecutor;
use mix\task\RightWorker;

/**
 * 流水线模式范例
 * @author 刘健 <coder.liu@qq.com>
 */
class AssemblyLineCommand extends BaseCommand
{

    /**
     * @var \mix\client\PDOPersistent
     */
    public $pdo;

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 获取程序名称
        $this->programName = Input::getCommandName();
    }

    /**
     * 获取服务
     * @return ProcessPoolTaskExecutor
     */
    public function getTaskService()
    {
        return create_object(
            [
                // 类路径
                'class'         => 'mix\task\ProcessPoolTaskExecutor',
                // 服务名称
                'name'          => "mix-daemon: {$this->programName}",
                // 执行模式
                'mode'          => ProcessPoolTaskExecutor::MODE_ASSEMBLY_LINE,
                // 左进程数
                'leftProcess'   => 1,
                // 中进程数
                'centerProcess' => 5,
                // 右进程数
                'rightProcess'  => 1,
                // 最大执行次数
                'maxExecutions' => 16000,
                // 队列名称
                'queueName'     => __FILE__,
                // 临时文件目录，当消息长度超过8K时会启用临时文件来保存，建议使用tmpfs文件系统提升性能
                'tempDir'       => '/dev/shm',
            ]
        );
    }

    // 执行任务
    public function actionExec()
    {
        // 预处理
        parent::actionExec();
        // 启动服务
        $service = $this->getTaskService();
        $service->on('LeftStart', [$this, 'onLeftStart']);
        $service->on('CenterStart', [$this, 'onCenterStart']);
        $service->on('CenterMessage', [$this, 'onCenterMessage']);
        $service->on('RightStart', [$this, 'onRightStart']);
        $service->on('RightMessage', [$this, 'onRightMessage']);
        $service->start();
        // 返回退出码
        return ExitCode::OK;
    }

    // 左进程启动事件
    public function onLeftStart(LeftWorker $worker)
    {
        // 使用长连接客户端，这样会自动帮你维护连接不断线
        $pdo    = PDOPersistent::newInstanceByConfig('libraries.[persistent.pdo]');
        $result = $pdo->createCommand("SELECT * FROM `table`")->queryAll();
        // 取出全量数据一行一行推送给中进程去处理
        foreach ($result as $item) {
            // 将消息发送给中进程去处理
            $worker->send($item);
        }
    }

    // 中进程启动事件
    public function onCenterStart(CenterWorker $worker)
    {
        // 可以在这里实例化一些对象，供 onCenterMessage 中使用，这样就不需要重复实例化。
    }

    // 中进程消息事件
    public function onCenterMessage(CenterWorker $worker, $data)
    {
        // 对消息进行处理，比如：IP转换，经纬度转换等
        // ...
        // 将处理完成的消息发送给右进程去处理
        $worker->send($data);
    }

    // 右进程启动事件
    public function onRightStart(RightWorker $worker)
    {
        // 可以在这里实例化一些对象，供 onRightMessage 中使用，这样就不需要重复实例化。
        $this->pdo = PDOPersistent::newInstanceByConfig('libraries.[persistent.pdo]');
    }

    // 右进程消息事件
    public function onRightMessage(RightWorker $worker, $data)
    {
        // 将处理完成的消息存入数据库
        $this->pdo->insert('table', $data);
    }

}