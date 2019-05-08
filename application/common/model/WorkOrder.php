<?php

namespace app\common\model;

use app\common\model\User as UserModel;
use app\common\model\WorkOrderComment as WorkOrderCommentModel;

class WorkOrder extends Common
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'ctime';

    const TYPE_CONSULT = 'consult';// 工单类型-咨询
    const TYPE_COMPLAIN = 'complain';// 工单类型-投诉

    const TYPE_CONSULT_NUM = 1;
    const TYP_COMPLAIN_NUM = 2;

    const TYPE_CONSULT_ZN = '咨询';
    const TYPE_COMPLAIN_ZN = '投诉';

    const TYPES = [
        self::TYPE_CONSULT => self::TYPE_CONSULT_NUM,
        self::TYPE_COMPLAIN => self::TYP_COMPLAIN_NUM
    ];

    const STATUS_INIT = 1;// 发起
    const STATUS_PROCESSING = 2;// 跟单中
    const STATUS_PROCESSED = 3;// 工单结束
    const STATUS_TIMEOUT_PROCESSED = 4;// 工单结束-用户三天内未回复

    // 工单跟单
    public function comments()
    {
        return $this->hasMany(WorkOrderCommentModel::class, 'work_order_id', 'id');
    }

    // 发起人
    public function initer()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }

    public function commentsNotReply()
    {
        return $this->comments()->where(['is_reply' => 0]);
    }
}