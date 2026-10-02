@if (!empty($_SESSION['flash_message']))
    <div class="alert alert-{{ $_SESSION['flash_type'] ?? 'info' }} alert-dismissible fade show" role="alert">
        {{ $_SESSION['flash_message'] }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
    @php
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    @endphp
@endif
