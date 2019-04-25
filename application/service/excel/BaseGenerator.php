<?php

namespace app\service\excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

abstract class BaseGenerator
{
    const START_COLUMN = (int)'A';
    const START_ROW = 1;
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

    public function generate($data = null)
    {
        list($headers, $keys) = $this->headers();

        $count = count($headers);

        $spreadSheet = new Spreadsheet();
        $sheet = $spreadSheet->getActiveSheet();

        if (!is_null($data)) {
            if (!is_iterable($data)) {
                $data = [$data];
            }
            array_unshift( $data, $headers);
            foreach ($data as $key => $item) {             //循环设置单元格：
                for ($i = 0; $i < $count; $i++) {     //数字转字母从65开始：
                    $column = strtoupper(chr($i + self::START_COLUMN));
                    $sheet->setCellValue($column . ($key + self::START_ROW), $item[$keys[$i]]);
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