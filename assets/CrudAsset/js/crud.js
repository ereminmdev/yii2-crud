'use strict';

(function () {
    const key = 'pageScrollPos.' + location.href;
    const data = sessionStorage.getItem(key);

    if (data !== null) {
        const pos = JSON.parse(data);
        window.scrollTo(pos.x, pos.y);
        sessionStorage.removeItem(key);
    }

    document.querySelectorAll('.js-store-page-scroll').forEach(element => {
        element.addEventListener('click', () => setItem());
        element.addEventListener('submit', () => setItem());
    });

    function setItem() {
        sessionStorage.setItem(key, JSON.stringify({x: window.scrollX, y: window.scrollY}));
    }
})();

jQuery(function ($) {

    // views/index
    if ($('.cms-crud-index').length) {
        // Stop submitting a form when only part of the value is written
        $('.cms-crud-grid tr.filters').find('input[type=date], input[type=time]').each(function () {
            $(this).on('change', function () {
                return false;
            });
        });

        $(document).on('click', '.js-crud-post-refresh', function (event) {
            const $that = $(this);
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

        function treeOpen($item) {
            $.get(treeOpenUrl.replace('_id_', $item.data('id')), function (data) {
                $item.append(data);
            });
        }

        function treeClose($item) {
            const $sub = $item.find('.tree-items');

            let ids = [$item.data('id')];
            $sub.find('.tree-items').each(function () {
                ids.push($(this).data('parent-id'));
            });

            $.get(treeCloseUrl.replace('_ids_', ids.join(',')));

            $sub.remove();
        }

        $(document).on('click', '.js-tree-open, .js-tree-close', function () {
            const $item = $(this).closest('.tree-item');

            if (!$item.hasClass('open')) {
                treeOpen($item);
            } else {
                treeClose($item);
            }

            $item.toggleClass('open');

            return false;
        });

        $('.js-edit-prompt').on('click', function () {
            const that = this;

            const id = $(that).closest('tr').data('key');
            const message = $(that).data('message');
            const column = $(that).data('column');

            const oldValue = $(that).text();
            const value = prompt(message, oldValue);

            if ((value !== null) && (value !== oldValue)) {
                const url = window.jsEditPromptUrl || location.href;

                $.post(url, {id: id, column: column, value: value})
                    .done(function (data) {
                        $(that).text(data);
                    })
                    .fail(function (jqXHR) {
                        alert(jqXHR.responseText);
                    });
            }

            return false;
        });
    }

    // views/_form
    if ($('.cms-crud-form').length) {
        const $crudForm = $('#crudFormData');
        let isDataChanged = false;

        $crudForm.on('change', function () {
            isDataChanged = true;
        });

        $crudForm.on('beforeSubmit', function () {
            $(this).find('.form-buttons .btn').attr('disabled', 'disabled');
            isDataChanged = false;
        });

        $(window).on('beforeunload', function (e) {
            if (isDataChanged) {
                const message = 'Изменения не сохранены. Вы можете потерять данные!';
                e.returnValue = message;
                return message;
            }
        });

        $(document).on('click', '.js-delete-file', function () {
            const that = this;
            const message = $(this).data('message');

            if (window.confirm(message)) {
                $.get($(this).attr('href'), function () {
                    $(that).closest('.form-group').find('.help-block').remove();
                });
            }

            return false;
        });

        inputAutoHeight('.input-auto-height');

        function inputAutoHeight(selector) {
            document.querySelectorAll(selector).forEach(el => {
                el.style.overflow = 'hidden';
                el.style.resize = 'none';
                el.addEventListener('input', autoResize, false);
                el.addEventListener('focus', autoResize, false);
                autoResize.bind(el)();
            });

            function autoResize() {
                if (this.scrollHeight > 0) {
                    this.style.height = 'auto';
                    const style = getComputedStyle(this);
                    this.style.height = (parseFloat(this.scrollHeight) + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth)) + 'px';
                }
            }
        }
    }

    // views/setvals
    if ($('.cms-crud-setvals').length) {
        $('.js-toggle-block').on('change', function () {
            const destination = $(this).data('destination');

            if ($(this).prop('checked')) {
                $('.' + destination).show();
            } else {
                $('.' + destination).hide();
            }
        }).trigger('change');
    }

});
