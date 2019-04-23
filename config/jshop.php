<?php

return [
    'default_image' => 'https://b2c.jihainet.com/static/images/default.png',
    'upload_path' => ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'uploads',
    //上传文件限制5M
    'upload_filesize' => 5242880,
    //分页默认数量
    'page_limit' => 10,
    //售后，评论等上传图片数量限制
    'image_max' => 5,
    //商品导入模板
    'product_import_template' => ROOT_PATH . 'public' . DS . 'static' . DS . 'template' . DS . 'excel' . DS . 'products_import.xls',
    // 用户导入模板
    'user_import_template' => ROOT_PATH . 'public' . DS . 'static' . DS . 'template' . DS . 'excel' . DS . 'users_import.xls',
    // 用户收货地址导入模板
    'user_address_import_template' => ROOT_PATH . 'public' . DS . 'static' . DS . 'template' . DS . 'excel' . DS . 'users_address_import.xls',
    // 管理者导入模板
    'manager_import_template' => ROOT_PATH . 'public' . DS . 'static' . DS . 'template' . DS . 'excel' . DS . 'managers_import.xls',
    // 商品价格规格导入模板
    'product_price_levels_import' => ROOT_PATH . 'public' . DS . 'static' . DS . 'template' . DS . 'excel' . DS . 'product_price_level_import.xls',
    //快递查询配置参数
    'api_express' => [
        'key' => '',
        'customer' => ''
    ],

    'login_fail_num' => 3,       //登陆失败次数，如果每天登陆失败次数超过次数字，就会显示图片验证码
    'manage_login_fail_num' => 5,       //管理员登陆失败次数，如果超过这个次数，会显示图片验证码，防止暴力破解
    'tocash_money_low' => '100',       //最低提现金额
    'authorization_url' => 'https://jshop.jihainet.com', //授权查询地址
    'product' => 'Jshop-b2c标准版',//产品名称
    'version' => 'v2.0.1',    //版本号
    'sms_password' => '',  //短信密码，会覆盖项目配置里的此参数，为了保密密码
    'image_storage' => [
        'type' => 'Local'
    ],
    'file_size' => '104857600',//100M
    'area_list' => ROOT_PATH . 'public/static/area.json',//地址库信息地址
];