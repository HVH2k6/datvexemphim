// Trang chọn ghế: chọn ghế, bắp nước, voucher, điểm. Giá cuối cùng do server tính.
(function ($) {
    'use strict';

    var $root = $('#seatBooking');
    if (!$root.length) {
        return;
    }

    var maxSeats = Number($root.data('max-seats'));
    var selected = [];          // [{id, label, price}]
    var voucherCode = '';
    var usePoints = false;
    var quoteTimer = null;
    var quoteRequest = null;

    var $error = $('#bookingError');
    var $continue = $('#continueBtn');

    function payload() {
        var products = {};
        $('.product-qty input').each(function () {
            var qty = parseInt($(this).val(), 10) || 0;
            if (qty > 0) {
                products[$(this).data('product-id')] = qty;
            }
        });

        return {
            showtimeId: $root.data('showtime-id'),
            seatIds: selected.map(function (s) { return s.id; }),
            products: products,
            voucherCode: voucherCode,
            points: usePoints ? 'max' : 0
        };
    }

    function showError(message) {
        $error.text(message || '').toggleClass('d-none', !message);
    }

    function setFeedback($el, message, ok) {
        $el.empty().removeClass('is-ok is-error');
        if (message) {
            $el.addClass(ok ? 'is-ok' : 'is-error').text((ok ? '✓ ' : '') + message);
        }
    }

    function productAmount() {
        var total = 0;
        $('.product-qty input').each(function () {
            total += (parseInt($(this).val(), 10) || 0) * Number($(this).data('price'));
        });
        return total;
    }

    function renderChips() {
        var $chips = $('#selectedSeats').empty();
        if (!selected.length) {
            $chips.append($('<span class="text-muted small">').text('Chưa chọn ghế nào'));
        }
        selected.forEach(function (seat) {
            $chips.append(
                $('<span class="seat-chip">').text(seat.label).append(
                    $('<button type="button" class="seat-chip-remove">')
                        .attr({ 'data-seat-id': seat.id, 'aria-label': 'Bỏ chọn ghế ' + seat.label })
                        .html('&times;')
                )
            );
        });
        $('#selectedCount').text(selected.length);
    }

    // Tổng tạm tính ở client (chưa giảm giá) để phản hồi ngay khi chọn
    function renderLocal() {
        var tickets = selected.reduce(function (sum, s) { return sum + s.price; }, 0);
        var products = productAmount();

        renderChips();
        $('#sideTickets, #sumTickets').text(App.formatMoney(tickets));
        $('#sideProducts, #sumProducts').text(App.formatMoney(products));
        $('#sumSubtotal').text(App.formatMoney(tickets + products));
        $continue.prop('disabled', !selected.length);
    }

    function renderQuote(q) {
        $('#sideTickets, #sumTickets').text(App.formatMoney(q.ticketAmount));
        $('#sideProducts, #sumProducts').text(App.formatMoney(q.productAmount));
        $('#sumSubtotal').text(App.formatMoney(q.subtotal));
        $('#rowDiscount').toggleClass('d-none', !q.discountAmount);
        $('#sumDiscount').text('−' + App.formatMoney(q.discountAmount));
        $('#sumTotal').text(App.formatMoney(q.totalAmount));

        var parts = [];
        if (q.memberDiscount) { parts.push('Ưu đãi hạng ' + q.memberLevel + ' −' + App.formatMoney(q.memberDiscount)); }
        if (usePoints) {
            setFeedback($('#pointsFeedback'), q.pointsUsed
                ? 'Dùng ' + q.pointsUsed + ' điểm, giảm ' + App.formatMoney(q.pointsDiscount)
                : 'Đơn này chưa dùng được điểm', q.pointsUsed > 0);
        }
        $('#rowDiscount dt').attr('title', parts.join(' · '));
    }

    function resetTotals() {
        $('#rowDiscount').addClass('d-none');
        $('#sumTotal').text(App.formatMoney(productAmount()));
    }

    // Lấy bảng giá từ server (debounce khi thay đổi liên tục)
    function requestQuote(url, onDone) {
        clearTimeout(quoteTimer);
        if (!selected.length) {
            resetTotals();
            if (onDone) { onDone(null); }
            return;
        }

        quoteTimer = setTimeout(function () {
            if (quoteRequest) {
                quoteRequest.abort();
            }
            quoteRequest = $.post(url || $root.data('voucher-url'), payload())
                .done(function (res) {
                    showError('');
                    renderQuote(res.data);
                    if (onDone) { onDone(null, res.data); }
                })
                .fail(function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }
                    if (onDone) {
                        onDone(xhr.responseJSON || {});
                    } else {
                        showError(App.errorMessage(xhr));
                    }
                });
        }, onDone ? 0 : 250);
    }

    function toggleSeat($seat) {
        var id = Number($seat.data('seat-id'));
        var index = selected.findIndex(function (s) { return s.id === id; });

        if (index >= 0) {
            selected.splice(index, 1);
            $seat.removeClass('is-selected').attr('aria-pressed', 'false');
        } else {
            if (selected.length >= maxSeats) {
                showError('Chỉ được chọn tối đa ' + maxSeats + ' ghế');
                return;
            }
            selected.push({ id: id, label: String($seat.data('label')), price: Number($seat.data('price')) });
            $seat.addClass('is-selected').attr('aria-pressed', 'true');
        }

        showError('');
        renderLocal();
        requestQuote();
    }

    // ---------- Ghế ----------
    $root.on('click', '.seat:not(:disabled)', function () {
        toggleSeat($(this));
    });

    $root.on('click', '.seat-chip-remove', function () {
        toggleSeat($('.seat[data-seat-id="' + $(this).data('seat-id') + '"]'));
    });

    // ---------- Bắp nước ----------
    $root.on('click', '.qty-minus, .qty-plus', function () {
        var $input = $(this).closest('.product-qty').find('input');
        var qty = (parseInt($input.val(), 10) || 0) + ($(this).hasClass('qty-plus') ? 1 : -1);
        $input.val(Math.max(0, Math.min(Number($input.attr('max')), qty))).trigger('change');
    });

    $root.on('change', '.product-qty input', function () {
        var qty = parseInt($(this).val(), 10) || 0;
        $(this).val(Math.max(0, Math.min(Number($(this).attr('max')), qty)));
        renderLocal();
        requestQuote();
    });

    // ---------- Voucher ----------
    function applyVoucher() {
        var code = $.trim($('#voucherCode').val()).toUpperCase();
        var $fb = $('#voucherFeedback');
        if (!selected.length) {
            setFeedback($fb, 'Vui lòng chọn ghế trước', false);
            return;
        }

        var previous = voucherCode;
        voucherCode = code;
        requestQuote($root.data('voucher-url'), function (err, quote) {
            if (err) {
                voucherCode = previous;
                setFeedback($fb, err.message || 'Mã không hợp lệ', false);
                requestQuote();
            } else if (code) {
                setFeedback($fb, 'Đã áp dụng ' + code + ' — giảm ' + App.formatMoney(quote.voucherDiscount), true);
            } else {
                setFeedback($fb, '', true);
            }
        });
    }

    $('#applyVoucher').on('click', applyVoucher);
    $('#voucherCode').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyVoucher();
        }
    });

    // ---------- Điểm ----------
    $('#usePoints').on('change', function () {
        var $box = $(this);
        var $fb = $('#pointsFeedback');
        if (!selected.length) {
            $box.prop('checked', false);
            setFeedback($fb, 'Vui lòng chọn ghế trước', false);
            return;
        }

        usePoints = $box.is(':checked');
        if (!usePoints) {
            setFeedback($fb, '', true);
        }
        requestQuote($root.data('points-url'), function (err) {
            if (err) {
                usePoints = false;
                $box.prop('checked', false);
                setFeedback($fb, err.message || 'Không dùng được điểm', false);
                requestQuote();
            }
        });
    });

    // ---------- Đồng hồ giữ ghế ----------
    var $timer = $('#holdTimer');
    if ($timer.length) {
        var offset = Number($timer.data('server-now')) - Date.now();
        var expiredAt = Number($timer.data('expired-at'));
        var tick = function () {
            var left = Math.max(0, Math.floor((expiredAt - (Date.now() + offset)) / 1000));
            $timer.text(String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0'));
            $timer.closest('.hold-timer').toggleClass('is-low', left <= 60);
            if (left <= 0) {
                clearInterval(timerId);
                showError('Hết thời gian chọn ghế. Trang sẽ tải lại để cập nhật sơ đồ ghế.');
                setTimeout(function () { window.location.reload(); }, 2000);
            }
        };
        var timerId = setInterval(tick, 1000);
        tick();
    }

    // ---------- Giữ ghế ----------
    $continue.on('click', function () {
        $continue.prop('disabled', true).text('ĐANG GIỮ GHẾ...');
        showError('');

        $.post($root.data('save-url'), payload())
            .done(function (res) {
                window.location.href = res.data.redirectUrl;
            })
            .fail(function (xhr) {
                var res = xhr.responseJSON || {};
                showError(App.errorMessage(xhr));
                // Ghế vừa bị người khác lấy: tải lại sơ đồ ghế mới nhất
                if (res.field === 'seats') {
                    setTimeout(function () { window.location.reload(); }, 2000);
                }
                $continue.prop('disabled', !selected.length).text('TIẾP TỤC THANH TOÁN');
            });
    });

    renderLocal();
})(jQuery);
