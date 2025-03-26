jQuery(document).ready(function($) {
    //console.log('plugin lgpd');
    
    $('.moove-gdpr-infobar-allow-all, .moove-gdpr-infobar-reject-btn').on('click', function() {
        //console.log('teste');  
        var response = $(this).hasClass('moove-gdpr-infobar-allow-all') ? 'aceitou' : 'rejeitou';
        
        $.post(lgpd_ajax.ajax_url, {
            action: 'lgpd_save_response',
            response: response
        }, function(response) {
            if (response.success) {
                console.log(response.data.message);
            } else {
                console.log('Erro: ' + response.data.message);
            }
        }).fail(function(xhr, status, error) {
            console.log('Erro AJAX: ' + error);
        });
    });
});
