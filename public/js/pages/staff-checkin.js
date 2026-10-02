// Soát vé: quét QR bằng camera (jsQR) -> mở trang tra cứu mã vé. Xác nhận vào rạp vẫn qua form (server kiểm tra lại).
(function () {
    'use strict';

    var CODE_RE = /^BK[A-Z0-9]{6,10}$/;
    var video = document.getElementById('scanVideo');
    var toggle = document.getElementById('scanToggle');
    var idle = document.getElementById('scanIdle');
    var status = document.getElementById('scanStatus');
    var codeInput = document.getElementById('ticketCode');
    var manualForm = document.getElementById('manualForm');
    if (!video || !toggle) {
        return;
    }

    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var stream = null;
    var rafId = null;
    var lastCode = '';

    function setStatus(text) {
        status.textContent = text;
    }

    function stop() {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        video.srcObject = null;
        idle.hidden = false;
        toggle.textContent = 'Bật camera';
    }

    function found(code) {
        if (code === lastCode) {
            return;
        }
        lastCode = code;
        if (navigator.vibrate) {
            navigator.vibrate(80);
        }
        setStatus('Đã đọc mã ' + code + ', đang tra cứu...');
        stop();
        codeInput.value = code;
        manualForm.submit();
    }

    function tick() {
        if (!stream) {
            return;
        }
        if (video.readyState === video.HAVE_ENOUGH_DATA && window.jsQR) {
            // Thu nhỏ khung hình để quét nhanh hơn trên máy yếu
            var scale = Math.min(1, 640 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            var image = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var result = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
            if (result && result.data) {
                var code = result.data.replace(/\s+/g, '').toUpperCase();
                if (CODE_RE.test(code)) {
                    found(code);
                    return;
                }
                setStatus('Mã QR không phải vé ' + (document.title.split(' - ').pop() || '') + '.');
            }
        }
        rafId = requestAnimationFrame(tick);
    }

    function start() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Trình duyệt không hỗ trợ camera (cần HTTPS hoặc localhost). Hãy nhập mã vé.');
            return;
        }
        if (!window.jsQR) {
            setStatus('Không tải được thư viện quét QR. Hãy nhập mã vé.');
            return;
        }
        setStatus('Đang bật camera...');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (s) {
                stream = s;
                video.srcObject = s;
                return video.play();
            })
            .then(function () {
                idle.hidden = true;
                toggle.textContent = 'Tắt camera';
                setStatus('Đưa mã QR trên vé vào khung.');
                lastCode = '';
                rafId = requestAnimationFrame(tick);
            })
            .catch(function (err) {
                stop();
                setStatus(err && err.name === 'NotAllowedError'
                    ? 'Chưa được cấp quyền camera. Hãy cho phép trong trình duyệt hoặc nhập mã vé.'
                    : 'Không bật được camera. Hãy nhập mã vé.');
            });
    }

    toggle.addEventListener('click', function () {
        if (stream) {
            stop();
            setStatus('Đã tắt camera.');
        } else {
            start();
        }
    });

    // Chuẩn hóa mã khi nhập tay / máy quét cầm tay
    codeInput.addEventListener('input', function () {
        var pos = codeInput.selectionStart;
        codeInput.value = codeInput.value.replace(/\s+/g, '').toUpperCase();
        codeInput.setSelectionRange(pos, pos);
    });

    window.addEventListener('pagehide', stop);
})();
