(function (document, $, undefined) {
    'use strict';

    // views/_grid_toolbar
    if ($(".cms-crud-index").length) {
        var $filters = $(".cms-crud-grid tr.filters");

        $(".toggle-filters").on("click", function () {
            if (!$(this).hasClass("dropdown-toggle")) {
                $filters.toggle();
            }
        });

        $filters.find("input, select").each(function () {
            if ($(this).val()) {
                $filters.show();
                return false;
            }
        });

        // stop form submitting when only part of value is written
        $filters.find("input[type=date], input[type=time]").each(function () {
            $(this).on('change', function () {
                return false;
            });
        });

        $(document).on("click", ".js-ajax-dropdown-list", function (event) {
            var $that = $(this);
            $.post({
                url: $that.attr("href"),
                data: $that.data('params')
            });
            $that.closest(".btn-group").find(".dropdown-toggle").html($that.text() + ' <span class="caret"></span>');
            event.preventDefault();
        });
    }

    // views/_form
    if ($(".cms-crud-form").length) {
        var $crudForm = $("#crudFormData"),
            oldCrudFormData = $crudForm.serialize();

        $crudForm.on("beforeSubmit", function () {
            $(this).find(".form-buttons .btn").attr("disabled", "disabled");
            oldCrudFormData = $crudForm.serialize()
        });

        $(window).on("beforeunload", function () {
            if (oldCrudFormData != $crudForm.serialize()) {
                return "Изменения не сохранены. Вы можете потерять данные!";
            }
        });
    }

    // components/Crud:renderFormSetvals
    if ($(".cms-crud-setvals").length) {
        $(".js-toggle-block").on("change", function () {
            var destination = $(this).data("destination");

            if ($(this).prop("checked")) {
                $("." + destination).show();
            } else {
                $("." + destination).hide();
            }
        }).trigger("change");
    }

})(document, jQuery);
