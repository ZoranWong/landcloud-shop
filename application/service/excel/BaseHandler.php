<?php

namespace app\service\excel;

use app\common\model\Ietask as IeTaskModel;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadSheetException;
use think\facade\Log;
use think\queue\Job;

abstract class BaseHandler
{
    abstract public function model();

    abstract public function getHeader();

    abstract public function extractData(array $fields, array $sheetData);

    public function extractFields(array $sheetHeader)
    {
        $header = $this->getHeader();
        $fields = [];
        foreach ($header as $item) {
            $index = array_search($item['desc'], $sheetHeader);
            if ($index >= 0 && !is_bool($index)) {
                $fields[] = [
                    'index' => $index,
                    'value' => $item['id']
                ];
            }
        }
        return $fields;
    }

    public function handle(Job $job = null, $params)
    {
        Log::record($params);
        $ieTaskModel = new IeTaskModel();

        try {
            $file = json_decode($params['params'], true);

            $sheetData = importDataFromExcel($file['file_path']);

            $sheetHeader = $sheetData[0];
            unset($sheetData[0]);

            $fields = $this->extractFields($sheetHeader);

            $errorMessages = $this->extractData($fields, $sheetData);

            $uData['status'] = $ieTaskModel::IMPORT_SUCCESS_STATUS;
            $uData['message'] = '导入成功';
            if ($errorMessages) {
                $uData['message'] .= json_encode($errorMessages);
            }
            $uData['utime'] = time();
            $ieTaskModel->update($uData, ['id' => $params['task_id']]);

        } catch (PhpSpreadSheetException $exception) {
            Log::error('解析文件错误：', $exception->getTrace());
        }

        if ($job->attempts() > 3) {
            $uData['status'] = $ieTaskModel::IMPORT_FAIL_STATUS;
            $uData['message'] = '导入执行失败';
            $uData['utime'] = time();
            $ieTaskModel->update($uData, ['id' => $params['task_id']]);
            $job->delete();
        }
    }
}