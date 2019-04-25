<?php

namespace app\service\excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

abstract class BaseGenerator
{
    const START_COLUMN = (int)'A';

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

    public function generate($data = null, $contentRowStart = 2)
    {
        list($headers, $keys) = $this->headers();

        $count = count($headers);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();

        for ($i = 0; $i < $count; $i++) {     //数字转字母从65开始，循环设置表头：
            $sheet->setCellValue(strtoupper(chr($i + self::START_COLUMN)) . '1', $headers[$i]);
        }

        if (!is_null($data)) {
            if (!is_iterable($data)) {
                $data = [$data];
            }

            foreach ($data as $key => $item) {             //循环设置单元格：
                //$key+2,因为第一行是表头，所以写到表格时   从第二行开始写
                for ($i = 0; $i < $count; $i++) {     //数字转字母从65开始：
                    $column = strtoupper(chr($i + self::START_COLUMN));
                    $sheet->setCellValue($column . ($key + $contentRowStart), $item[$keys[$i]]);
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
}