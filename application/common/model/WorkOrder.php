<?php

namespace app\common\model;

class WorkOrder extends Common
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'ctime';

    const TYPE_CONSULT = 'consult';// 工单类型-咨询
    const TYPE_COMPLAIN = 'complain';// 工单类型-投诉

    const TYPES = [
        self::TYPE_CONSULT => 1,
        self::TYPE_COMPLAIN => 2
    ];

    const STATUS_INIT = 1;// 发起
    const STATUS_PROCESSING = 2;// 跟单中
    const STATUS_PROCESSED = 3;// 工单结束
    const STATUS_TIMEOUT_PROCESSED = 4;// 工单结束-用户三天内未回复

    public function comments()
    {
        return $this->hasMany(WorkOrderComment::class, 'work_order_id', 'id');
    }
}