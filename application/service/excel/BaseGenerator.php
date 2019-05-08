<?php

namespace app\service\excel;

use app\common\model\Ietask;
use app\common\model\Ietask as IeTaskModel;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\queue\Job;

abstract class BaseGenerator
{
    const START_COLUMN = 65;//字母A
    const START_ROW = 1;

    public $basePath = ROOT_PATH . 'public' . DS . 'static' . DS . 'file';// 文件存放根目录

    abstract public function model(): string;

    abstract public function fileName(): string;

    public function headers()
    {
        $excelHeaders = ($this->model())::excelHeader();

        $headers = [];
        $keys = [];
        foreach ($excelHeaders as $header) {
            $headers[] = $header['desc'];
            $keys[] = $header['id'];
        }

        return [$headers, $keys];
    }

    public function generate($data = [])
    {
        list($headers, $keys) = $this->headers();

        $count = count($headers);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();

        array_unshift($data, $headers);
        if (!is_null($data)) {
            if (!is_iterable($data)) {
                $data = [$data];
            }

            foreach ($data as $key => $item) {             //循环设置单元格：
                for ($i = 0; $i < $count; $i++) {     //数字转字母从65开始：
                    $column = strtoupper(chr($i + self::START_COLUMN));
                    $sheet->setCellValue($column . ($key + self::START_ROW), isset($item[$keys[$i]]) ? $item[$keys[$i]] : $item[$i]);
                    $spreadSheet->getActiveSheet()->getColumnDimension($column)->setWidth(20); //固定列宽
                }

            }
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $this->fileName() . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadSheet);
        $writer->save('php://output');

        //删除清空：
        $spreadSheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

    public function exportHeaders()
    {
        $exportHeaders = ($this->model())::exportHeader();

        $headers = [];
        $keys = [];
        foreach ($exportHeaders as $header) {
            $headers[] = $header['desc'];
            $keys[] = ['field' => $header['field'], 'type' => $header['type']];
        }

        return [$headers, $keys];
    }

    abstract public function getExportData($params): array;

    public function export(Job $job, $params)
    {
        $ieTaskModel = new IeTaskModel();
        try {
            $type = $params['type'];

            list($headers, $keys) = $this->exportHeaders();

            $count = count($headers);

            $spreadSheet = new Spreadsheet();
            $sheet = $spreadSheet->getActiveSheet();

            $exportData = $this->getExportData($params['exportData']['params']);

            if ($exportData['status']) {
                $exportData = $exportData['data'];
                array_unshift($exportData, $headers);

                foreach ($exportData as $key => $item) {
                    for ($i = 0; $i < $count; $i++) {     //数字转字母从65开始：
                        $column = strtoupper(chr($i + self::START_COLUMN));
                        $sheet->setCellValue($column . ($key + self::START_ROW), isset($item[$keys[$i]['field']]) ? $item[$keys[$i]['field']] : $item[$i]);
                        $spreadSheet->getActiveSheet()->getColumnDimension($column)->setWidth(20); //固定列宽
                    }
                }

                $writer = new Xlsx($spreadSheet);
                $fileName = $type . "-xlsx-" . date("YmdHis", time()) . ".xlsx";
                $filePath = $this->basePath . get_hash_dir($fileName);
                $fullFilePath = $filePath . $fileName;
                $this->checkPath($filePath);
                $writer->save($fullFilePath);
                $fileSize = getRealSize(filesize($fullFilePath));

                $spreadSheet->disconnectWorksheets();
                unset($spreadSheet);

                $uData['file_name'] = $fileName;
                $uData['file_size'] = $fileSize;
                $uData['file_path'] = $fullFilePath;
                $uData['status'] = IeTaskModel::EXPORT_SUCCESS_STATUS;
                $uData['utime'] = time();
                $ieTaskModel->update($uData, ['id' => $params['exportData']['task_id']]);
            } else {
                $uData['status'] = IeTaskModel::EXPORT_FAIL_STATUS;
                $uData['message'] = $exportData['msg'];
                $uData['utime'] = time();
                $ieTaskModel->update($uData, ['id' => $params['exportData']['task_id']]);
            }

            $job->delete();
        } catch (Exception $exception) {
            $uData['status'] = IeTaskModel::EXPORT_FAIL_STATUS;
            $uData['message'] = $exception->getMessage();
            $uData['utime'] = time();
            $ieTaskModel->update($uData, ['id' => $params['exportData']['task_id']]);
        }

        if ($job->attempts() > 3) {
            $uData['status'] = IeTaskModel::EXPORT_FAIL_STATUS;
            $uData['message'] = '导出执行失败';
            $uData['utime'] = time();
            $ieTaskModel->update($uData, ['id' => $params['exportData']['task_id']]);
            $job->delete();
        }
    }

    private function checkPath($path)
    {
        if (!is_dir($path)) {
            @mkdirs($path, 0777, true);
        }
    }
}