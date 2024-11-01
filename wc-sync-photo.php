<?php
/*
Plugin Name: Wc Sync Photo
Description: Plugin para sincronizar fotos de produtos entre sites WooCommerce baseado no SKU. Versao inicial, simples e estável.
Version: 0.1
Author: Matheus 
*/

// Verificação do WooCommerce ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('WooCommerce precisa estar ativo para este plugin funcionar.');
}

// Inclui o arquivo de funções de sincronização
require_once plugin_dir_path(__FILE__) . 'includes/sync-functions.php';
// Hook para capturar nome da imagem, se tiver imagem relacionada ao produto.
add_action('pre_post_update', 'capture_image_name_before_update', 10, 2);

// Hook para atualizar imagem ao salvar produto
add_action('save_post_product', 'wc_sync_on_product_save', 10, 3);

