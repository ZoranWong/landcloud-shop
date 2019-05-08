<?php

namespace app\service\excel;

interface Excelable
{
    public static function excelHeader();

    public static function exportHeader();
}