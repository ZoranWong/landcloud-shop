<?php

use app\service\Encryption\Encrypter;
use think\helper\Str;

bind('encrypter', function () {
    $key = config('app.key');
    $cipher = config('app.cipher');
    if (empty($key)) {
        throw new RuntimeException(
            'No application encryption key has been specified.'
        );
    }
    if (Str::startsWith($key, 'base64:')) {
        $key = base64_decode(substr($key, 7));
    }

    return new Encrypter($key, $cipher);
});