<?php

use think\migration\Migrator;
use think\migration\db\Column;

class VisitCount extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $this->table('visit_product_count', ['engine' => 'MyISAM'])->setComment('访问产品统计表单')
            ->addColumn(Column::unsignedInteger('user_id')->setDefault(0)->setComment('用户ID'))
            ->addColumn(Column::unsignedInteger('ip')->setDefault(0)->setComment('ip转化程int'))
            ->addColumn(Column::unsignedInteger('product_id')->setDefault(0)->setComment('产品ID'))
            ->addColumn(Column::timestamp('date')->setComment('访问时间'))
            ->addColumn(Column::unsignedInteger('area_code')->setDefault(0)->setComment('区域编码'))
            ->create();
    }
}
