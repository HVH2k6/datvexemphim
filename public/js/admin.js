/* =====================================================================
   CineHub Admin - hành vi dùng chung
   - form[data-ajax]: gửi FormData bằng fetch, lỗi 422 hiện dưới từng ô, xong thì chuyển trang
   - [data-confirm]: hỏi lại trước khi gửi form / bấm nút
   - [data-slug-from]: tự sinh slug từ ô khác (chỉ khi người dùng chưa tự sửa slug)
   - .upload-box: xem trước ảnh chọn từ máy
   ===================================================================== */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '';

    // ---------- Sidebar trên mobile ----------
    document.addEventListener('click', function (e) {
        var sidebar = document.getElementById('adminSidebar');
        if (!sidebar) return;
        if (e.target.closest('[data-sidebar-toggle]')) {
            sidebar.classList.toggle('show');
        } else if (e.target.closest('[data-sidebar-close]')) {
            sidebar.classList.remove('show');
        }
    });

    // ---------- Hỏi lại ----------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm') || (e.submitter && e.submitter.getAttribute('data-confirm'));
        if (message && !window.confirm(message)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, true);

    // ---------- Lỗi theo từng ô ----------
    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.invalid-feedback[data-auto]').forEach(function (el) { el.remove(); });
        var alert = form.querySelector('.form-alert');
        if (alert) alert.textContent = '';
    }

    function showErrors(form, errors, message) {
        var first = null;
        Object.keys(errors || {}).forEach(function (name) {
            var field = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
            if (!field) return;
            var group = field.closest('[data-field]') || field.parentElement;
            var target = field.type === 'checkbox' || field.type === 'radio' || field.type === 'file' ? group : field;
            target.classList.add('is-invalid');
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.setAttribute('data-auto', '');
            feedback.textContent = errors[name];
            group.appendChild(feedback);
            first = first || field;
        });
        var alert = form.querySelector('.form-alert');
        if (alert && (!first || !errors)) alert.textContent = message || 'Có lỗi xảy ra';
        if (first && first.focus && first.type !== 'hidden') first.focus();
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('form[data-ajax]') || e.defaultPrevented) return;
        e.preventDefault();

        var buttons = form.querySelectorAll('[type=submit]');
        buttons.forEach(function (b) { b.disabled = true; });
        clearErrors(form);

        var data = new FormData(form);
        if (e.submitter && e.submitter.name) data.append(e.submitter.name, e.submitter.value);

        fetch(form.action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf }
        }).then(function (res) {
            return res.json().catch(function () { return { status: 'error', message: 'Máy chủ trả về dữ liệu không hợp lệ' }; });
        }).then(function (json) {
            if (json.status === 'success' && json.redirect) {
                window.location.href = json.redirect;
                return;
            }
            showErrors(form, json.errors, json.message);
            buttons.forEach(function (b) { b.disabled = false; });
        }).catch(function () {
            showErrors(form, null, 'Không kết nối được máy chủ, vui lòng thử lại');
            buttons.forEach(function (b) { b.disabled = false; });
        });
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('is-invalid')) {
            e.target.classList.remove('is-invalid');
            var fb = e.target.parentElement.querySelector('.invalid-feedback[data-auto]');
            if (fb) fb.remove();
        }
    });

    // ---------- Slug tự động ----------
    function slugify(text) {
        return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 200);
    }

    document.querySelectorAll('[data-slug-from]').forEach(function (slug) {
        var source = document.querySelector(slug.getAttribute('data-slug-from'));
        if (!source) return;
        var touched = slug.value !== '' && slug.value !== slugify(source.value);
        slug.addEventListener('input', function () { touched = slug.value !== ''; });
        source.addEventListener('input', function () {
            if (!touched) slug.value = slugify(source.value);
        });
    });

    // ---------- Xem trước ảnh ----------
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches('.upload-box input[type=file]') || !input.files || !input.files[0]) return;
        var box = input.closest('.upload-box');
        var img = box.querySelector('img') || box.appendChild(document.createElement('img'));
        img.alt = '';
        img.src = URL.createObjectURL(input.files[0]);
        box.classList.add('has-image');
    });

    // ---------- Gửi nhanh khi đổi bộ lọc ----------
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
    });
})();
