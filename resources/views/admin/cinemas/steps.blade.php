{{-- Tab 3 bước của mục Quản lý rạp. Cần $step (1|2|3), $cinema (bước 2-3), $room (bước 3) --}}
<nav class="admin-tabs" aria-label="Các bước quản lý rạp">
    <a href="{{ \CommonHelper::url('admin/cinemas') }}" class="{{ $step === 1 ? 'active' : '' }}">1. Danh sách rạp</a>
    @if (!empty($cinema))
        <a href="{{ \CommonHelper::url('admin/cinemas/' . $cinema['id'] . '/rooms') }}" class="{{ $step === 2 ? 'active' : '' }}">2. Phòng chiếu · {{ $cinema['name'] }}</a>
    @else
        <a class="disabled" aria-disabled="true">2. Danh sách phòng</a>
    @endif
    @if (!empty($room))
        <a href="{{ \CommonHelper::url('admin/rooms/' . $room['id'] . '/seats') }}" class="active">3. Sơ đồ ghế · {{ $room['name'] }}</a>
    @else
        <a class="disabled" aria-disabled="true">3. Sơ đồ ghế</a>
    @endif
</nav>
