<?php
/**
 * Hotfix: Sync Master Sheet - Step 4 Service Account JSON Upload for LiteSpeed Server
 * Description: Bypasses include() on temporary upload files to fix "File content is not json!" on LiteSpeed / strict servers.
 */
add_action('wp_ajax_handle_json_file_upload', 'pssg_hotfix_handle_json_file_upload', 1);

function pssg_hotfix_handle_json_file_upload() {
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
    $nonce_action = defined('PSSG_BASE_FILE') ? plugin_basename(PSSG_BASE_FILE) : 'product-sync-master-sheet/product-sync-master-sheet.php';

    if (empty($nonce) || ! wp_verify_nonce($nonce, $nonce_action)) {
        wp_send_json(['status' => 'failed', 'message' => '', 'error' => __('Nonce not found', 'product-sync-master-sheet')]);
        wp_die();
    }

    $service_json_key = 'pssg_service_json_data';
    $akses_tokn_key   = 'pssg_eta_holo_akses_tokn';
    $response         = [];
    $json_data        = [];
    $response['client_email'] = '';

    if (isset($_FILES['json_file'])) {
        $file      = $_FILES['json_file'];
        $temp_file = $file['tmp_name'] ?? '';

        if (! empty($temp_file) && is_uploaded_file($temp_file)) {
            // Read file content safely without PHP execution/inclusion
            $json_content = @file_get_contents($temp_file);

            // Strip UTF-8 BOM if present
            $json_content = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $json_content));

            $service_fileArray = json_decode($json_content, true);

            if (is_array($service_fileArray) && ! empty($service_fileArray)) {
                if (! empty($service_fileArray['type']) && $service_fileArray['type'] === 'service_account' 
                    && ! empty($service_fileArray['private_key']) && ! empty($service_fileArray['client_email'])) {
                    
                    $response['message'][] = __('Service file is OK', 'product-sync-master-sheet');
                    $response['status']    = 'success';
                    $json_data             = $service_fileArray;
                } else {
                    $response['errors'][] = __('Service file content is not right', 'product-sync-master-sheet');
                    $response['status']   = 'failed';
                }
            } else {
                $response['errors'][] = __('File content is not json!', 'product-sync-master-sheet');
                $response['status']   = 'failed';
            }
        }

        if (! empty($response['status']) && $response['status'] === 'success') {
            $upload_dir                = wp_upload_dir();
            $upload_path               = $upload_dir['path'] . '/' . time() . '-' . sanitize_file_name($file['name']);
            $response['json_fil_path'] = $upload_path;
            $json_data['file_name']    = sanitize_file_name($file['name']);
            $json_data['upload_path']  = $upload_path;
            $response['client_email']  = $json_data['client_email'];
            $response['message'][]     = 'File has upload to server';
            
            update_option($service_json_key, $json_data);
        } else {
            $response['errors'][] = __('Wrong file is not uploaded to server', 'product-sync-master-sheet');
        }
    } elseif (isset($_POST['delete']) && sanitize_key($_POST['delete']) === 'json') {
        $response['client_email']  = '';
        $response['json_fil_path'] = '';
        $response['status']        = 'success';
        $response['message']       = __('Service json file has removed.', 'product-sync-master-sheet');
        update_option($service_json_key, []);
    } else {
        $response['errors'][] = __('Service file is not found.', 'product-sync-master-sheet');
        $response['status']   = 'failed';
    }

    set_transient($akses_tokn_key, null, 0);
    wp_send_json($response);
    wp_die();
}