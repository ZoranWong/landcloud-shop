<?php

namespace app\service\excel;

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

    public function handle(Job $job, $params)
    {
        Log::record($params);

        try {
            $file = json_decode($params['params'], true);

            $sheetData = importDataFromExcel($file['file_path']);

            $sheetHeader = $sheetData[0];
            unset($sheetData[0]);

            $fields = $this->extractFields($sheetHeader);

            $this->extractData($fields, $sheetData);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $exception) {
            Log::error('解析文件错误：', $exception->getTrace());
        }
    }
}