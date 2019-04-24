<?php

namespace app\service\excel;

use app\common\model\Ietask as IeTaskModel;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadSheetException;
use think\Exception;
use think\facade\Log;
use think\queue\Job;

abstract class BaseHandler
{
    abstract public function model();

    abstract public function parseToModel(array $importData);

    public function getHeader()
    {
        return ($this->model())::excelHeader();
    }

    public function extractData(array $fields, array $sheetData)
    {
        $importData = [];
        if ($fields) {
            $i = 0;
            foreach ($sheetData as $item) {
                foreach ($fields as $key => $field) {
                    $importData[$i][$field['value']] = $item[$field['index']];
                }
                $i++;
            }
        }
        return $importData;
    }

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

    /**
     * @param Job|null $job
     * @param $params
     */
    public function handle(Job $job, $params)
    {
        Log::record($params);
        $ieTaskModel = new IeTaskModel();

        try {
            $file = json_decode($params['params'], true);

            $sheetData = importDataFromExcel($file['file_path']);

            $sheetHeader = $sheetData[0];
            unset($sheetData[0]);

            $fields = $this->extractFields($sheetHeader);

            $importData = $this->extractData($fields, $sheetData);

            $errorMessages = $this->parseToModel($importData);

            $uData['status'] = $ieTaskModel::IMPORT_SUCCESS_STATUS;
            $uData['message'] = '导入成功';
            if ($errorMessages) {
                $uData['message'] .= json_encode($errorMessages);
            }
            $uData['utime'] = time();
            $ieTaskModel->update($uData, ['id' => $params['task_id']]);

        } catch (PhpSpreadSheetException $exception) {
            Log::error('解析文件错误：', $exception->getTrace());
        } catch (Exception $exception) {
            Log::error('数据导入发生错误', $exception->getTrace());
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