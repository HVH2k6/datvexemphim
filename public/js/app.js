// Global script
(function ($) {
    'use strict';

    // Gửi kèm CSRF token + đánh dấu AJAX cho mọi request jQuery
    $.ajaxSetup({
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        dataType: 'json'
    });

    window.App = {
        formatMoney: function (amount) {
            return Math.round(Number(amount) || 0).toLocaleString('vi-VN') + 'đ';
        },

        escapeHtml: function (value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        },

        errorMessage: function (xhr, fallback) {
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
            return fallback || 'Đã có lỗi xảy ra, vui lòng thử lại';
        }
    };
})(jQuery);
