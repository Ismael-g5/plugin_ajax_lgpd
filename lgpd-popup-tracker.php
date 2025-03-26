<?php
/**
 * Plugin Name: LGPD Popup Tracker
 * Plugin URI:  https://seusite.com/
 * Description: Captura respostas do popup de LGPD e armazena no banco de dados.
 * Version: 1.0
 * Author: Seu Nome
 * Author URI: https://seusite.com/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto
}

// Enfileira o script do rastreamento
function lgpd_enqueue_scripts() {
    wp_enqueue_script('lgpd-tracker', plugin_dir_url(__FILE__) . 'lgpd-tracker.js', array('jquery'), null, true);
    
    // Passa o URL AJAX para o script JS
    wp_localize_script('lgpd-tracker', 'lgpd_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'lgpd_enqueue_scripts');

// Função que cria a tabela no banco de dados
function lgpd_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lgpd_respostas';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        response varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Executa a criação da tabela ao ativar o plugin
register_activation_hook(__FILE__, 'lgpd_create_table');

// Função que salva a resposta no banco de dados
function lgpd_save_response() {
    // Verifica se a resposta foi enviada
    if (isset($_POST['response'])) {
        $response = sanitize_text_field($_POST['response']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'lgpd_respostas';

        // Insere a resposta no banco de dados
        $wpdb->insert(
            $table_name,
            array(
                'response' => $response,
                'created_at' => current_time('mysql')
            )
        );
        
        wp_send_json_success(array('message' => 'Resposta registrada com sucesso.'));
    } else {
        wp_send_json_error(array('message' => 'Resposta não recebida.'));
    }
}
add_action('wp_ajax_lgpd_save_response', 'lgpd_save_response');  // Para usuários logados
add_action('wp_ajax_nopriv_lgpd_save_response', 'lgpd_save_response');  // Para usuários não logados

// Adiciona o menu administrativo
function lgpd_add_admin_menu() {
    add_menu_page(
        'LGPD Tracker',               // Título da página
        'LGPD Tracker',               // Nome do menu
        'manage_options',             // Permissão necessária
        'lgpd-tracker',               // Slug do menu
        'lgpd_admin_page',            // Função que renderiza a página
        'dashicons-shield-alt',       // Ícone do menu
        25                            // Posição no menu
    );
}
add_action('admin_menu', 'lgpd_add_admin_menu');

// Função que renderiza a página administrativa
function lgpd_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lgpd_respostas';

    // Conta o número de aceitos e rejeitados
    $aceitou_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE response = 'aceitou'");
    $rejeitou_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE response = 'rejeitou'");

    ?>
    <div class="wrap">
        <h1>LGPD Popup Tracker</h1>
        <p>Aqui estão as respostas capturadas pelo popup de LGPD:</p>
        
        <h2>Contagem de Respostas</h2>
        <p>Aceitaram: <?php echo esc_html($aceitou_count); ?></p>
        <p>Rejeitaram: <?php echo esc_html($rejeitou_count); ?></p>

        <h2>Respostas Registradas</h2>
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th>Resposta</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

                if ($results) {
                    foreach ($results as $row) {
                        echo '<tr>';
                        echo '<td>' . esc_html($row->response) . '</td>';
                        echo '<td>' . esc_html($row->created_at) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">Nenhuma resposta registrada.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
