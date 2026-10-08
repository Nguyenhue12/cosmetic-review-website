<?php
$active_page = 'services';
$page_title = 'Chi Tiết Dịch Vụ - The Editorial Muse';
require_once 'config/database.php';
require_once 'includes/service_post_functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: services.php');
    exit;
}

$return_url = 'service_detail.php?id=' . $id . '#comments';
service_posts_handle_actions($pdo, $return_url);

$success_msg = service_posts_take_flash('success');
$error_msg = service_posts_take_flash('error');
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt_post = $pdo->prepare("
    SELECT sp.*, u.name as user_name, u.avatar, u.role, c.name as category_name,
           sp.rating_avg, sp.rating_count,
           (SELECT rating FROM service_post_ratings r WHERE r.post_id = sp.id AND r.user_id = ?) as my_rating,
           (SELECT type FROM service_post_reactions r WHERE r.post_id = sp.id AND r.user_id = ?) as my_reaction
    FROM service_posts sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN categories c ON sp.category_id = c.id
    WHERE sp.id = ? AND sp.status = 'active'
");
$stmt_post->execute([$current_user_id, $current_user_id, $id]);
$post = $stmt_post->fetch();

if (!$post) {
    die('Dịch vụ/Bài đăng không tồn tại hoặc đã bị ẩn.');
}

$pdo->prepare("UPDATE service_posts SET views_count = views_count + 1 WHERE id = ?")->execute([$id]);
$comments = service_posts_fetch_comments($pdo, $id);
$media = service_posts_fetch_media($pdo, $id);
$tags = service_posts_fetch_tags($pdo, $id);
$mentions = service_posts_fetch_mentions($pdo, $id);

$related_stmt = $pdo->prepare("
    SELECT id, title, image_url, likes_count, comments_count
    FROM service_posts
    WHERE status = 'active' AND id <> ? AND (category_id = ? OR ? IS NULL)
    ORDER BY created_at DESC
    LIMIT 4
");
$related_stmt->execute([$id, $post['category_id'], $post['category_id']]);
$related_posts = $related_stmt->fetchAll();

$avatar = $post['avatar'] ?: ('https://ui-avatars.com/api/?name=' . urlencode($post['user_name']) . '&background=random');
$likeActive = $post['my_reaction'] === 'like' ? 'text-primary bg-primary/10' : 'text-secondary hover:text-primary hover:bg-primary/5';
$dislikeActive = $post['my_reaction'] === 'dislike' ? 'text-error bg-error/10' : 'text-secondary hover:text-error hover:bg-error/5';
$can_manage_post = $current_user_id && ((int)$post['user_id'] === (int)$current_user_id || ($_SESSION['user_role'] ?? '') === 'admin');

require_once 'includes/header.php';
?>

<main class="pt-28 pb-24 bg-background min-h-screen">
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

    <section class="max-w-screen-2xl mx-auto px-6 md:px-12">
        <a href="services.php" class="inline-flex items-center gap-2 text-sm font-bold text-secondary hover:text-primary mb-8">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Quay lại feed dịch vụ
        </a>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <article class="lg:col-span-8 bg-surface-container-lowest border border-outline-variant/20 rounded-lg overflow-hidden shadow-sm">
                <div class="p-6 md:p-8">
                    <div class="flex items-start justify-between gap-4 mb-6">
                        <div class="flex items-center gap-4">
                            <img src="<?= htmlspecialchars($avatar) ?>" class="w-14 h-14 rounded-full object-cover bg-surface-container-high">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-on-background text-lg"><?= htmlspecialchars($post['user_name']) ?></h3>
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
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="manage_service_post">
                                        <input type="hidden" name="operation" value="hide">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="return_to" value="services.php">
                                        <button class="w-full rounded-lg bg-surface-container-low text-on-background py-2 text-xs font-bold hover:bg-surface-container" type="submit">Ẩn bài viết</button>
                                    </form>
                                    <form method="POST" action="" onsubmit="return confirm('Xóa bài đăng này và toàn bộ bình luận?');">
                                        <input type="hidden" name="action" value="manage_service_post">
                                        <input type="hidden" name="operation" value="delete">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="return_to" value="services.php">
                                        <button class="w-full rounded-lg bg-error text-on-error py-2 text-xs font-bold" type="submit">Xóa bài viết</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="" class="space-y-2">
                                        <input type="hidden" name="action" value="report_service_post">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_url) ?>">
                                        <input name="reason" class="w-full rounded-lg border-outline-variant/40 text-xs" placeholder="Lý do báo cáo">
                                        <button class="w-full rounded-lg bg-error text-on-error py-2 text-xs font-bold" type="submit">Báo cáo bài viết</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-5">
                        <?php if($post['location']): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-low px-3 py-1 text-xs font-bold text-secondary"><span class="material-symbols-outlined text-sm">location_on</span><?= htmlspecialchars($post['location']) ?></span>
                        <?php endif; ?>
                        <?php if($post['price_note']): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"><span class="material-symbols-outlined text-sm">sell</span><?= htmlspecialchars($post['price_note']) ?></span>
                        <?php endif; ?>
                    </div>

                    <h1 class="text-4xl md:text-6xl font-headline leading-tight text-on-background mb-6"><?= htmlspecialchars($post['title']) ?></h1>
                    <div class="flex flex-wrap items-center gap-3 mb-6 text-sm">
                        <div class="flex items-center gap-1 text-amber-400">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="material-symbols-outlined <?= $i <= round($post['rating_avg']) ? 'text-amber-400' : 'text-outline-variant/30' ?>">star</span>
                            <?php endfor; ?>
                        </div>
                        <?php if ($post['rating_count'] > 0): ?>
                            <span class="font-bold"><?= number_format($post['rating_avg'], 1) ?>/5</span>
                            <span class="text-secondary">• <?= (int)$post['rating_count'] ?> đánh giá</span>
                        <?php else: ?>
                            <span class="text-secondary">Chưa có đánh giá</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-lg text-on-surface-variant leading-relaxed whitespace-pre-line"><?= htmlspecialchars($post['content']) ?></p>
                    <?php service_posts_render_social_meta($tags, $mentions); ?>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <section class="mt-8 bg-surface-container-low p-6 rounded-lg border border-outline-variant/20">
                            <h3 class="font-semibold mb-3">Đánh giá sao cho bài đăng này</h3>
                            <form method="POST" action="" class="service-rating-form">
                                <input type="hidden" name="action" value="rate_service_post">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_url) ?>">
                                <div class="flex items-center gap-1 text-3xl service-rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="service-rating-star <?= $i <= ($post['my_rating'] ?: 0) ? 'text-amber-400' : 'text-outline-variant/30' ?>" data-value="<?= $i ?>">
                                            <input type="radio" name="rating" value="<?= $i ?>" class="sr-only" <?= $post['my_rating'] == $i ? 'checked' : '' ?>>
                                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                                <button class="mt-4 rounded-lg bg-primary text-on-primary px-5 py-3 text-sm font-bold hover:opacity-90">Gửi đánh giá</button>
                                <?php if ($post['my_rating']): ?>
                                    <p class="text-xs text-secondary mt-2">Bạn đã chấm <?= (int)$post['my_rating'] ?> sao. Bạn có thể cập nhật lần nữa.</p>
                                <?php endif; ?>
                            </form>
                        </section>
                    <?php else: ?>
                        <div class="mt-8 text-sm text-secondary">Đăng nhập để chấm sao cho bài đăng này.</div>
                    <?php endif; ?>
                </div>

                <?php if(!empty($media)): ?>
                    <?php service_posts_render_media_grid($media, $post['id'], 'detail'); ?>
                <?php endif; ?>

                <div class="px-6 md:px-8 py-4 flex flex-wrap items-center justify-between gap-3 text-sm text-secondary border-b border-outline-variant/20">
                    <span><?= (int)$post['likes_count'] ?> thích • <?= (int)$post['dislikes_count'] ?> không thích</span>
                    <span><?= (int)$post['comments_count'] ?> bình luận • <?= (int)$post['views_count'] + 1 ?> lượt xem • <?= (int)$post['shares_count'] ?> chia sẻ</span>
                </div>

                <div class="grid grid-cols-4 gap-2 px-4 py-2 border-b border-outline-variant/20">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="react_service_post">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="type" value="like">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_url) ?>">
                        <button class="w-full rounded-lg py-2 text-xs font-bold <?= $likeActive ?>" type="submit"><span class="material-symbols-outlined align-middle text-base">thumb_up</span> Thích</button>
                    </form>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="react_service_post">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <input type="hidden" name="type" value="dislike">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_url) ?>">
                        <button class="w-full rounded-lg py-2 text-xs font-bold <?= $dislikeActive ?>" type="submit"><span class="material-symbols-outlined align-middle text-base">thumb_down</span> Không thích</button>
                    </form>
                    <a href="#comments" class="w-full rounded-lg py-2 text-xs font-bold text-center text-secondary hover:text-primary hover:bg-primary/5"><span class="material-symbols-outlined align-middle text-base">chat_bubble</span> Bình luận</a>
                    <button type="button" onclick="shareServicePost(<?= (int)$post['id'] ?>, '<?= htmlspecialchars('http://localhost/myspa_project/service_detail.php?id=' . $post['id'], ENT_QUOTES) ?>')" class="w-full rounded-lg py-2 text-xs font-bold text-secondary hover:text-primary hover:bg-primary/5"><span class="material-symbols-outlined align-middle text-base">ios_share</span> Chia sẻ</button>
                </div>

                <section id="comments" class="p-6 md:p-8 scroll-mt-32">
                    <div class="flex items-end justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-3xl font-headline italic text-on-background">Thảo luận</h2>
                            <p class="text-sm text-secondary mt-1">Bình luận nhiều cấp, phản hồi trực tiếp và reaction như một bài đăng cộng đồng.</p>
                        </div>
                        <span class="text-sm font-bold text-primary"><?= count($comments) ?> bình luận</span>
                    </div>

                    <?php service_posts_render_comment_form($post['id'], null, 'Chia sẻ cảm nhận hoặc đặt câu hỏi về dịch vụ này...', $return_url, false); ?>

                    <div class="mt-8">
                        <?php if(empty($comments)): ?>
                            <div class="text-center bg-surface-container-low p-8 rounded-lg">
                                <span class="material-symbols-outlined text-4xl text-primary mb-2">forum</span>
                                <p class="text-secondary">Chưa có bình luận nào. Hãy mở đầu cuộc thảo luận.</p>
                            </div>
                        <?php else: ?>
                            <?php service_posts_render_comments($comments, null, 0, $post['id'], $return_url); ?>
                        <?php endif; ?>
                    </div>
                </section>
            </article>

            <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-28 self-start">
                <section class="bg-surface-container-lowest p-6 rounded-lg border border-outline-variant/20">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-on-surface-variant mb-5">Tác giả</h3>
                    <div class="flex items-center gap-4">
                        <img src="<?= htmlspecialchars($avatar) ?>" class="w-16 h-16 rounded-full object-cover">
                        <div>
                            <p class="text-xl font-headline"><?= htmlspecialchars($post['user_name']) ?></p>
                            <p class="text-xs text-secondary uppercase tracking-widest"><?= htmlspecialchars($post['role']) ?></p>
                        </div>
                    </div>
                </section>

                <section class="bg-surface-container-low p-6 rounded-lg border border-outline-variant/20">
                    <h3 class="font-bold text-sm uppercase tracking-widest text-on-surface-variant mb-5">Bài cùng nhóm</h3>
                    <div class="space-y-4">
                        <?php foreach($related_posts as $rel): ?>
                            <a href="service_detail.php?id=<?= $rel['id'] ?>" class="flex gap-3 group">
                                <img src="<?= htmlspecialchars($rel['image_url'] ?: 'https://placehold.co/200x160/f0eded/775a19?text=Muse') ?>" class="w-20 h-16 object-cover rounded-lg bg-white">
                                <div>
                                    <h4 class="font-bold text-sm leading-snug group-hover:text-primary transition"><?= htmlspecialchars($rel['title']) ?></h4>
                                    <p class="text-xs text-secondary mt-1"><?= (int)$rel['likes_count'] ?> thích • <?= (int)$rel['comments_count'] ?> bình luận</p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($related_posts)): ?>
                            <p class="text-sm text-secondary">Chưa có bài liên quan.</p>
                        <?php endif; ?>
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
});

function shareServicePost(postId, url) {
    navigator.clipboard?.writeText(url);
    const data = new FormData();
    data.append('action', 'share_service_post');
    data.append('post_id', postId);
    data.append('return_to', 'service_detail.php?id=' + postId + '#comments');
    fetch('service_detail.php?id=' + postId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data
    }).catch(() => {});
    showSuccessToast('Đã sao chép link chia sẻ!');
}

function initServiceRatingStars() {
    const form = document.querySelector('.service-rating-form');
    const stars = Array.from(document.querySelectorAll('.service-rating-star'));
    if (!form || stars.length === 0) return;

    const updateStars = (value) => {
        stars.forEach((star) => {
            const starValue = parseInt(star.dataset.value, 10);
            star.classList.toggle('text-amber-400', starValue <= value);
            star.classList.toggle('text-outline-variant/30', starValue > value);
        });
    };

    stars.forEach((star) => {
        star.addEventListener('click', () => {
            const value = parseInt(star.dataset.value, 10);
            updateStars(value);
            const input = star.querySelector('input[type="radio"]');
            if (input) input.checked = true;
        });
    });

    const selected = stars.find((star) => star.querySelector('input[type="radio"]').checked);
    if (selected) {
        updateStars(parseInt(selected.dataset.value, 10));
    }
}

initServiceRatingStars();
</script>

<?php require_once 'includes/footer.php'; ?>
