{{-- Tab mục Banner & Tin tức. Cần $tab: banners | posts | write --}}
<nav class="admin-tabs" aria-label="Nội dung">
    <a href="{{ \CommonHelper::url('admin/content/banners') }}" class="{{ $tab === 'banners' ? 'active' : '' }}">Banner</a>
    <a href="{{ \CommonHelper::url('admin/content/posts') }}" class="{{ $tab === 'posts' ? 'active' : '' }}">Tin tức &amp; Khuyến mãi</a>
    <a href="{{ \CommonHelper::url('admin/content/posts?new=1') }}" class="{{ $tab === 'write' ? 'active' : '' }}">{{ !empty($editing) && $tab === 'write' ? 'Sửa bài' : 'Viết bài' }}</a>
</nav>
