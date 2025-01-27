<?php

$image_name_before_removal = '';
$username = get_option('psm_username');
$password = get_option('psm_password');
$url = get_option('psm_url');

// Functions to sync images of products
function sync_on_product_save($post_id, $post, $update) {
    if ($post->post_type !== 'product' || !$update) return;

    $sku = get_post_meta($post_id, '_sku', true);
    if (!$sku) {
        log_img_product("[LOCAL] [ERRO] Produto sem SKU no post ID $post_id. Encerrando execução.");
        return;
    }

    $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
    if (!$thumbnail_id && !empty($GLOBALS['image_name_before_removal'])) {
        log_img_product("[REMOTO] Produto com SKU $sku teve imagem removida."); 
        check_and_remove_image_on_update($post_id, $post, $update);
        return;
    }

    if (!$thumbnail_id) {
        log_img_product("[LOCAL] Produto com SKU $sku não possui imagem destacada. Encerrando execução.");
        return;
    }

    $image_url = wp_get_attachment_url($thumbnail_id);
    if (!$image_url) {
        log_img_product("[ERRO] Não foi possível obter a URL da imagem para o SKU $sku.");
        return;
    }

    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        log_img_product("[ERRO] URL da imagem inválida para SKU $sku: $image_url");
        return;
    }

    try {
        sync_update_product_photo($sku, $image_url);
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
    }
}

// Função de sincronização de imagem (envia ao outro site)
function sync_update_product_photo($sku, $image_url) {
    global $username, $password, $url;

    log_img_product("[REQUISIÇÃO] Sincronização iniciada para SKU: $sku. URL da imagem: $image_url");

    try {
        // Monta a URL da requisição
        // Validação e montagem da URL final
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            log_img_product("[ERRO] URL base inválida ou vazia: " . ($url ?: 'NULO'));
            return;
        }
        log_img_product("[DEBUG 1] URL base: $url");
        $url = trim($url); // Remove espaços extras
        log_img_product("[DEBUG 2] URL base: $url");
        $request_url = rtrim($url, '/') . '/wp-json/wc/v3/products?sku=' . $sku;
        log_img_product("[DEBUG 3] URL final concatenada: $request_url");


        $response = wp_remote_get($request_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            log_img_product("[ERRO] Erro na conexão com SKU $sku: " . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            log_img_product("[ERRO] Código de resposta da API: $response_code. Resposta: $response_body. URL usada: $request_url");
            return;
        }

        $body = json_decode($response_body, true);

        if (empty($body) || !isset($body[0]['id'])) {
            log_img_product("[ERRO] Produto SKU $sku não encontrado no outro site. Resposta: $response_body");
            return;
        }

        $product_id = $body[0]['id'];
        log_img_product("[REQUISIÇÃO] Produto encontrado. ID: $product_id. Tentando adicionar imagem...");

        $update_response = wp_remote_post(rtrim($url, '/') . "/wp-json/wc/v3/products/$product_id", array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'images' => array(
                    array('src' => $image_url)
                )
            ))
        ));

        // Log do corpo da requisição
        log_img_product("[DEBUG] Corpo da requisição para SKU $sku: " . json_encode(array(
            'images' => array(
                array('src' => $image_url)
            )
        )));

        if (is_wp_error($update_response)) {
            log_img_product("[ERRO] Erro ao adicionar imagem para SKU $sku: " . $update_response->get_error_message());
            return;
        }

        $update_response_code = wp_remote_retrieve_response_code($update_response);
        $update_response_body = wp_remote_retrieve_body($update_response);

        if ($update_response_code !== 200) {
            log_img_product("[ERRO] Código de resposta do PUT: $update_response_code. Resposta: $update_response_body");
            return;
        }

        log_img_product("[REQUISIÇÃO] Imagem adicionada com sucesso ao SKU $sku no outro site.");
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
    }
}

function check_and_remove_image_on_update($post_id, $post, $update) {
    if (!$update) return;

    try {
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if (!$thumbnail_id) {
            global $image_name_before_removal;
            $sku = get_post_meta($post_id, '_sku', true);

            if ($image_name_before_removal) {
                sync_remove_image_by_name($image_name_before_removal);
                log_img_product("[LOCAL] [REQUISIÇÃO] Imagem destacada removida para SKU: $sku. Nome: $image_name_before_removal");
                $image_name_before_removal = '';
            }
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao verificar/remover imagem para SKU $sku: " . $e->getMessage());
    }
}

function sync_remove_image_by_name($image_name) {
    global $username, $password, $url;

    log_img_product("[REQUISIÇÃO] Iniciando remoção de imagem com nome: $image_name");

    try {
        $media_search_url = rtrim($url, '/') . "/wp-json/wp/v2/media?search=" . urlencode($image_name);
        log_img_product("[DEBUG] URL para busca de mídia: $media_search_url");

        $response = wp_remote_get($media_search_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            log_img_product("[ERRO] Erro ao conectar para buscar imagem: " . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body)) {
            log_img_product("[ERRO] Imagem com nome $image_name não encontrada no outro site.");
            return;
        }

        foreach ($body as $media_item) {
            if (strpos($media_item['source_url'], $image_name) !== false) {
                $image_id = $media_item['id'];
                $delete_url = rtrim($url, '/') . "/wp-json/wp/v2/media/$image_id";

                log_img_product("[REQUISIÇÃO] Tentando remover a imagem com ID $image_id no outro site.");

                $delete_response = wp_remote_request($delete_url, array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
                    ),
                    'body' => array('force' => true)
                ));

                if (is_wp_error($delete_response)) {
                    log_img_product("[ERRO] Erro ao remover imagem com ID $image_id: " . $delete_response->get_error_message());
                } else {
                    log_img_product("[REQUISIÇÃO] Imagem com nome $image_name e ID $image_id removida com sucesso do outro site.");
                }
            }
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao remover imagem: " . $e->getMessage());
    }
}

function capture_image_name_before_update($post_id, $data) {
    global $image_name_before_removal;

    if (get_post_type($post_id) !== 'product') return;

    try {
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_url($thumbnail_id);
            $image_name_before_removal = basename($image_url);
            log_img_product("[LOCAL] [CAPTURA] Imagem capturada antes da atualização: $image_name_before_removal");
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao capturar nome da imagem: " . $e->getMessage());
    }
}
