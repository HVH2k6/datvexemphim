<nav aria-label="Menu {{ $area }}" class="list-group">
    @if ($area === 'admin')
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin') }}">Tổng quan</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin/movies') }}">Phim</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin/cinemas') }}">Rạp</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin/showtimes') }}">Suất chiếu</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin/vouchers') }}">Voucher</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('admin/revenue') }}">Doanh thu</a>
    @else
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('staff') }}">Tổng quan</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('staff/checkin') }}">Soát vé</a>
        <a class="list-group-item list-group-item-action" href="{{ \CommonHelper::url('staff/pos') }}">Bán hàng</a>
    @endif
</nav>
