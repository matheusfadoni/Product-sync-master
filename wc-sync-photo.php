<?php
/*
Plugin Name: Wc Sync Photo
Description: Plugin para sincronizar fotos de produtos entre sites WooCommerce baseado no SKU. Versao inicial, simples e estável.
Version: 0.5
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

// Adiciona o menu do plugin no painel administrativo
add_action('admin_menu', 'wc_sync_photo_add_admin_menu');

function wc_sync_photo_add_admin_menu() {
    add_menu_page(
        'Configurações do Wc Sync Photo', // Título da página
        'Wc Sync Photo',                  // Nome do menu
        'manage_options',                 // Permissão necessária
        'wc_sync_photo',                  // Slug da página
        'wc_sync_photo_settings_page'     // Função de callback para renderizar a página
    );
}

function wc_sync_photo_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações do Wc Sync Photo</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('wc_sync_photo_options_group'); // Protege e valida o formulário
                do_settings_sections('wc_sync_photo');          // Exibe as seções de configurações registradas
                submit_button();                                // Botão de salvar configurações
            ?>
        </form>
    </div>
    <?php
}

function wc_sync_photo_username_render() {
    $username = get_option('wc_sync_photo_username');
    echo "<input type='text' name='wc_sync_photo_username' value='" . esc_attr($username) . "' />";
}

function wc_sync_photo_password_render() {
    $password = get_option('wc_sync_photo_password');
    echo "<input type='password' name='wc_sync_photo_password' value='" . esc_attr($password) . "' />";
}

function wc_sync_photo_url_render() {
    $url = get_option('wc_sync_photo_url');
    echo "<input type='url' name='wc_sync_photo_url' value='" . esc_attr($url) . "' />";
}

// Inicializa as configurações
add_action('admin_init', 'wc_sync_photo_settings_init');

function wc_sync_photo_settings_init() {
    // Registra as opções com sanitização
    register_setting('wc_sync_photo_options_group', 'wc_sync_photo_username', 'sanitize_text_field');
    register_setting('wc_sync_photo_options_group', 'wc_sync_photo_password', 'sanitize_text_field');
    register_setting('wc_sync_photo_options_group', 'wc_sync_photo_url', 'esc_url_raw');

    // Adiciona a seção de configurações
    add_settings_section(
        'wc_sync_photo_section', 
        'Credenciais do Site Remoto', 
        null, 
        'wc_sync_photo'
    );

    // Campo para o nome de usuário
    add_settings_field(
        'wc_sync_photo_username',
        'Nome de Usuário',
        'wc_sync_photo_username_render',
        'wc_sync_photo',
        'wc_sync_photo_section'
    );

    // Campo para a senha
    add_settings_field(
        'wc_sync_photo_password',
        'Senha',
        'wc_sync_photo_password_render',
        'wc_sync_photo',
        'wc_sync_photo_section'
    );

    // Campo para a URL do site remoto
    add_settings_field(
        'wc_sync_photo_url',
        'URL do Site Remoto',
        'wc_sync_photo_url_render',
        'wc_sync_photo',
        'wc_sync_photo_section'
    );
}
