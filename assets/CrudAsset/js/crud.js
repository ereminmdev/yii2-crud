(function ($) {
    'use strict';

    // views/_grid_toolbar
    if ($('.cms-crud-index').length) {
        // stop form submitting when only part of value is written
        $('.cms-crud-grid tr.filters').find('input[type=date], input[type=time]').each(function () {
            $(this).on('change', function () {
                return false;
            });
        });

        $(document).on('click', '.js-crud-post-refresh', function (event) {
            let $that = $(this);
            $.ajax({
                url: $that.data('url') ? $that.data('url') : $that.attr('href'),
                data: $that.data('params') ? $that.data('params') : {},
                method: $that.data('method') ? $that.data('method') : 'POST'
            }).done(function () {
                $.get(location.href).done(function (data) {
                    $('.cms-crud-index').replaceWith(data);
                });
            });
            event.preventDefault();
        });
    }

    // views/_form
    if ($('.cms-crud-form').length) {
        let $crudForm = $('#crudFormData'),
            isDataChanged = false;

        $crudForm.on('change', function () {
            isDataChanged = true;
        });

        $crudForm.on('beforeSubmit', function () {
            $(this).find('.form-buttons .btn').attr('disabled', 'disabled');
            isDataChanged = false;
        });

        $(window).on('beforeunload', function (e) {
            if (isDataChanged) {
                let message = 'Изменения не сохранены. Вы можете потерять данные!';
                e.returnValue = message;
                return message;
            }
        });

        $(document).on('click', '.js-delete-file', function () {
            let that = this,
                message = $(this).data('message');

            if (window.confirm(message)) {
                $.get($(this).attr('href'), function () {
                    $(that).closest('.form-group').find('.help-block').remove();
                });
            }

            return false;
        });
    }

    // components/Crud:renderFormSetvals
    if ($('.cms-crud-setvals').length) {
        $('.js-toggle-block').on('change', function () {
            let destination = $(this).data('destination');

            if ($(this).prop('checked')) {
                $('.' + destination).show();
            } else {
                $('.' + destination).hide();
            }
        }).trigger('change');
    }

}(jQuery));
