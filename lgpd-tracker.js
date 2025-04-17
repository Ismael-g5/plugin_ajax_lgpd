jQuery(document).ready(function ($) {
    $('.modal-cacsp-btn').on('click', function (e) {
        e.preventDefault();

        let response = '';

        if ($(this).hasClass('modal-cacsp-btn-accept-all')) {
            response = 'aceitou_tudo';

        } else if (
            $(this).hasClass('modal-cacsp-btn-accept') &&
            !$(this).hasClass('modal-cacsp-btn-accept-all')
        ) {
            response = 'aceitou';

        } else if ($(this).hasClass('modal-cacsp-btn-save')) {
            response = 'salvou';
        }

        $.post(lgpd_ajax.ajax_url, {
            action: 'lgpd_save_response',
            response: response
        })
            .done(function (res) {
                if (res.success) {
                    console.log('LGPD:', res.data.message);
                } else {
                    console.error('LGPD erro:', res.data.message);
                }
            })
            .fail(function (xhr, status, error) {
                console.error('LGPD AJAX falhou:', error);
            });
    });
});
