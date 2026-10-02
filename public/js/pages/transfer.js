// Trang chuyển khoản SePay: đếm ngược + hỏi trạng thái thanh toán, sao chép thông tin
(function ($) {
    'use strict';

    var $root = $('#sepayTransfer');
    if (!$root.length) {
        return;
    }

    var statusUrl = $root.data('status-url');
    var $status = $('#transferStatus');
    var $countdown = $('#countdown');
    var offset = Number($countdown.data('server-now')) - Date.now();
    var expiredAt = Number($countdown.data('expired-at'));
    var polling = null;
    var done = false;

    function finish(url, message, type) {
        done = true;
        clearInterval(polling);
        clearInterval(timer);
        $status.removeClass('alert-info').addClass('alert-' + type).text(message);
        setTimeout(function () { window.location.href = url; }, 1200);
    }

    function poll() {
        if (done) {
            return;
        }
        $.get(statusUrl).done(function (res) {
            var data = res.data || {};
            if (data.status === 'paid') {
                finish(data.redirectUrl, 'Đã nhận được tiền. Đang chuyển tới vé của bạn...', 'success');
            } else if (data.status === 'expired') {
                finish(data.redirectUrl, 'Đơn đã hết thời gian giữ ghế.', 'warning');
            }
        });
    }

    function tick() {
        var remaining = Math.max(0, Math.floor((expiredAt - (Date.now() + offset)) / 1000));
        $countdown.text(String(Math.floor(remaining / 60)).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0'));
        if (remaining <= 0) {
            clearInterval(timer);
            poll();     // server xác nhận hết hạn rồi mới chuyển trang
        }
    }

    $root.on('click', '.copy-btn', function () {
        var $btn = $(this);
        var text = String($btn.data('copy'));
        var restore = function () { setTimeout(function () { $btn.text('Sao chép'); }, 1500); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () { $btn.text('Đã chép'); restore(); });
        } else {
            var $tmp = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo('body');
            $tmp[0].select();
            document.execCommand('copy');
            $tmp.remove();
            $btn.text('Đã chép');
            restore();
        }
    });

    var timer = setInterval(tick, 1000);
    tick();
    polling = setInterval(poll, 3000);
    // Kiểm tra ngay khi quay lại tab (sau khi chuyển khoản trên app ngân hàng)
    $(document).on('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });
})(jQuery);
