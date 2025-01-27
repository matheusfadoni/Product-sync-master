<?php

// Configurações globais do plugin, como login, senha e URL do site de destino
$username = get_option('wc_sync_photo_username');
$password = get_option('wc_sync_photo_password');
$url = get_option('wc_sync_photo_url');

// Função de log para registrar as operações executadas no sistema
function wc_sync_log($message) {
    $file = plugin_dir_path(__DIR__) . 'sync-log.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}

// Função para buscar o ID do produto no site de destino pelo SKU
function get_product_id_by_sku($sku) {
    global $url, $username, $password;
    
    $response = wp_remote_get("$url/wp-json/wc/v3/products?sku=$sku", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        ]
    ]);
    
    if (is_wp_error($response)) {
        wc_sync_log("Erro ao buscar produto pelo SKU $sku: " . $response->get_error_message());
        return null;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return !empty($data[0]['id']) ? $data[0]['id'] : null;
}

// Função para buscar as marcas vinculadas ao produto no site de destino
function get_product_brands($product_id) {
    global $url, $username, $password;
    
    $response = wp_remote_get("$url/wp-json/wp/v2/marcas?post=$product_id", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        ]
    ]);
    
    if (is_wp_error($response)) {
        wc_sync_log("Erro ao buscar marcas vinculadas ao produto $product_id: " . $response->get_error_message());
        return [];
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}

// Função para buscar o ID de uma marca pelo slug
function get_brand_id_by_slug($slug) {
    global $url, $username, $password;
    
    $response = wp_remote_get("$url/wp-json/wp/v2/marcas?slug=$slug", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        ]
    ]);
    
    if (is_wp_error($response)) {
        wc_sync_log("Erro ao buscar a marca com o slug $slug: " . $response->get_error_message());
        return null;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return !empty($data[0]['id']) ? $data[0]['id'] : null;
}

// Função para associar a marca ao produto no site de destino
function associate_brand_to_product($product_id, $brand_id) {
    global $url, $username, $password;
    
    $response = wp_remote_request("$url/wp-json/wp/v2/product/$product_id", [
        'method' => 'PUT',
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$username:$password"),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'marcas' => [$brand_id]
        ])
    ]);
    
    if (is_wp_error($response)) {
        wc_sync_log("Erro ao associar a marca $brand_id ao produto $product_id: " . $response->get_error_message());
    } else {
        wc_sync_log("Marca $brand_id associada ao produto $product_id com sucesso.");
    }
}

// Função principal para sincronizar as marcas
function sync_product_brands() {
    $products = get_all_products_from_origin();
    
    foreach ($products as $product) {
        $sku = $product['sku'];
        
        if (empty($sku)) {
            wc_sync_log("Produto sem SKU. Pulando...");
            continue;
        }
        
        $product_id_destino = get_product_id_by_sku($sku);
        
        if (!$product_id_destino) {
            wc_sync_log("Produto com SKU $sku não encontrado no site de destino.");
            continue;
        }
        
        $brands = get_product_brands($product_id_destino);
        $brand_slugs = array_map(function($brand) {
            return $brand['slug'];
        }, $brands);
        
        foreach ($product['brands'] as $brand) {
            if (in_array($brand['slug'], $brand_slugs)) {
                wc_sync_log("Marca {$brand['slug']} já está vinculada ao produto $sku.");
            } else {
                $brand_id = get_brand_id_by_slug($brand['slug']);
                if ($brand_id) {
                    associate_brand_to_product($product_id_destino, $brand_id);
                } else {
                    wc_sync_log("Marca {$brand['slug']} não encontrada no site de destino.");
                }
            }
        }
    }
}

