<?php
$active_page = 'services';
$page_title = 'Dịch Vụ Mỹ Phẩm - The Editorial Muse';
require_once 'config/database.php';
require_once 'includes/service_post_functions.php';

service_posts_handle_actions($pdo, 'services.php');

$success_msg = service_posts_take_flash('success');
$error_msg = service_posts_take_flash('error');

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = trim($_GET['search'] ?? '');
$current_user_id = $_SESSION['user_id'] ?? 0;
$can_post = service_posts_can_current_user($pdo);
$posting_mode = service_posts_get_posting_mode($pdo);
$video_limit_mb = service_posts_get_video_limit_mb($pdo);

$stmt_cats = $pdo->query("SELECT * FROM categories WHERE type = 'service' ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

$mentionable_users = $pdo->query("SELECT id, name, avatar FROM users ORDER BY name ASC LIMIT 40")->fetchAll();

$where = ["sp.status = 'active'"];
$params = [$current_user_id];
if ($category_id) {
    $where[] = "sp.category_id = ?";
    $params[] = $category_id;
}
if ($search !== '') {
    $where[] = "(sp.title LIKE ? OR sp.content LIKE ? OR sp.location LIKE ? OR u.name LIKE ? OR EXISTS (SELECT 1 FROM service_post_tags t WHERE t.post_id = sp.id AND t.tag LIKE ?))";
    $like = '%' . $search . '%';
    $tagLike = '%' . service_posts_normalize_tag($search) . '%';
    array_push($params, $like, $like, $like, $like, $tagLike);
}
$where_sql = implode(' AND ', $where);

$stmt_posts = $pdo->prepare("
    SELECT sp.*, u.name as user_name, u.avatar, u.role, c.name as category_name,
           sp.rating_avg, sp.rating_count,
           (SELECT type FROM service_post_reactions r WHERE r.post_id = sp.id AND r.user_id = ?) as my_reaction
    FROM service_posts sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN categories c ON sp.category_id = c.id
    WHERE $where_sql
    ORDER BY sp.created_at DESC
");
$stmt_posts->execute($params);
$posts = $stmt_posts->fetchAll();

$trending_posts = $pdo->query("
    SELECT id, title, likes_count, comments_count
    FROM service_posts
    WHERE status = 'active'
    ORDER BY (likes_count + comments_count + shares_count) DESC, created_at DESC
    LIMIT 4
")->fetchAll();

require_once 'includes/header.php';
?>

<main class="pt-28 pb-24 min-h-screen bg-background">
    <?php if($success_msg): ?>
        <div id="toast-success" class="fixed bottom-6 right-6 z-[999] opacity-0 transition-opacity duration-500 bg-green-600 text-white px-6 py-4 rounded-lg shadow-2xl font-bold flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div id="toast-error" class="fixed bottom-6 right-6 z-[999] opacity-0 transition-opacity duration-500 bg-error text-on-error px-6 py-4 rounded-lg shadow-2xl font-bold flex items-center gap-3">
            <span class="material-symbols-outlined">error</span>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <section class="px-6 md:px-12 max-w-screen-2xl mx-auto">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
            <aside class="xl:col-span-3 space-y-6 xl:sticky xl:top-28 self-start">
                <section class="bg-surface-container-low p-6 rounded-lg border border-outline-variant/20">
                    <p class="text-[10px] uppercase tracking-[0.2em] text-primary font-bold mb-3">Không gian dịch vụ</p>
                    <h1 class="text-4xl font-headline leading-tight text-on-background mb-4">Dịch vụ mỹ phẩm cộng đồng</h1>
                    <p class="text-sm leading-relaxed text-secondary">Đăng trải nghiệm, giới thiệu dịch vụ, hỏi đáp và thảo luận như một feed làm đẹp riêng của The Editorial Muse.</p>
                </section>

                <section class="bg-surface-container-lowest p-5 rounded-lg border border-outline-variant/20">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-on-surface-variant mb-4">Bộ lọc</h3>
                    <form method="GET" action="services.php" class="space-y-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-base">search</span>
                            <input name="search" value="<?= htmlspecialchars($search) ?>" class="w-full rounded-lg border-outline-variant/40 pl-10 text-sm focus:border-primary focus:ring-primary" placeholder="Tìm dịch vụ, địa điểm...">
                        </div>
                        <select name="category" class="w-full rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary">
                            <option value="">Tất cả danh mục</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="w-full bg-primary text-on-primary rounded-lg py-3 text-xs font-bold uppercase tracking-widest hover:opacity-90">Lọc feed</button>
                        <?php if($search || $category_id): ?>
                            <a href="services.php" class="block text-center text-xs font-bold text-secondary hover:text-primary">Xóa bộ lọc</a>
                        <?php endif; ?>
                    </form>
                </section>
            </aside>

            <section class="xl:col-span-6 space-y-6">
                <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/20 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-outline-variant/20 flex items-center gap-3">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=User&background=random') ?>" class="w-11 h-11 rounded-full object-cover">
                            <div>
                                <p class="text-sm font-bold text-on-background"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Thành viên') ?></p>
                                <p class="text-xs text-secondary">Quyền đăng: <?= $can_post ? 'Được đăng dịch vụ' : 'Chưa được cấp quyền' ?></p>
                            </div>
                        <?php else: ?>
                            <div class="w-11 h-11 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-on-background">Bạn muốn chia sẻ dịch vụ?</p>
                                <p class="text-xs text-secondary">Đăng nhập để tham gia thảo luận.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <div class="p-5">
                            <a href="login.php" class="flex items-center justify-center gap-2 w-full rounded-lg bg-primary text-on-primary py-3 text-xs font-bold uppercase tracking-widest hover:opacity-90">
                                <span class="material-symbols-outlined text-base">login</span>
                                Đăng nhập để đăng dịch vụ
                            </a>
                        </div>
                    <?php elseif(!$can_post): ?>
                        <div class="p-5 bg-surface-container-low">
                            <p class="text-sm text-secondary">Chế độ hiện tại là <b class="text-primary"><?= service_posts_mode_label($posting_mode) ?></b>. Quản trị viên có thể cấp quyền đăng trong trang quản lý người dùng.</p>
                        </div>
                    <?php else: ?>
                        <details class="group">
                            <summary class="list-none cursor-pointer p-5 hover:bg-surface-container-low transition flex items-center justify-between">
                                <span class="text-sm text-secondary">Bạn muốn đăng dịch vụ mỹ phẩm nào hôm nay?</span>
                                <span class="material-symbols-outlined text-primary group-open:rotate-45 transition-transform">add_circle</span>
                            </summary>
                            <form method="POST" action="services.php" enctype="multipart/form-data" class="p-5 pt-0 space-y-4 service-composer-form">
                                <input type="hidden" name="action" value="create_service_post">
                                <input type="hidden" name="return_to" value="services.php">
                                <input name="title" required class="w-full rounded-lg border-outline-variant/40 text-lg font-headline focus:border-primary focus:ring-primary" placeholder="Tên dịch vụ mỹ phẩm">
                                <textarea name="content" required rows="5" class="w-full resize-none rounded-lg border-outline-variant/40 text-sm leading-relaxed focus:border-primary focus:ring-primary" placeholder="Mô tả dịch vụ, trải nghiệm, điểm nổi bật, lưu ý..."></textarea>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <select name="category_id" class="rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary">
                                        <option value="">Danh mục</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input name="location" class="rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary" placeholder="Địa điểm">
                                    <input name="price_note" class="rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary" placeholder="Giá tham khảo">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="rounded-lg border border-outline-variant/40 px-4 py-3 text-sm cursor-pointer hover:border-primary transition">
                                        <span class="flex items-center gap-2 font-bold text-on-background"><span class="material-symbols-outlined text-primary">perm_media</span> Chọn ảnh/video</span>
                                        <span class="block text-xs text-secondary mt-1">Có thể chọn nhiều file. Video tối đa <?= $video_limit_mb ?>MB/file.</span>
                                        <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="service-media-input sr-only">
                                    </label>
                                    <textarea name="media_urls" rows="3" class="rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary" placeholder="Hoặc dán link ảnh/video, mỗi dòng một link"></textarea>
                                </div>
                                <div class="service-media-preview hidden rounded-lg border border-outline-variant/30 bg-surface-container-low p-3">
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-xs font-bold uppercase tracking-widest text-primary">Xem trước media</p>
                                        <span class="service-media-count text-xs text-secondary"></span>
                                    </div>
                                    <div class="service-media-preview-grid grid grid-cols-2 md:grid-cols-3 gap-2"></div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input name="tags" class="rounded-lg border-outline-variant/40 text-sm focus:border-primary focus:ring-primary" placeholder="Hashtag: skincare, spa, #retinol">
                                    <details class="rounded-lg border border-outline-variant/40 bg-white px-4 py-3">
                                        <summary class="list-none cursor-pointer flex items-center justify-between text-sm font-bold text-on-background">
                                            <span class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-base">sell</span> Gắn thẻ người</span>
                                            <span class="material-symbols-outlined text-base text-secondary">expand_more</span>
                                        </summary>
                                        <div class="mt-3 max-h-44 overflow-y-auto space-y-2 pr-1">
                                            <?php foreach($mentionable_users as $mention_user): ?>
                                                <?php if((int)$mention_user['id'] === (int)$current_user_id) continue; ?>
                                                <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-surface-container-low p-2 rounded">
                                                    <input type="checkbox" name="tagged_user_ids[]" value="<?= $mention_user['id'] ?>" class="rounded text-primary focus:ring-primary">
                                                    <img src="<?= htmlspecialchars($mention_user['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($mention_user['name']) . '&background=random') ?>" class="w-6 h-6 rounded-full object-cover">
                                                    <span><?= htmlspecialchars($mention_user['name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                </div>
                                <div class="flex justify-end">
                                    <button class="bg-primary text-on-primary px-8 py-3 rounded-lg text-xs font-bold uppercase tracking-widest hover:opacity-90">Đăng dịch vụ</button>
                                </div>
                            </form>
                        </details>
                    <?php endif; ?>
                </section>

                <?php if(empty($posts)): ?>
                    <div class="bg-surface-container-low p-12 text-center rounded-lg border border-outline-variant/20">
                        <span class="material-symbols-outlined text-5xl text-primary mb-3">forum</span>
                        <h2 class="text-2xl font-headline mb-2">Chưa có bài dịch vụ nào</h2>
                        <p class="text-secondary text-sm">Hãy là người đầu tiên mở một cuộc thảo luận mới.</p>
                    </div>
                <?php endif; ?>

                <?php foreach($posts as $post): ?>
                    <?php
                    $return_to = 'services.php#post-' . $post['id'];
                    $avatar = $post['avatar'] ?: ('https://ui-avatars.com/api/?name=' . urlencode($post['user_name']) . '&background=random');
                    $likeActive = $post['my_reaction'] === 'like' ? 'text-primary bg-primary/10' : 'text-secondary hover:text-primary hover:bg-primary/5';
                    $dislikeActive = $post['my_reaction'] === 'dislike' ? 'text-error bg-error/10' : 'text-secondary hover:text-error hover:bg-error/5';
                    $comments = service_posts_fetch_comments($pdo, $post['id']);
                    $media = service_posts_fetch_media($pdo, $post['id']);
                    $tags = service_posts_fetch_tags($pdo, $post['id']);
                    $mentions = service_posts_fetch_mentions($pdo, $post['id']);
                    $can_manage_post = $current_user_id && ((int)$post['user_id'] === (int)$current_user_id || ($_SESSION['user_role'] ?? '') === 'admin');
                    ?>
                    <article id="post-<?= $post['id'] ?>" class="bg-surface-container-lowest rounded-lg border border-outline-variant/20 shadow-sm overflow-hidden">
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($avatar) ?>" class="w-12 h-12 rounded-full object-cover bg-surface-container-high">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-bold text-on-background"><?= htmlspecialchars($post['user_name']) ?></h3>
                                            <?php if($post['role'] === 'admin'): ?>
                                                <span class="text-[10px] font-bold uppercase tracking-widest text-primary bg-primary/10 px-2 py-0.5 rounded-full">Admin</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-secondary">
                                            <?= htmlspecialchars($post['category_name'] ?? 'Dịch vụ') ?> • <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <details class="relative">
                                    <summary class="list-none cursor-pointer text-outline hover:text-primary"><span class="material-symbols-outlined">more_horiz</span></summary>
                                    <div class="absolute right-0 mt-2 w-64 rounded-lg bg-white shadow-xl border border-outline-variant/20 p-3 z-10 space-y-2">
                                        <?php if($can_manage_post): ?>
                                            <form method="POST" action="services.php">
                                                <input type="hidden" name="action" value="manage_service_post">
                                                <input type="hidden" name="operation" value="hide">
                                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                <input type="hidden" name="return_to" value="services.php">
                                                <button class="w-full rounded-lg bg-surface-container-low text-on-background py-2 text-xs font-bold hover:bg-surface-container" type="submit">Ẩn bài viết</button>
                                            </form>
                                            <form method="POST" action="services.php" onsubmit="return confirm('Xóa bài đăng này và toàn bộ bình luận?');">
                                                <input type="hidden" name="action" value="manage_service_post">
                                                <input type="hidden" name="operation" value="delete">
                                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                <input type="hidden" name="return_to" value="services.php">
                                                <button class="w-full rounded-lg bg-error text-on-error py-2 text-xs font-bold" type="submit">Xóa bài viết</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="services.php" class="space-y-2">
                                                <input type="hidden" name="action" value="report_service_post">
                                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
                                                <input name="reason" class="w-full rounded-lg border-outline-variant/40 text-xs" placeholder="Lý do báo cáo">
                                                <button class="w-full rounded-lg bg-error text-on-error py-2 text-xs font-bold" type="submit">Báo cáo bài viết</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div>

                            <a href="service_detail.php?id=<?= $post['id'] ?>" class="block group">
                                <h2 class="text-2xl md:text-3xl font-headline text-on-background group-hover:text-primary transition mb-3"><?= htmlspecialchars($post['title']) ?></h2>
                                <p class="text-on-surface-variant leading-relaxed line-clamp-4"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                            </a>

                            <div class="flex flex-wrap gap-2 mt-4">
                                <?php if($post['location']): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-low px-3 py-1 text-xs font-bold text-secondary"><span class="material-symbols-outlined text-sm">location_on</span><?= htmlspecialchars($post['location']) ?></span>
                                <?php endif; ?>
                                <?php if($post['price_note']): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"><span class="material-symbols-outlined text-sm">sell</span><?= htmlspecialchars($post['price_note']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if($post['rating_count'] > 0): ?>
                                <div class="flex items-center gap-1 text-amber-400 text-xs mt-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="material-symbols-outlined <?= $i <= round($post['rating_avg']) ? 'text-amber-400' : 'text-outline-variant/30' ?>">star</span>
                                    <?php endfor; ?>
                                    <span class="text-secondary"><?= number_format($post['rating_avg'], 1) ?>/5 (<?= (int)$post['rating_count'] ?>)</span>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-secondary mt-2">Chưa có đánh giá</div>
                            <?php endif; ?>
                            <?php service_posts_render_social_meta($tags, $mentions); ?>
                        </div>

                        <?php if(!empty($media)): ?>
                            <?php service_posts_render_media_grid($media, $post['id'], 'feed'); ?>
                        <?php endif; ?>

                        <div class="px-5 py-3 flex items-center justify-between text-xs text-secondary border-b border-outline-variant/20">
                            <span><?= (int)$post['likes_count'] ?> thích • <?= (int)$post['dislikes_count'] ?> không thích</span>
                            <span><?= (int)$post['comments_count'] ?> bình luận • <?= (int)$post['shares_count'] ?> chia sẻ</span>
                        </div>

                        <div class="grid grid-cols-4 gap-2 px-4 py-2 border-b border-outline-variant/20">
                            <form method="POST" action="services.php">
                                <input type="hidden" name="action" value="react_service_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <input type="hidden" name="type" value="like">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
                                <button class="w-full rounded-lg py-2 text-xs font-bold <?= $likeActive ?>" type="submit"><span class="material-symbols-outlined align-middle text-base">thumb_up</span> Thích</button>
                            </form>
                            <form method="POST" action="services.php">
                                <input type="hidden" name="action" value="react_service_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <input type="hidden" name="type" value="dislike">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
                                <button class="w-full rounded-lg py-2 text-xs font-bold <?= $dislikeActive ?>" type="submit"><span class="material-symbols-outlined align-middle text-base">thumb_down</span> Không thích</button>
                            </form>
                            <a href="service_detail.php?id=<?= $post['id'] ?>#comments" class="w-full rounded-lg py-2 text-xs font-bold text-center text-secondary hover:text-primary hover:bg-primary/5"><span class="material-symbols-outlined align-middle text-base">chat_bubble</span> Bình luận</a>
                            <button type="button" onclick="shareServicePost(<?= (int)$post['id'] ?>, '<?= htmlspecialchars('http://localhost/myspa_project/service_detail.php?id=' . $post['id'], ENT_QUOTES) ?>')" class="w-full rounded-lg py-2 text-xs font-bold text-secondary hover:text-primary hover:bg-primary/5"><span class="material-symbols-outlined align-middle text-base">ios_share</span> Chia sẻ</button>
                        </div>

                        <div class="p-5 bg-surface-container-lowest">
                            <?php service_posts_render_comment_form($post['id'], null, 'Viết bình luận cho dịch vụ này...', $return_to, false); ?>
                            <div class="mt-4">
                                <?php service_posts_render_comments($comments, null, 0, $post['id'], $return_to); ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <aside class="xl:col-span-3 space-y-6 xl:sticky xl:top-28 self-start">
                <section class="bg-surface-container-lowest p-6 rounded-lg border border-outline-variant/20">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-on-surface-variant mb-4">Quyền đăng hiện tại</h3>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                        </div>
                        <div>
                            <p class="font-headline text-lg"><?= service_posts_mode_label($posting_mode) ?></p>
                            <p class="text-xs text-secondary">Admin có thể đổi ở trang Người Dùng.</p>
                        </div>
                    </div>
                </section>

                <section class="bg-surface-container-low p-6 rounded-lg border border-outline-variant/20">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-on-surface-variant mb-4">Đang được thảo luận</h3>
                    <div class="space-y-4">
                        <?php foreach($trending_posts as $trend): ?>
                            <a href="service_detail.php?id=<?= $trend['id'] ?>" class="block group">
                                <h4 class="font-headline text-lg leading-snug group-hover:text-primary transition"><?= htmlspecialchars($trend['title']) ?></h4>
                                <p class="text-xs text-secondary mt-1"><?= (int)$trend['likes_count'] ?> thích • <?= (int)$trend['comments_count'] ?> bình luận</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            </aside>
        </div>
    </section>
</main>

<?php service_posts_render_media_viewer_assets(); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    ['toast-success', 'toast-error'].forEach((id) => {
        const toast = document.getElementById(id);
        if (!toast) return;
        setTimeout(() => { toast.classList.remove('opacity-0'); toast.classList.add('opacity-100'); }, 100);
        setTimeout(() => { toast.classList.remove('opacity-100'); toast.classList.add('opacity-0'); setTimeout(() => toast.remove(), 500); }, 3200);
    });

    document.querySelectorAll('.service-composer-form').forEach(setupServiceComposerPreview);
});

function setupServiceComposerPreview(form) {
    const input = form.querySelector('.service-media-input');
    const urls = form.querySelector('textarea[name="media_urls"]');
    const panel = form.querySelector('.service-media-preview');
    const grid = form.querySelector('.service-media-preview-grid');
    const count = form.querySelector('.service-media-count');
    const maxVideoBytes = <?= (int)$video_limit_mb ?> * 1024 * 1024;

    function makeTile(src, type, name, oversize) {
        const tile = document.createElement('div');
        tile.className = 'relative aspect-square overflow-hidden rounded-lg bg-white border border-outline-variant/30';
        const badge = document.createElement('div');
        badge.className = 'absolute left-2 top-2 z-10 rounded-full bg-black/60 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-white';
        badge.textContent = type === 'video' ? 'Video' : 'Ảnh';
        tile.appendChild(badge);
        if (type === 'video') {
            const video = document.createElement('video');
            video.src = src;
            video.muted = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.className = 'w-full h-full object-cover';
            tile.appendChild(video);
            const play = document.createElement('span');
            play.className = 'material-symbols-outlined absolute inset-0 flex items-center justify-center text-white text-4xl drop-shadow';
            play.textContent = 'play_circle';
            tile.appendChild(play);
        } else {
            const img = document.createElement('img');
            img.src = src;
            img.className = 'w-full h-full object-cover';
            tile.appendChild(img);
        }
        if (oversize) {
            const warn = document.createElement('div');
            warn.className = 'absolute inset-x-0 bottom-0 bg-error px-2 py-1 text-[10px] font-bold text-on-error';
            warn.textContent = 'Vượt giới hạn video';
            tile.appendChild(warn);
        } else if (name) {
            const label = document.createElement('div');
            label.className = 'absolute inset-x-0 bottom-0 bg-black/45 px-2 py-1 text-[10px] text-white truncate';
            label.textContent = name;
            tile.appendChild(label);
        }
        return tile;
    }

    function refreshPreview() {
        if (!grid || !panel) return;
        grid.innerHTML = '';
        const items = [];
        Array.from(input?.files || []).forEach((file) => {
            const isVideo = file.type.startsWith('video/');
            const src = URL.createObjectURL(file);
            items.push({ src, type: isVideo ? 'video' : 'image', name: file.name, oversize: isVideo && file.size > maxVideoBytes });
        });
        (urls?.value || '').split(/\n+/).map((line) => line.trim()).filter(Boolean).forEach((url) => {
            const clean = url.toLowerCase().split('?')[0];
            const isVideo = /\.(mp4|webm|mov|m4v)$/.test(clean);
            items.push({ src: url, type: isVideo ? 'video' : 'image', name: 'Link media', oversize: false });
        });
        items.forEach((item) => grid.appendChild(makeTile(item.src, item.type, item.name, item.oversize)));
        panel.classList.toggle('hidden', items.length === 0);
        if (count) count.textContent = items.length ? `${items.length} media đã chọn` : '';
    }

    input?.addEventListener('change', refreshPreview);
    urls?.addEventListener('input', refreshPreview);
}

function shareServicePost(postId, url) {
    navigator.clipboard?.writeText(url);
    const data = new FormData();
    data.append('action', 'share_service_post');
    data.append('post_id', postId);
    data.append('return_to', 'services.php#post-' + postId);
    fetch('services.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data
    }).catch(() => {});
    showSuccessToast('Đã sao chép link chia sẻ!');
}
</script>

<?php require_once 'includes/footer.php'; ?>
