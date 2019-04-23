<?php

namespace app\service\excel\handler;

use app\common\model\User;
use app\common\model\User as UserModel;
use app\service\excel\BaseHandler;

class UserHandler extends BaseHandler
{
    public function model()
    {
        return UserModel::class;
    }

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
                    $importData[$i++][$field['value']] = $item[$field['index']];
                }
            }
        }

        foreach ($importData as $record) {
            $user['username'] = $record['username'];
//            $user['password'] = encrypt();
        }
    }
}