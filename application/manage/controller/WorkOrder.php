<?php

namespace app\manage\controller;

use app\common\controller\Manage;
use app\common\model\WorkOrder as WorkOrderModel;
use app\common\model\WorkOrderComment as WorkOrderModelComment;
use think\Exception;
use think\facade\Request;

class WorkOrder extends Manage
{
    public function index()
    {
        $workOrderModel = new WorkOrderModel();

        $limit = input('limit', PAGE_SIZE);

        $where = [];
        if (Request::has('type') && ($type = input('param.type'))) {
            $where['type'] = WorkOrderModel::TYPES[$type];
        }
        if (Request::has('status') && ($status = input('param.status'))) {
            $where['status'] = $status;
        }

        $paginator = $workOrderModel->with('comments,initer')->withCount('commentsNotReply')
            ->where($where)->order('ctime', 'desc')->paginate($limit);

        if (Request::isAjax()) {
            return ['code' => 0, 'msg' => '', 'count' => $paginator->count(), 'data' => $paginator->items()];
        }

        return $this->fetch();
    }

    public function process()
    {
        $workOrderModel = new WorkOrderModel();
        if (Request::isPost()) {
            /** @var WorkOrderModel $workOrder */
            try {
                $workOrderModel->startTrans();
                $workOrder = $workOrderModel->findOrFail(input('param.work_order_id/d'));
                $workOrder->save(['status' => WorkOrderModel::STATUS_PROCESSING]);
                $workOrder->comments()->save([
                    'content' => input('param.content'),
                    'manage_id' => session('manage')['id'],
                    'ctime' => time(),
                    'is_reply' => 1
                ]);
                WorkOrderModelComment::where(['work_order_id' => $workOrder->id])->update(['is_reply' => 1]);
                $workOrderModel->commit();
            } catch (Exception $exception) {
                $workOrderModel->rollback();
                return ['status' => 0, 'msg' => '跟单异常，请稍候重试'];
            }

            return ['status' => 1, 'msg' => '跟单成功'];
        }
        /** @var WorkOrderModel $workOrder */
        $workOrder = $workOrderModel->with('initer')->where('id', input('param.id/d'))->find();
        if (!$workOrder) {
            return error_code(10002);
        }
        $comments = $workOrder->comments()->with('manage')->select();
        return $this->fetch('process', [
            'comments' => $comments,
            'workOrder' => $workOrder
        ]);
    }

}