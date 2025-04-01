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

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Enfileira o script do rastreamento
function lgpd_enqueue_scripts() {
    wp_enqueue_script('lgpd-tracker', plugin_dir_url(__FILE__) . 'lgpd-tracker.js', array('jquery'), null, true);
    wp_localize_script('lgpd-tracker', 'lgpd_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'lgpd_enqueue_scripts');

// Cria a tabela no banco de dados ao ativar o plugin
function lgpd_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lgpd_respostas';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        response varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'lgpd_create_table');

// Salva a resposta no banco de dados
function lgpd_save_response() {
    if (isset($_POST['response'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lgpd_respostas';
        $response = sanitize_text_field($_POST['response']);

        $wpdb->insert($table_name, array(
            'response' => $response,
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array('message' => 'Resposta registrada com sucesso.'));
    } else {
        wp_send_json_error(array('message' => 'Resposta não recebida.'));
    }
}
add_action('wp_ajax_lgpd_save_response', 'lgpd_save_response');
add_action('wp_ajax_nopriv_lgpd_save_response', 'lgpd_save_response');

// Criação do papel "LGPD Manager"
function lgpd_create_user_role() {
    add_role('lgpd_manager', 'LGPD Manager', array(
        'read' => true,
        'manage_lgpd_tracker' => true,
    ));

    // Garante que administradores tenham acesso
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('manage_lgpd_tracker');
    }
}
register_activation_hook(__FILE__, 'lgpd_create_user_role');

function lgpd_remove_user_role() {
    remove_role('lgpd_manager');
}
register_deactivation_hook(__FILE__, 'lgpd_remove_user_role');

// Adiciona o menu administrativo
function lgpd_add_admin_menu() {
    if (current_user_can('manage_lgpd_tracker')) { 
        add_menu_page(
            'LGPD Tracker',               
            'LGPD Tracker',               
            'manage_lgpd_tracker',
            'lgpd-tracker',               
            'lgpd_admin_page',            
            'dashicons-shield-alt',       
            25                            
        );
    }
}
add_action('admin_menu', 'lgpd_add_admin_menu');

// Renderiza a página administrativa
function lgpd_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lgpd_respostas';
    $aceitou_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE response = 'aceitou'");
    $rejeitou_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE response = 'rejeitou'");
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>LGPD Popup Tracker</h1>
        <h2>Contagem de Respostas</h2>
        <p>Aceitaram: <?php echo esc_html($aceitou_count); ?></p>
        <p>Rejeitaram: <?php echo esc_html($rejeitou_count); ?></p>
        <h2>Exportação de Dados</h2>
        <form method="post">
            <input type="submit" name="export_csv" class="button button-primary" value="Exportar CSV">
            <input type="submit" name="export_pdf" class="button button-secondary" value="Exportar PDF">
        </form>
        <h2>Respostas Registradas</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Resposta</th><th>Data</th></tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr><td><?php echo esc_html($row->response); ?></td><td><?php echo esc_html($row->created_at); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Exportação CSV
function lgpd_export_csv() {
    if (isset($_POST['export_csv'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lgpd_respostas';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=lgpd_respostas.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Resposta', 'Data'));
        foreach ($results as $row) {
            fputcsv($output, array($row->response, $row->created_at));
        }
        fclose($output);
        exit();
    }
}
add_action('admin_init', 'lgpd_export_csv');

// Exportação PDF
function lgpd_export_pdf() {
    if (isset($_POST['export_pdf'])) {
        require_once(plugin_dir_path(__FILE__) . 'fpdf.php');
        global $wpdb;
        $table_name = $wpdb->prefix . 'lgpd_respostas';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(190, 10, 'Respostas LGPD', 1, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        foreach ($results as $row) {
            $pdf->Cell(95, 10, utf8_decode($row->response), 1);
            $pdf->Cell(95, 10, utf8_decode($row->created_at), 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'lgpd_respostas.pdf');
        exit();
    }
}
add_action('admin_init', 'lgpd_export_pdf');
