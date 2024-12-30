<?php
/*
Plugin Name: Product Sync Master
Description: Plugin para sincronizar fotos de produtos entre sites WooCommerce baseado no SKU. Versão inicial, simples e bugada.
Version: 0.3.1
Author: Matheus 
*/

// Verificação do WooCommerce ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('WooCommerce precisa estar ativo para este plugin funcionar.');
}

// Função para registrar logs do arquivo sync-produto-marca.php
function wc_sync_produto_marca_log($message) {
    $logDir = plugin_dir_path(__FILE__) . 'logs';
    $logFile = $logDir . '/log-sync-produto-marca.txt';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    if (!file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Inclui o arquivo de funções de sincronização
require_once plugin_dir_path(__FILE__) . 'includes/sync-functions.php';

// Hook para capturar nome da imagem, se tiver imagem relacionada ao produto.
add_action('pre_post_update', 'capture_image_name_before_update', 10, 2);

// Hook para atualizar imagem ao salvar produto
add_action('save_post_product', 'wc_sync_on_product_save', 10, 3);

// Adiciona o menu do plugin no painel administrativo
add_action('admin_menu', 'product_sync_master_add_admin_menu');

function product_sync_master_add_admin_menu() {
    add_menu_page(
        'Configurações do Product Sync Master', // Título da página
        'Product Sync Master',                  // Nome do menu
        'manage_options',                 // Permissão necessária
        'product_sync_master',                  // Slug da página
        'product_sync_master_settings_page',    // Função de callback para renderizar a página
        'dashicons-update'
    );
}

function product_sync_master_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações do Product Sync Master</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('product_sync_master_options_group');
                do_settings_sections('product_sync_master');
                submit_button();
            ?>
        </form>
        <h2>Sincronizar Imagens de Marcas</h2>
        <form method="post">
            <?php
            if (isset($_POST['sync_action']) && $_POST['sync_action'] === 'sync_marcas') {
                try {
                    require_once plugin_dir_path(__FILE__) . 'includes/sync-img-marcas.php';
                    wc_sync_marcas();
                    echo '<div class="updated"><p>Sincronização de marcas concluída. Verifique o log para detalhes.</p></div>';
                } catch (Exception $e) {
                    wc_sync_produto_marca_log('Erro durante a sincronização de marcas: ' . $e->getMessage());
                }
            }
            ?>
            <input type="hidden" name="sync_action" value="sync_marcas">
            <button type="submit" class="button button-primary">Sincronizar Imagens de Marcas</button>
        </form>

        <h2>Sincronizar Marcas dos Produtos</h2>
        <form method="post">
            <?php
            if (isset($_POST['sync_action']) && $_POST['sync_action'] === 'sync_produto_marcas') {
                try {
                    $logDir = plugin_dir_path(__FILE__) . 'logs';
                    $logFile = $logDir . '/log-sync-produto-marca.txt';
                    if (!file_exists($logDir)) {
                        mkdir($logDir, 0755, true);
                    }
                    if (!file_exists($logFile)) {
                        file_put_contents($logFile, '');
                    }
                    
                    require_once plugin_dir_path(__FILE__) . 'includes/sync-produto-marca.php';
                    wc_sync_produto_marca_log('Iniciando sincronização de marcas de produtos.');
                    sync_product_brands();
                    wc_sync_produto_marca_log('Sincronização de marcas de produtos concluída.');
                    echo '<div class="updated"><p>Sincronização de marcas de produtos concluída. Verifique o log para detalhes.</p></div>';
                } catch (Exception $e) {
                    wc_sync_produto_marca_log('Erro durante a sincronização de marcas de produtos: ' . $e->getMessage());
                }
            }
            ?>
            <input type="hidden" name="sync_action" value="sync_produto_marcas">
            <button type="submit" class="button button-primary">Sincronizar Marcas de Produtos</button>
        </form>
    </div>
    <?php
}

function product_sync_master_username_render() {
    $username = get_option('product_sync_master_username');
    echo "<input type='text' name='product_sync_master_username' value='" . esc_attr($username) . "' />";
}

function product_sync_master_password_render() {
    $password = get_option('product_sync_master_password');
    echo "<input type='password' name='product_sync_master_password' value='" . esc_attr($password) . "' />";
}

function product_sync_master_url_render() {
    $url = get_option('product_sync_master_url');
    echo "<input type='url' name='product_sync_master_url' value='" . esc_attr($url) . "' />";
}

// Inicializa as configurações
add_action('admin_init', 'product_sync_master_settings_init');

function product_sync_master_settings_init() {
    register_setting('product_sync_master_options_group', 'product_sync_master_username', 'sanitize_text_field');
    register_setting('product_sync_master_options_group', 'product_sync_master_password', 'sanitize_text_field');
    register_setting('product_sync_master_options_group', 'product_sync_master_url', 'esc_url_raw');

    add_settings_section('product_sync_master_section', 'Credenciais do Site Remoto', null, 'product_sync_master');

    add_settings_field('product_sync_master_username', 'Nome de Usuário', 'product_sync_master_username_render', 'product_sync_master', 'product_sync_master_section');
    add_settings_field('product_sync_master_password', 'Senha', 'product_sync_master_password_render', 'product_sync_master', 'product_sync_master_section');
    add_settings_field('product_sync_master_url', 'URL do Site Remoto', 'product_sync_master_url_render', 'product_sync_master', 'product_sync_master_section');
}
