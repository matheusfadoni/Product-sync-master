<?php
$image_name_before_removal = '';
$username = get_option('wc_sync_photo_username');
$password = get_option('wc_sync_photo_password');
$url = get_option('wc_sync_photo_url');


// Função de log
function wc_sync_log($message) {
    $file = plugin_dir_path(__DIR__) . 'sync-log.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}

// Função para salvar e sincronizar imagem do produto
function wc_sync_on_product_save($post_id, $post, $update) {
    if ($post->post_type !== 'product' || !$update) return;

    $sku = get_post_meta($post_id, '_sku', true);
    if (!$sku) {
         wc_sync_log("Produto sem SKU");
        return;
    }
   
    $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
    if (!$thumbnail_id && !empty($GLOBALS['image_name_before_removal'])) {
        wc_sync_log("Produto com SKU $sku teve Imagem removida."); 
        check_and_remove_image_on_update($post_id, $post, $update);
        return;
    }
    
    if (!$thumbnail_id) {
        wc_sync_log("Produto com SKU $sku não possui imagem destacada no momento da atualização. Encerrando execução.");
        return;
    }

    $image_url = wp_get_attachment_url($thumbnail_id);
    if (!$image_url) {
        wc_sync_log("Erro ao obter a URL da imagem para o SKU $sku.");
        return;
    }

    try {
        wc_sync_update_product_photo($sku, $image_url);
    } catch (Exception $e) {
        wc_sync_log("Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
        return;
    }
}

// Função de sincronização de imagem (envia ao outro site)
function wc_sync_update_product_photo($sku, $image_url) {
    global $username, $password, $url;

    wc_sync_log("Sincronização iniciada para SKU: $sku");

    try {
        // Busca o produto no outro site
        $request_url = $url . '/wc/v3/products?sku=' . $sku;
        $response = wp_remote_get($request_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            wc_sync_log("Erro conexão SKU $sku: " . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !isset($body[0]['id'])) {
            wc_sync_log("Produto SKU $sku não encontrado no outro site.");
            return;
        }

        $product_id = $body[0]['id'];
        wc_sync_log("Produto encontrado. ID: $product_id. Tentando adicionar imagem...");

        $update_response = wp_remote_post("$url/wc/v3/products/$product_id", array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'images' => array(
                    array(
                        'src' => $image_url
                    )
                )
            ))
        ));

        if (is_wp_error($update_response)) {
            wc_sync_log("Erro ao adicionar imagem SKU $sku: " . $update_response->get_error_message());
        } else {
            wc_sync_log("Imagem adicionada com sucesso ao SKU $sku no outro site.");
        }
    } catch (Exception $e) {
        wc_sync_log("Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
        return;
    }
}

function check_and_remove_image_on_update($post_id, $post, $update) {
    if (!$update) return;

    try {
        // Verifica se a imagem destacada foi removida após a atualização
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if (!$thumbnail_id) {
            global $image_name_before_removal;
            $sku = get_post_meta($post_id, '_sku', true);

            if ($image_name_before_removal) {
                wc_sync_remove_image_by_name($image_name_before_removal);
                wc_sync_log("Imagem destacada removida para SKU: $sku. Tentando remover $image_name_before_removal do outro site.");
                // Limpa a variável global após a remoção
                $image_name_before_removal = '';
            }
        }
    } catch (Exception $e) {
        wc_sync_log("Erro inesperado ao verificar/remover imagem para SKU $sku: " . $e->getMessage());
        return;
    }
}

// Função de remoção de imagem
function wc_sync_remove_image_by_name($image_name) {
    global $username, $password, $url;

    wc_sync_log("Iniciando remoção de imagem com nome: $image_name");

    try {
        $media_search_url = $url . "/wp/v2/media?search=" . urlencode($image_name);
        $response = wp_remote_get($media_search_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            wc_sync_log("Erro ao conectar para buscar imagem: " . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body)) {
            wc_sync_log("Imagem com nome $image_name não encontrada no outro site.");
            return;
        }

        foreach ($body as $media_item) {
            if (strpos($media_item['source_url'], $image_name) !== false) {
                $image_id = $media_item['id'];
                $delete_url = $url . "/wp/v2/media/$image_id";

                wc_sync_log("Tentando remover a imagem com ID $image_id no outro site.");

                $delete_response = wp_remote_request($delete_url, array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
                    ),
                    'body' => array('force' => true)
                ));

                if (is_wp_error($delete_response)) {
                    wc_sync_log("Erro ao remover imagem com ID $image_id: " . $delete_response->get_error_message());
                } else {
                    wc_sync_log("Imagem com nome $image_name e ID $image_id removida com sucesso do outro site.");
                }
            }
        }
    } catch (Exception $e) {
        wc_sync_log("Erro inesperado ao remover imagem: " . $e->getMessage());
        return;
    }
}

function capture_image_name_before_update($post_id, $data) {
    global $image_name_before_removal;

    if (get_post_type($post_id) !== 'product') return;

    try {
        // Captura o ID e o nome da imagem destacada atual
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_url($thumbnail_id);
            $image_name_before_removal = basename($image_url);
            wc_sync_log("Imagem capturada antes da atualização: $image_name_before_removal");
        }
    } catch (Exception $e) {
        wc_sync_log("Erro inesperado ao capturar nome da imagem: " . $e->getMessage());
        return;
    }
}
