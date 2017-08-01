<?php

function alipay_config($version) {
    if(!empty($version))
        $version = '_'.strtoupper($version);
    //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓
    //合作身份者id，以2088开头的16位纯数字
    $alipay_config['partner'] = C('ALIPAY'.$version.'.PID');

    //支付请求信息
    $alipay_config['service'] = 'mobile.securitypay.pay';

    //支付宝异步回调
    $alipay_config['notify_url'] = C('ALIPAY'.$version.'.NOTIFY_URL');

    //商户的私钥（后缀是.pen）文件相对路径
    $alipay_config['private_key_path'] = VENDOR_PATH . 'Alipay/key/rsa_private_key.pem';

    //支付宝公钥（后缀是.pen）文件相对路径
    $alipay_config['ali_public_key_path'] = VENDOR_PATH . 'Alipay/key/alipay_public_key.pem';

    //支付类型
    $alipay_config['payment_type'] = 1;


    //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
    //签名方式 不需修改
    $alipay_config['sign_type'] = strtoupper('RSA');

    //字符编码格式 目前支持 gbk 或 utf-8
    $alipay_config['input_charset'] = strtolower('utf-8');

    //ca证书路径地址，用于curl中ssl校验
    //请保证cacert.pem文件在当前文件夹目录中
    $alipay_config['cacert'] = getcwd() . '\\cacert.pem';

    //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
    $alipay_config['transport'] = 'http';

    return $alipay_config;
}
