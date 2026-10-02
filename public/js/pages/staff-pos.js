// Bán tại quầy: giỏ hàng, tiền thối, tra cứu thành viên. Server tính lại tổng tiền khi thanh toán.
(function ($) {
    'use strict';

    var $form = $('#posForm');
    if (!$form.length) {
        return;
    }

    var MAX_QTY = 50;
    var cart = {};          // id -> {name, price, qty}
    var $list = $('#cartList');
    var $inputs = $('#cartInputs');
    var $submit = $('#posSubmit');
    var $received = $('#received');

    function total() {
        return Object.keys(cart).reduce(function (sum, id) { return sum + cart[id].price * cart[id].qty; }, 0);
    }

    function method() {
        return $form.find('input[name="method"]:checked').val();
    }

    function showError(message) {
        $('#posError').text(message || '').toggleClass('d-none', !message);
    }

    function renderQuickCash(sum) {
        // Gợi ý tờ tiền tròn gần nhất để thu nhanh
        var options = [];
        [10000, 50000, 100000, 200000, 500000].forEach(function (step) {
            var v = Math.ceil(sum / step) * step;
            if (v >= sum && options.indexOf(v) < 0 && options.length < 4) {
                options.push(v);
            }
        });
        var $q = $('#quickCash').empty();
        if (!sum) {
            return;
        }
        options.forEach(function (v) {
            $q.append($('<button type="button" class="btn btn-sm btn-outline-secondary">').attr('data-amount', v).text(App.formatMoney(v)));
        });
    }

    function render() {
        var ids = Object.keys(cart);
        var sum = total();
        $list.empty();
        $inputs.empty();

        if (!ids.length) {
            $list.append('<li class="text-muted small">Chưa có món nào. Chọn sản phẩm bên trái.</li>');
        }
        ids.forEach(function (id) {
            var item = cart[id];
            $list.append(
                $('<li class="cart-item">').append(
                    $('<div class="flex-grow-1 min-w-0">').append(
                        $('<strong class="d-block text-truncate">').text(item.name),
                        $('<span class="small text-muted">').text(App.formatMoney(item.price))
                    ),
                    $('<div class="qty">').append(
                        $('<button type="button" class="cart-minus">').attr({ 'data-id': id, 'aria-label': 'Bớt ' + item.name }).text('−'),
                        $('<span class="cart-qty">').text(item.qty),
                        $('<button type="button" class="cart-plus">').attr({ 'data-id': id, 'aria-label': 'Thêm ' + item.name }).text('+')
                    ),
                    $('<strong class="cart-line">').text(App.formatMoney(item.price * item.qty))
                )
            );
            $inputs.append($('<input type="hidden">').attr('name', 'items[' + id + ']').val(item.qty));
        });

        $('.pos-item').each(function () {
            var qty = cart[$(this).data('id')] ? cart[$(this).data('id')].qty : 0;
            $(this).toggleClass('in-cart', qty > 0).find('.pos-qty-badge').text(qty).prop('hidden', !qty);
        });

        $('#cartTotal').text(App.formatMoney(sum));
        renderQuickCash(sum);
        updateChange();
    }

    function updateChange() {
        var sum = total();
        var cash = method() === 'cash';
        var received = parseInt($received.val(), 10) || 0;
        $('#cashBox').toggle(cash);
        $received.prop('required', cash);
        $('#changeAmount').text(App.formatMoney(Math.max(0, received - sum)))
            .toggleClass('text-danger', cash && received > 0 && received < sum);
        $submit.prop('disabled', !sum || (cash && received < sum));
    }

    function add(id, name, price, delta) {
        var item = cart[id] || { name: name, price: price, qty: 0 };
        item.qty = Math.max(0, Math.min(MAX_QTY, item.qty + delta));
        if (item.qty) {
            cart[id] = item;
        } else {
            delete cart[id];
        }
        showError('');
        render();
    }

    $('.pos-item').on('click', function () {
        var $b = $(this);
        add(String($b.data('id')), String($b.data('name')), Number($b.data('price')), 1);
    });

    $list.on('click', '.cart-plus, .cart-minus', function () {
        var id = String($(this).data('id'));
        add(id, cart[id].name, cart[id].price, $(this).hasClass('cart-plus') ? 1 : -1);
    });

    $('#clearCart').on('click', function () {
        cart = {};
        $received.val('');
        render();
    });

    $('.pos-filter button').on('click', function () {
        var type = $(this).data('type');
        $('.pos-filter button').removeClass('active');
        $(this).addClass('active');
        $('.pos-item').each(function () {
            $(this).toggle(!type || $(this).data('type') === type);
        });
    });

    $form.on('change', 'input[name="method"]', updateChange);
    $received.on('input', updateChange);
    $('#quickCash').on('click', 'button', function () {
        $received.val($(this).data('amount'));
        updateChange();
    });

    // ---------- Thành viên ----------
    $('#checkMember').on('click', function () {
        var contact = $.trim($('#member').val());
        var $fb = $('#memberFeedback').removeClass('is-ok is-error').empty();
        if (!contact) {
            $fb.addClass('is-error').text('Nhập SĐT hoặc email của khách');
            return;
        }
        $.post($form.data('member-url'), { action: 'lookup_member', member: contact })
            .done(function (res) {
                var m = res.data;
                var points = Math.floor(total() * m.pointRate / 100 / 1000);
                $fb.addClass('is-ok').text('✓ ' + m.name + ' · ' + (m.level || 'Member') + ' · ' + m.points + ' điểm'
                    + (total() ? ' · đơn này +' + points + ' điểm' : ''));
            })
            .fail(function (xhr) {
                $fb.addClass('is-error').text(App.errorMessage(xhr, 'Không tìm thấy thành viên'));
            });
    });

    $('#member').on('input', function () {
        $('#memberFeedback').removeClass('is-ok is-error').empty();
    });

    $form.on('submit', function () {
        if (!total()) {
            showError('Chưa chọn sản phẩm nào');
            return false;
        }
        $submit.prop('disabled', true).text('ĐANG XỬ LÝ...');
        return true;
    });

    render();
})(jQuery);
