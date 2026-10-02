<?php
namespace App\Controllers\Admin;

use App\Core\ValidationException;

/**
 * Banner trang chủ (thứ tự hiển thị, bật / tắt) và bài viết Tin tức / Khuyến mãi
 */
class ContentController extends AdminController
{
    const POST_TYPES = ['news' => 'Tin tức', 'promotion' => 'Khuyến mãi'];

    // ---------------- Banner ----------------

    public function banners()
    {
        $banners = $this->db->query('SELECT * FROM banner ORDER BY sortOrder, id');
        $editId = (int)$this->query('edit');
        $editing = null;
        foreach ($banners as $b) {
            if ((int)$b['id'] === $editId) {
                $editing = $b;
            }
        }

        return $this->page('content.banners', ['banners' => $banners, 'editing' => $editing, 'tab' => 'banners']);
    }

    public function storeBanner()
    {
        return $this->saveBanner(null);
    }

    public function updateBanner($id)
    {
        return $this->saveBanner($this->findBanner($id));
    }

    public function destroyBanner($id)
    {
        $banner = $this->findBanner($id);
        $this->db->execute('DELETE FROM banner WHERE id = ?', [(int)$banner['id']]);
        $this->removeUpload($banner['image']);

        return $this->done('/admin/content/banners', 'Đã xóa banner' . ($banner['title'] ? ' ' . $banner['title'] : ''));
    }

    /**
     * Đổi chỗ với banner liền trên / liền dưới, rồi đánh lại số thứ tự 1..n
     */
    public function moveBanner($id)
    {
        $banner = $this->findBanner($id);
        $ids = array_map('intval', array_column($this->db->query('SELECT id FROM banner ORDER BY sortOrder, id'), 'id'));
        $pos = array_search((int)$banner['id'], $ids, true);
        $target = $this->input('direction') === 'up' ? $pos - 1 : $pos + 1;

        if ($target >= 0 && $target < count($ids)) {
            [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
            $this->db->beginTransaction();
            try {
                foreach ($ids as $i => $bannerId) {
                    $this->db->update('banner', ['sortOrder' => $i + 1], 'id = ?', [$bannerId]);
                }
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        return $this->done('/admin/content/banners', 'Đã cập nhật thứ tự banner');
    }

    private function findBanner($id)
    {
        $banner = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM banner WHERE id = ?', [(int)$id], true) : null;
        if (!$banner) {
            $this->notFound();
        }

        return $banner;
    }

    private function saveBanner($banner)
    {
        $data = [
            'title'  => mb_substr($this->input('title'), 0, 255),
            'link'   => $this->input('link'),
            'status' => $this->input('status') === 'inactive' ? 'inactive' : 'active',
        ];

        $errors = [];
        if ($data['link'] !== '' && !$this->validLink($data['link'])) {
            $errors['link'] = 'Link là đường dẫn trong web (bắt đầu bằng /) hoặc URL https://';
        }
        if (!$banner && empty($_FILES['image']['name'])) {
            $errors['image'] = 'Vui lòng chọn ảnh banner';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        try {
            $image = $this->upload('image', 'banners');
        } catch (ValidationException $e) {
            return $this->invalid([$e->getField() => $e->getMessage()]);
        }

        $data['title'] = $data['title'] ?: null;
        $data['link'] = $data['link'] ?: null;
        if ($image) {
            $data['image'] = $image;
        }

        if ($banner) {
            $this->db->update('banner', $data, 'id = ?', [(int)$banner['id']]);
            if ($image) {
                $this->removeUpload($banner['image']);
            }
        } else {
            $data['sortOrder'] = (int)$this->db->query('SELECT COALESCE(MAX(sortOrder), 0) + 1 AS n FROM banner', [], true)['n'];
            $this->db->insert('banner', $data);
        }

        return $this->done('/admin/content/banners', $banner ? 'Đã cập nhật banner' : 'Đã thêm banner');
    }

    /** Link nội bộ (/movies/...) hoặc https:// - không nhận javascript:, //host... */
    private function validLink($link)
    {
        if (strlen($link) > 255 || preg_match('/[\s<>"\']/', $link)) {
            return false;
        }

        return preg_match('#^/(?!/)#', $link) || (preg_match('#^https://#i', $link) && filter_var($link, FILTER_VALIDATE_URL));
    }

    // ---------------- Bài viết ----------------

    public function posts()
    {
        $type = array_key_exists($this->query('type'), self::POST_TYPES) ? $this->query('type') : '';
        $keyword = mb_substr($this->query('q'), 0, 100);

        $where = '1 = 1';
        $params = [];
        if ($type !== '') {
            $where .= ' AND p.type = ?';
            $params[] = $type;
        }
        if ($keyword !== '') {
            $where .= " AND p.title LIKE ? ESCAPE '\\\\'";
            $params[] = $this->like($keyword);
        }

        $pager = $this->paginator((int)$this->db->query("SELECT COUNT(*) AS c FROM post p WHERE {$where}", $params, true)['c']);
        $posts = $this->db->query(
            "SELECT p.id, p.title, p.slug, p.thumbnail, p.type, p.status, p.createdAt, p.updatedAt, u.name AS authorName
             FROM post p LEFT JOIN users u ON u.id = p.authorId
             WHERE {$where} ORDER BY p.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$pager['perPage'], $pager['offset']])
        );

        // ?edit=ID: sửa bài · ?new=1: viết bài mới
        $editId = (int)$this->query('edit');
        $editing = $editId ? $this->db->query('SELECT * FROM post WHERE id = ?', [$editId], true) : null;
        $writing = $editing !== null || $this->query('new') === '1';

        return $this->page('content.posts', [
            'posts'   => $posts,
            'pager'   => $pager,
            'filters' => ['type' => $type, 'q' => $keyword],
            'editing' => $editing,
            'tab'     => $writing ? 'write' : 'posts',
            'types'   => self::POST_TYPES,
        ]);
    }

    public function storePost()
    {
        return $this->savePost(null);
    }

    public function updatePost($id)
    {
        return $this->savePost($this->findPost($id));
    }

    public function destroyPost($id)
    {
        $post = $this->findPost($id);
        $this->db->execute('DELETE FROM post WHERE id = ?', [(int)$post['id']]);
        $this->removeUpload($post['thumbnail']);

        return $this->done('/admin/content/posts', 'Đã xóa bài "' . $post['title'] . '"');
    }

    private function findPost($id)
    {
        $post = ctype_digit((string)$id) ? $this->db->query('SELECT * FROM post WHERE id = ?', [(int)$id], true) : null;
        if (!$post) {
            $this->notFound();
        }

        return $post;
    }

    private function savePost($post)
    {
        $data = [
            'title'   => mb_substr($this->input('title'), 0, 255),
            'slug'    => strtolower($this->input('slug')),
            'summary' => mb_substr($this->input('summary'), 0, 500),
            'content' => $this->input('content'),
            'type'    => $this->input('type'),
            'status'  => $this->input('status') === 'published' ? 'published' : 'draft',
        ];

        $errors = [];
        if ($data['title'] === '') {
            $errors['title'] = 'Vui lòng nhập tiêu đề';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 200) {
            $errors['slug'] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang';
        } elseif ($this->db->query('SELECT id FROM post WHERE slug = ? AND id <> ?', [$data['slug'], (int)($post['id'] ?? 0)], true)) {
            $errors['slug'] = 'Slug đã được dùng cho bài khác';
        }
        if (!array_key_exists($data['type'], self::POST_TYPES)) {
            $errors['type'] = 'Chọn loại bài';
        }
        if ($data['content'] === '') {
            $errors['content'] = 'Vui lòng nhập nội dung';
        } elseif (strlen($data['content']) > 200000) {
            $errors['content'] = 'Nội dung quá dài';
        }
        if ($errors) {
            return $this->invalid($errors);
        }

        try {
            $thumbnail = $this->upload('thumbnail', 'posts');
        } catch (ValidationException $e) {
            return $this->invalid([$e->getField() => $e->getMessage()]);
        }

        $data['summary'] = $data['summary'] ?: null;
        if ($thumbnail) {
            $data['thumbnail'] = $thumbnail;
        }

        if ($post) {
            $this->db->update('post', $data, 'id = ?', [(int)$post['id']]);
            if ($thumbnail) {
                $this->removeUpload($post['thumbnail']);
            }
        } else {
            $this->db->insert('post', $data + ['authorId' => (int)\AuthHelper::id()]);
        }

        return $this->done('/admin/content/posts', ($post ? 'Đã cập nhật bài "' : 'Đã đăng bài "') . $data['title'] . '"'
            . ($data['status'] === 'draft' ? ' (bản nháp)' : ''));
    }
}
