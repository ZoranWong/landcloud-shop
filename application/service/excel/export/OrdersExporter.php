<?php
/**
 * Created by PhpStorm.
 * User: wang
 * Date: 2019-07-19
 * Time: 23:01
 */

namespace app\service\excel\export;


use app\common\model\Order;
use app\service\excel\BaseGenerator;

class OrdersExporter extends BaseGenerator
{

    public function model(): string
    {
        // TODO: Implement model() method.
        return Order::class;
    }

    public function fileName(): string
    {
        // TODO: Implement fileName() method.
        return '订单表';
    }

    public function getExportData($params): array
    {
        // TODO: Implement getExportData() method.
        $filter = json_decode($params, true);

        if (isset($filter['ids'])) {
            if ($filter['ids']) {
                $filter['id'] = ['in', $filter['ids']];
            }
            unset($filter['ids']);
        }

        $result = [
            'status' => true,
            'data' => [],
            'msg' => ''
        ];

        $order = new Order();
        $ordersResult = $order->tableData($filter);
        $result['data'] = $ordersResult['data'];
        return $result;
    }
}