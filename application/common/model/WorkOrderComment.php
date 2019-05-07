<?php

namespace app\common\model;

use app\common\model\Manage as ManageModel;

class WorkOrderComment extends Common
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'ctime';

    protected $type = [
        'is_reply' => 'boolean'
    ];

    public function manage()
    {
        return $this->belongsTo(ManageModel::class, 'manage_id', 'id');
    }
}