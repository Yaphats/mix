<?php

namespace mix\swoole;

/**
 * 队列进程类
 * @author 刘健 <coder.liu@qq.com>
 */
class QueueProcess extends \swoole_process
{

    // 主进程pid
    public $mpid = 0;

    // 检查主进程
    public function checkMaster()
    {
        if (!\swoole_process::kill($this->mpid, 0)) {
            while ($this->statQueue()['queue_num'] > 0) {
            }
            $this->freeQueue();
            $this->exit();
        }
    }

    // 投递数据到消息队列中
    public function push($data)
    {
        parent::push(serialize($data));
    }

    // 从队列中提取数据
    public function pop($maxsize = 8192)
    {
        return unserialize(parent::pop($maxsize));
    }

}
