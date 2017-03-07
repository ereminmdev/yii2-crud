'use strict';

jQuery(function ($) {

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
            var $this = $(this);
            $.post({
                url: $this.attr("href"),
                data: $this.data('params')
            });
            var html = $this.data('title') || $this.text();
            $this.closest(".btn-group").find(".dropdown-toggle").html(html + ' <span class="caret"></span>');
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

        $(window).on("beforeunload", function (e) {
            if (oldCrudFormData != $crudForm.serialize()) {
                var message = "Изменения не сохранены. Вы можете потерять данные!";
                e.returnValue = message;
                return message;
            }
        });

        $(document).on("click", ".js-delete-image", function () {
            var that = this,
                message = $(this).data('message');

            if (window.confirm(message)) {
                $.get($(this).attr("href"), function () {
                    $(that).closest(".form-group").find(".help-block").remove();
                });
            }

            return false;
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

});
