<?php

/**
 * Plugin Name:     LGPD Popup Tracker
 * Description:     Captura IP/User‑Agent e resposta do popup de LGPD, com exportação CSV/PDF.
 * Version:         1.3
 * Author:          Avanz
 * License:         GPL2
 */

if (! defined('ABSPATH')) {
    exit; // impede acesso direto
}

/**
 * Enfileira o script e passa ajax_url
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'lgpd-tracker',
        plugin_dir_url(__FILE__) . 'lgpd-tracker.js',
        ['jquery'],
        null,
        true
    );
    wp_localize_script('lgpd-tracker', 'lgpd_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});

/**
 * Cria tabela e role ao ativar plugin
 */
register_activation_hook(__FILE__, 'lgpd_activate');
function lgpd_activate()
{
    global $wpdb;
    $table   = $wpdb->prefix . 'lgpd_responses';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_ip VARCHAR(100) NOT NULL,
        user_agent TEXT NOT NULL,
        response VARCHAR(10) NOT NULL,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset};";
    dbDelta($sql);

    // cria role e atribui capability
    add_role('lgpd_manager', 'LGPD Manager', [
        'read'                => true,
        'manage_lgpd_tracker' => true,
    ]);
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_lgpd_tracker');
    }
}

/**
 * Remove role e capability ao desativar plugin
 */
register_deactivation_hook(__FILE__, 'lgpd_deactivate');
function lgpd_deactivate()
{
    remove_role('lgpd_manager');
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap('manage_lgpd_tracker');
    }
}

/**
 * Handler AJAX para salvar resposta
 */
add_action('wp_ajax_lgpd_save_response', 'lgpd_save_response');
add_action('wp_ajax_nopriv_lgpd_save_response', 'lgpd_save_response');
function lgpd_save_response()
{
    if (empty($_POST['response'])) {
        wp_send_json_error(['message' => 'Resposta não recebida.']);
        wp_die();
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lgpd_responses';
    $ok    = $wpdb->insert($table, [
        'user_ip'    => sanitize_text_field($_SERVER['REMOTE_ADDR']),
        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'response'   => sanitize_text_field($_POST['response']),
        // timestamp gerado pelo DEFAULT
    ]);
    if ($ok) {
        wp_send_json_success(['message' => 'Registro salvo.']);
    } else {
        wp_send_json_error(['message' => 'Erro ao salvar.']);
    }
    wp_die();
}

/**
 * Exporta CSV
 */
add_action('admin_init', 'lgpd_export_csv');
function lgpd_export_csv()
{
    if (
        ! empty($_POST['export_csv'])
        && check_admin_referer('lgpd_export', 'lgpd_export_nonce')
        && current_user_can('manage_lgpd_tracker')
    ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'lgpd_responses';
        $results = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY timestamp DESC");
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=lgpd_respostas.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'IP', 'User-Agent', 'Resposta', 'Data']);
        foreach ($results as $r) {
            fputcsv($out, [$r->id, $r->user_ip, $r->user_agent, $r->response, $r->timestamp]);
        }
        fclose($out);
        exit();
    }
}

/**
 * Exporta PDF
 */
add_action('admin_init', 'lgpd_export_pdf');
function lgpd_export_pdf()
{
    if (
        ! empty($_POST['export_pdf'])
        && check_admin_referer('lgpd_export', 'lgpd_export_nonce')
        && current_user_can('manage_lgpd_tracker')
    ) {
        require_once plugin_dir_path(__FILE__) . 'fpdf.php';
        global $wpdb;
        $table   = $wpdb->prefix . 'lgpd_responses';
        $results = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY timestamp DESC");
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Respostas LGPD', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(10, 10, 'ID', 1);
        $pdf->Cell(30, 10, 'IP', 1);
        $pdf->Cell(50, 10, 'User-Agent', 1);
        $pdf->Cell(30, 10, 'Resposta', 1);
        $pdf->Cell(40, 10, 'Data', 1);
        $pdf->Ln();
        foreach ($results as $r) {
            $pdf->Cell(10, 10, $r->id, 1);
            $pdf->Cell(30, 10, $r->user_ip, 1);
            $pdf->Cell(50, 10, substr($r->user_agent, 0, 20) . '...', 1);
            $pdf->Cell(30, 10, $r->response, 1);
            $pdf->Cell(40, 10, $r->timestamp, 1);
            $pdf->Ln();
        }
        $pdf->Output('D', 'lgpd_respostas.pdf');
        exit();
    }
}

/**
 * Menu e página admin
 */
add_action('admin_menu', function () {
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
});

function lgpd_admin_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'lgpd_responses';
    $cntA  = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE response='aceitou'");
    $cntS  = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE response='salvou'");
    $cntAA = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE response='aceitou_tudo'");
    $rows  = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY timestamp DESC");
?>
<div class="wrap">
    <h1>LGPD Tracker</h1>
    <h2>Contagens</h2>
    <p>Aceitou: <?php echo esc_html($cntA); ?></p>
    <p>Salvou: <?php echo esc_html($cntS); ?></p>
    <p>Aceitou Tudo: <?php echo esc_html($cntAA); ?></p>
    <h2>Exportação de Dados</h2>
    <form method="post"><?php wp_nonce_field('lgpd_export', 'lgpd_export_nonce'); ?>
        <input type="submit" name="export_csv" class="button button-primary" value="Exportar CSV">
        <input type="submit" name="export_pdf" class="button button-secondary" value="Exportar PDF">
    </form>
    <h2>Respostas Detalhadas</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>IP</th>
                <th>User‑Agent</th>
                <th>Resposta</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows) : ?>
            <?php foreach ($rows as $r) : ?>
            <tr>
                <td><?php echo esc_html($r->id); ?></td>
                <td><?php echo esc_html($r->user_ip); ?></td>
                <td><?php echo esc_html($r->user_agent); ?></td>
                <td><?php echo esc_html($r->response); ?></td>
                <td><?php echo esc_html($r->timestamp); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php else : ?>
            <tr>
                <td colspan="5">Nenhum registro encontrado.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
}