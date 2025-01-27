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

// Sincroniza as imagens de marcas
function wc_sync_marcas() {
    global $username, $password, $url;

    if (!$username || !$password || !$url) {
        wc_sync_log("Configurações ausentes. Verifique username, password e URL.");
        return;
    }

    // Busca as marcas no site de origem
    $origem_url = $url . '/wp-json/wp/v2/marcas?per_page=100';
    $response_origem = wp_remote_get($origem_url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        )
    ));

    if (is_wp_error($response_origem)) {
        wc_sync_log("Erro ao conectar ao site de origem: " . $response_origem->get_error_message());
        return;
    }

    $marcas_origem = json_decode(wp_remote_retrieve_body($response_origem), true);

    if (empty($marcas_origem)) {
        wc_sync_log("Nenhuma marca encontrada no site de origem.");
        return;
    }

    wc_sync_log("Iniciando sincronização de marcas...");

    foreach ($marcas_origem as $marca_origem) {
        $slug = $marca_origem['slug'];
        $imagem_url = isset($marca_origem['meta']['foto_url']) ? $marca_origem['meta']['foto_url'] : null;

        if (!$imagem_url) {
            wc_sync_log("Marca '$slug' no site de origem não possui imagem associada.");
            continue;
        }

        $image_name = basename($imagem_url);

        // Verifica se a imagem já existe no site de destino
        if (wc_sync_image_exists($image_name)) {
            wc_sync_log("Imagem '$image_name' já existe no site de destino. Ignorando sincronização para '$slug'.");
            continue;
        }

        // Busca a marca no site de destino
        $destino_url = site_url('/wp-json/wp/v2/marcas?slug=' . $slug);
        $response_destino = wp_remote_get($destino_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$username:$password")
            )
        ));

        if (is_wp_error($response_destino)) {
            wc_sync_log("Erro ao buscar marca '$slug' no site de destino: " . $response_destino->get_error_message());
            continue;
        }

        $marcas_destino = json_decode(wp_remote_retrieve_body($response_destino), true);

        if (empty($marcas_destino)) {
            wc_sync_log("Marca '$slug' não encontrada no site de destino.");
            continue;
        }

        $marca_destino_id = $marcas_destino[0]['id'];

        // Atualiza a imagem da marca no site de destino
        $update_response = wp_remote_post(site_url('/wp-json/wp/v2/marcas/' . $marca_destino_id), array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$username:$password"),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'meta' => array(
                    'foto_url' => $imagem_url
                )
            ))
        ));

        if (is_wp_error($update_response)) {
            wc_sync_log("Erro ao atualizar imagem da marca '$slug': " . $update_response->get_error_message());
        } else {
            wc_sync_log("Imagem da marca '$slug' sincronizada com sucesso.");
        }
    }
}

// Função para verificar se a imagem já existe no site de destino
function wc_sync_image_exists($image_name) {
    global $username, $password, $url;

    $media_url = site_url('/wp-json/wp/v2/media?search=' . urlencode($image_name));
    $response = wp_remote_get($media_url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        )
    ));

    if (is_wp_error($response)) {
        wc_sync_log("Erro ao verificar imagem '$image_name': " . $response->get_error_message());
        return false;
    }

    $media_items = json_decode(wp_remote_retrieve_body($response), true);

    foreach ($media_items as $media_item) {
        if (strpos($media_item['source_url'], $image_name) !== false) {
            return true;
        }
    }

    return false;
}
