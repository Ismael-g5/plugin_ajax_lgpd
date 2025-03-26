<?php
if (!defined('ABSPATH')) {
    exit; // Evita acesso direto
}

// Função que renderiza a página administrativa
function lgpd_admin_page() {
    ?>
    <div class="wrap">
        <h1>LGPD Tracker - Respostas</h1>
        <p>Aqui você pode visualizar as respostas capturadas do popup LGPD.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Resposta</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="4">Nenhum dado encontrado.</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
