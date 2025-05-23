<?php
function _log_basic($message, $log_file = 'logs_general.log') {
    $log_dir = plugin_dir_path(__DIR__) . 'logs';

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    if (!file_exists($log_dir . DIRECTORY_SEPARATOR  . $log_file)) {
        file_put_contents($log_dir . DIRECTORY_SEPARATOR  . $log_file, '');
    }

    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_dir . DIRECTORY_SEPARATOR . $log_file, "[$timestamp] $message\n", FILE_APPEND);
    return;
}

function log_product_brand($message) {
    _log_basic($message, 'log_sync_product_brand.log');
}

function log_img_product($message) {
    _log_basic($message, 'log_sync_img_product.log');
}

function log_config_credentials($message) {
    _log_basic($message, 'log_config_credentials.log');
}
function log_product_crud_timestamps($post_ID, $created, $updated) {
    $msg = "Produto $post_ID | Criado em $created | Editado em $updated";
    _log_basic($msg, 'log_product_crud_timestamps.log');
}
