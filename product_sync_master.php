<?php
/*
Plugin Name: Product Sync Master
Description: Plugin para sincronizar fotos de produtos entre sites WooCommerce baseado no SKU. Versão inicial, simples e estável.
Version: 0.4.0
Author: Matheus 
*/

// Verificação do WooCommerce ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('WooCommerce precisa estar ativo para este plugin funcionar.');
}


// Inclui o arquivo de funções de sincronização
require_once plugin_dir_path(__FILE__) . 'includes/sync-img-product.php';

// Hook para capturar nome da imagem, se tiver imagem relacionada ao produto.
add_action('pre_post_update', 'capture_image_name_before_update', 10, 2);

// Hook para atualizar imagem ao salvar produto
add_action('save_post_product', 'wc_sync_on_product_save', 10, 3);

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
                settings_fields('options_group');
                do_settings_sections('product_sync_master');
                submit_button();
            ?>
        </form>
        <h2>Sincronizar Imagens de Marcas</h2>
        <form method="post">
            <?php
        /*
            if (isset($_POST['sync_action']) && $_POST['sync_action'] === 'sync_marcas') {
                try {
                    require_once plugin_dir_path(__FILE__) . 'includes/sync-img-marcas.php';
                    wc_sync_marcas();
                    echo '<div class="updated"><p>Sincronização de marcas concluída. Verifique o log para detalhes.</p></div>';
                } catch (Exception $e) {
                    log_produto_marca('Erro durante a sincronização de marcas: ' . $e->getMessage());
                }
            }
        */
            ?>
            <input type="hidden" name="sync_action" value="sync_marcas">
            <button type="submit" class="button button-primary">Sincronizar Imagens de Marcas</button>
        </form>

        <h2>Sincronizar Marcas com Produtos</h2>
        <form method="post">
            <?php
        /*
            if (isset($_POST['sync_action']) && $_POST['sync_action'] === 'sync_produto_marcas') {
                try {
                    $logDir = plugin_dir_path(__FILE__) . 'logs';
                    $logFile = $logDir . '/log_sync_product_brand.log';
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
        */
            ?>
            <input type="hidden" name="sync_action" value="sync_produto_marcas">
            <button type="submit" class="button button-primary">Sincronizar Marcas de Produtos</button>
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
    register_setting('options_group', 'psm_username', 'sanitize_text_field');
    register_setting('options_group', 'psm_password', 'sanitize_text_field');
    register_setting('options_group', 'psm_url', function($value) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            add_settings_error(
                'psm_url',
                'invalid_url',
                'Por favor, insira uma URL válida.',
                'error'
            );
            return get_option('psm_url',''); // Retorna o valor anterior se a validação falhar
        }
        //wc_sync_produto_marca_log('URL salva: ' . $value);
        return esc_url_raw($value);
    });

    add_settings_section('section', 'Credenciais do Site Remoto', null, 'product_sync_master');

    add_settings_field('psm_username', 'Nome de Usuário', 'username_render', 'product_sync_master', 'section');
    add_settings_field('psm_password', 'Chave da API', 'password_render', 'product_sync_master', 'section');
    add_settings_field('psm_url', 'URL do Site Remoto', 'url_render', 'product_sync_master', 'section');
}
