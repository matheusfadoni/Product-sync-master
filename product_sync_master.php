<?php
/*
Plugin Name: Product Sync Master
Description: Plugin para sincronizar fotos de produtos entre sites WooCommerce baseado no SKU. Versão inicial, simples e estável.
Version: 0.4.1
Author: Matheus 
*/

// Verificação do WooCommerce ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('WooCommerce precisa estar ativo para este plugin funcionar.');
}

//Import log functions
require_once plugin_dir_path(__FILE__) . 'includes' . DIRECTORY_SEPARATOR . 'log_functions.php';

// Inclui o arquivo de funções de sincronização
require_once plugin_dir_path(__FILE__) . 'includes/sync_img_product.php';

// Hook para capturar nome da imagem, se tiver imagem relacionada ao produto.
add_action('pre_post_update', 'capture_image_name_before_update', 10, 2);

// Hook para atualizar imagem ao salvar produto
add_action('save_post_product', 'sync_on_product_save', 10, 3);

// Adiciona o menu do plugin no painel administrativo
add_action('admin_menu', 'add_admin_menu');

function add_admin_menu() {
    add_menu_page(
        'Configurações do Product Sync Master', // Título da página
        'Product Sync Master',                  // Nome do menu
        'manage_options',                 // Permissão necessária
        'product_sync_master',                  // Slug da página
        'settings_page',    // Função de callback para renderizar a página
        'dashicons-update'
    );
}

function settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações do Product Sync Master</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('psm_options_group');
                do_settings_sections('product_sync_master');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

function username_render() {
    $username = get_option('psm_username');
    echo "<input type='text' name='psm_username' value='" . esc_attr($username) . "' />";
}

function password_render() {
    $password = get_option('psm_password');
    echo "<input type='password' name='psm_password' value='" . esc_attr($password) . "' />";
}

function url_render() {
    $url = get_option('psm_url');
    echo "<input type='url' name='psm_url' value='" . esc_attr($url) . "' />";
}

// Inicializa as configurações
add_action('admin_init', 'settings_init');

function settings_init() {
    register_setting('psm_options_group', 'psm_username', 'sanitize_text_field');
    register_setting('psm_options_group', 'psm_password', 'sanitize_text_field');
    register_setting('psm_options_group', 'psm_url', 'esc_url_raw');

    add_settings_section('psm_section', 'Credenciais do Site Remoto', null, 'product_sync_master');

    add_settings_field('psm_username', 'Nome de Usuário', 'username_render', 'product_sync_master', 'psm_section');
    add_settings_field('psm_password', 'Chave da API', 'password_render', 'product_sync_master', 'psm_section');
    add_settings_field('psm_url', 'URL do Site Remoto', 'url_render', 'product_sync_master', 'psm_section');
}

