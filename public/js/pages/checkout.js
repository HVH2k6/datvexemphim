// Trang thanh toán: đếm ngược thời gian giữ ghế
(function ($) {
    'use strict';

    var $countdown = $('#countdown');
    if (!$countdown.length) {
        return;
    }

    // Bù lệch giờ giữa máy khách và server
    var offset = Number($countdown.data('server-now')) - Date.now();
    var expiredAt = Number($countdown.data('expired-at'));
    var $form = $('#payForm');
    var $submit = $form.find('button[type="submit"]');

    function tick() {
        var remaining = Math.max(0, Math.floor((expiredAt - (Date.now() + offset)) / 1000));
        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;

        $countdown.text(minutes >= 60
            ? 'đến giờ chiếu'
            : String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0'));
        $countdown.closest('.hold-note').toggleClass('is-low', remaining <= 60);

        if (remaining <= 0) {
            clearInterval(timer);
            $submit.prop('disabled', true).text('ĐÃ HẾT THỜI GIAN GIỮ GHẾ');
            setTimeout(function () { window.location.reload(); }, 1500);
        }
    }

    $form.on('submit', function () {
        $submit.prop('disabled', true).text('ĐANG XỬ LÝ...');
    });

    var timer = setInterval(tick, 1000);
    tick();
})(jQuery);
