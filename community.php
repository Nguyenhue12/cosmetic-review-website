<?php
$active_page = 'community';
$page_title = 'Cộng Đồng - The Editorial Muse';
require_once 'config/database.php';

$success_msg = '';
$error_msg = '';
$valid_tabs = ['latest', 'popular', 'saved'];
$active_tab = $_GET['tab'] ?? 'latest';
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'latest';
}

// Handle Post Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_post') {
    if (!isset($_SESSION['user_id'])) {
        $error_msg = 'Bạn cần đăng nhập để đăng bài viết.';
    } else {
        $topic_id = (int)$_POST['category_id'];
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if (!empty($topic_id) && !empty($title) && !empty($content)) {
            $stmt_in = $pdo->prepare("INSERT INTO discussions (user_id, category_id, title, content, is_trending) VALUES (?, ?, ?, ?, 0)");
            if ($stmt_in->execute([$_SESSION['user_id'], $topic_id, $title, $content])) {
                $success_msg = 'Bài viết của bạn đã được đăng thành công!';
            }
        } else {
            $error_msg = 'Vui lòng điền đủ tiêu đề, chuyên mục và nội dung.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'like_post') {
    if (!isset($_SESSION['user_id'])) {
        $error_msg = 'Bạn cần đăng nhập để thả tim.';
    } else {
        $post_id = (int)$_POST['post_id'];
        $redirect_tab = $_POST['tab'] ?? $active_tab;
        if (!in_array($redirect_tab, $valid_tabs, true)) {
            $redirect_tab = 'latest';
        }

        $stmt_check = $pdo->prepare("SELECT 1 FROM community_post_likes WHERE post_id = ? AND user_id = ?");
        $stmt_check->execute([$post_id, $_SESSION['user_id']]);

        if ($stmt_check->fetch()) {
            $stmt_del = $pdo->prepare("DELETE FROM community_post_likes WHERE post_id = ? AND user_id = ?");
            $stmt_del->execute([$post_id, $_SESSION['user_id']]);
            $stmt_update = $pdo->prepare("UPDATE discussions SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?");
            $stmt_update->execute([$post_id]);
            header("Location: community.php?tab=$redirect_tab&msg=unliked_ok#post-$post_id");
        } else {
            $stmt_in = $pdo->prepare("INSERT IGNORE INTO community_post_likes (post_id, user_id) VALUES (?, ?)");
            $stmt_in->execute([$post_id, $_SESSION['user_id']]);
            if ($stmt_in->rowCount() > 0) {
                $stmt_update = $pdo->prepare("UPDATE discussions SET likes_count = likes_count + 1 WHERE id = ?");
                $stmt_update->execute([$post_id]);
            }
            header("Location: community.php?tab=$redirect_tab&msg=liked_ok#post-$post_id");
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_save_post') {
    if (!isset($_SESSION['user_id'])) {
        $error_msg = 'Bạn cần đăng nhập để lưu bài viết.';
    } else {
        $post_id = (int)$_POST['post_id'];
        $redirect_tab = $_POST['tab'] ?? $active_tab;
        if (!in_array($redirect_tab, $valid_tabs, true)) {
            $redirect_tab = 'latest';
        }

        $stmt_check = $pdo->prepare("SELECT 1 FROM community_saved_posts WHERE post_id = ? AND user_id = ?");
        $stmt_check->execute([$post_id, $_SESSION['user_id']]);

        if ($stmt_check->fetch()) {
            $stmt_del = $pdo->prepare("DELETE FROM community_saved_posts WHERE post_id = ? AND user_id = ?");
            $stmt_del->execute([$post_id, $_SESSION['user_id']]);
            header("Location: community.php?tab=$redirect_tab&msg=unsaved_ok#post-$post_id");
        } else {
            $stmt_in = $pdo->prepare("INSERT IGNORE INTO community_saved_posts (post_id, user_id) VALUES (?, ?)");
            $stmt_in->execute([$post_id, $_SESSION['user_id']]);
            header("Location: community.php?tab=$redirect_tab&msg=saved_ok#post-$post_id");
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reply_post') {
    if (!isset($_SESSION['user_id'])) {
        $error_msg = 'Bạn cần đăng nhập để bình luận.';
    } else {
        $post_id = (int)$_POST['post_id'];
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $content = $_POST['reply_content'] ?? '';
        if (!empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO community_post_comments (post_id, parent_id, user_id, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$post_id, $parent_id, $_SESSION['user_id'], $content]);
            $pdo->query("UPDATE discussions SET replies_count = replies_count + 1 WHERE id = $post_id");
            header("Location: community.php?msg=reply_ok#post-$post_id");
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'react_comment') {
    if (!isset($_SESSION['user_id'])) {
        $error_msg = 'Bạn cần đăng nhập để thả biểu cảm.';
    } else {
        $comment_id = (int)$_POST['comment_id'];
        $type = $_POST['type']; // 'like' or 'dislike'
        
        // Kiểm tra xem user đã react comment này chưa, nếu có thì loại react là gì
        $stmt_check = $pdo->prepare("SELECT type FROM community_comment_reactions WHERE comment_id = ? AND user_id = ?");
        $stmt_check->execute([$comment_id, $_SESSION['user_id']]);
        $existing = $stmt_check->fetch();
        
        if (!$existing) {
            // Chưa react, insert mới
            $stmt_in = $pdo->prepare("INSERT INTO community_comment_reactions (comment_id, user_id, type) VALUES (?, ?, ?)");
            $stmt_in->execute([$comment_id, $_SESSION['user_id'], $type]);
            if ($type == 'like') $pdo->query("UPDATE community_post_comments SET likes_count = likes_count + 1 WHERE id = $comment_id");
            if ($type == 'dislike') $pdo->query("UPDATE community_post_comments SET dislikes_count = dislikes_count + 1 WHERE id = $comment_id");
        } else {
            // Đã react
            if ($existing['type'] === $type) {
                // Hủy react (like -> unlike, dislike -> undislike)
                $stmt_del = $pdo->prepare("DELETE FROM community_comment_reactions WHERE comment_id = ? AND user_id = ?");
                $stmt_del->execute([$comment_id, $_SESSION['user_id']]);
                if ($type == 'like') $pdo->query("UPDATE community_post_comments SET likes_count = likes_count - 1 WHERE id = $comment_id");
                if ($type == 'dislike') $pdo->query("UPDATE community_post_comments SET dislikes_count = dislikes_count - 1 WHERE id = $comment_id");
            } else {
                // Đổi kiểu react (like -> dislike or dislike -> like)
                $stmt_up = $pdo->prepare("UPDATE community_comment_reactions SET type = ? WHERE comment_id = ? AND user_id = ?");
                $stmt_up->execute([$type, $comment_id, $_SESSION['user_id']]);
                if ($type == 'like') $pdo->query("UPDATE community_post_comments SET likes_count = likes_count + 1, dislikes_count = dislikes_count - 1 WHERE id = $comment_id");
                if ($type == 'dislike') $pdo->query("UPDATE community_post_comments SET dislikes_count = dislikes_count + 1, likes_count = likes_count - 1 WHERE id = $comment_id");
            }
        }
        $post_id = (int)($_POST['post_id'] ?? 0);
        header("Location: community.php?msg=react_ok#post-$post_id");
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'reply_ok') $success_msg = 'Đã gửi bình luận!';
    if ($_GET['msg'] == 'react_ok') $success_msg = 'Đã lưu tương tác!';
    if ($_GET['msg'] == 'saved_ok') $success_msg = 'Đã lưu bài viết!';
    if ($_GET['msg'] == 'unsaved_ok') $success_msg = 'Đã bỏ lưu bài viết.';
    if ($_GET['msg'] == 'liked_ok') $success_msg = 'Đã thích bài viết!';
    if ($_GET['msg'] == 'unliked_ok') $success_msg = 'Đã bỏ thích bài viết.';
}

function render_community_comments($comments, $parent_id, $level, $post_id) {
    if ($level > 5) return;
    $has_children = false;
    foreach($comments as $cmt) {
        if ($cmt['parent_id'] == $parent_id) {
            $has_children = true;
            $margin = $level > 0 ? 'ml-8 border-l-2 pl-4 border-outline-variant/30 mt-4' : 'mt-4 pl-4 border-l-2 border-outline-variant/30';
            ?>
            <div class="<?= $margin ?>">
                <div class="flex items-start gap-2 group/cmt">
                    <div class="w-8 h-8 rounded-full bg-surface-container-highest overflow-hidden relative shrink-0">
                        <?php if(!empty($cmt['avatar'])): ?>
                            <img class="w-full h-full object-cover absolute top-0 left-0" src="<?= htmlspecialchars($cmt['avatar']) ?>"/>
                        <?php else: ?>
                            <div class="w-full h-full bg-surface-container-high flex items-center justify-center text-xs font-bold"><?= strtoupper(substr($cmt['user_name'],0,1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant/20 p-4 rounded-lg text-sm flex-1 relative">
                        <p class="font-bold text-on-background mb-1"><?= htmlspecialchars($cmt['user_name']) ?> <?php if($cmt['role']=='admin') echo '<span class="text-xs text-primary">(Admin)</span>'; ?><span class="text-xs font-normal text-secondary ml-2 font-label"><?= date('d/m/Y H:i', strtotime($cmt['created_at'])) ?></span></p>
                        <p class="text-on-surface-variant font-body mb-2"><?= nl2br(htmlspecialchars($cmt['content'])) ?></p>
                        
                        <div class="flex items-center gap-4 mt-2">
                            <form method="POST" action="community.php" class="inline">
                                <input type="hidden" name="action" value="react_comment">
                                <input type="hidden" name="comment_id" value="<?= $cmt['id'] ?>">
                                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                <input type="hidden" name="type" value="like">
                                <button type="submit" class="flex items-center gap-1 text-xs font-bold <?= $cmt['likes_count'] > 0 ? 'text-primary' : 'text-secondary hover:text-primary' ?>">
                                    <span class="material-symbols-outlined text-sm">thumb_up</span> <?= $cmt['likes_count'] ?: 'Thích' ?>
                                </button>
                            </form>
                            <form method="POST" action="community.php" class="inline">
                                <input type="hidden" name="action" value="react_comment">
                                <input type="hidden" name="comment_id" value="<?= $cmt['id'] ?>">
                                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                <input type="hidden" name="type" value="dislike">
                                <button type="submit" class="flex items-center gap-1 text-xs font-bold <?= $cmt['dislikes_count'] > 0 ? 'text-error' : 'text-secondary hover:text-error' ?>">
                                    <span class="material-symbols-outlined text-sm">thumb_down</span> <?= $cmt['dislikes_count'] ?: 'Không thích' ?>
                                </button>
                            </form>
                            <?php if(isset($_SESSION['user_id'])): ?>
                            <details class="cursor-pointer group">
                                <summary class="text-xs font-bold text-secondary hover:text-primary list-none flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">reply</span> Phản hồi
                                </summary>
                                <form method="POST" action="community.php" class="mt-3 flex gap-2 absolute left-0 right-0 z-10 bg-surface-container p-2 rounded shadow-lg border border-outline/10">
                                    <input type="hidden" name="action" value="reply_post">
                                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                    <input type="hidden" name="parent_id" value="<?= $cmt['id'] ?>">
                                    <input type="text" name="reply_content" required placeholder="Viết phản hồi cho bình luận này..." class="flex-1 text-xs px-3 py-2 border rounded focus:border-primary">
                                    <button type="submit" class="bg-primary text-on-primary px-4 py-2 rounded text-xs font-bold hover:opacity-90">GỬI</button>
                                </form>
                            </details>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php render_community_comments($comments, $cmt['id'], $level + 1, $post_id); ?>
            </div>
            <?php
        }
    }
    return $has_children;
}

$stmt_cats = $pdo->query("SELECT * FROM categories WHERE type = 'community'");
$community_cats = $stmt_cats->fetchAll();

// Fetch trending topics
$stmt_trends = $pdo->query("SELECT * FROM discussions WHERE is_trending = 1 ORDER BY likes_count DESC LIMIT 3");
$trending_topics = $stmt_trends->fetchAll();

// Fetch discussions with user info, saved state and tab ordering
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($active_tab === 'saved' && $current_user_id === 0) {
    $posts = [];
    if (!$error_msg) {
        $error_msg = 'Bạn cần đăng nhập để xem bài đã lưu.';
    }
} elseif ($active_tab === 'saved') {
    $stmt_posts = $pdo->prepare("SELECT d.*, u.name as user_name, u.avatar, c.name as category_name, 1 as is_saved,
                                  CASE WHEN liked.post_id IS NULL THEN 0 ELSE 1 END as is_liked
                           FROM community_saved_posts saved
                           JOIN discussions d ON saved.post_id = d.id
                           JOIN users u ON d.user_id = u.id
                           LEFT JOIN categories c ON d.category_id = c.id
                           LEFT JOIN community_post_likes liked ON liked.post_id = d.id AND liked.user_id = ?
                           WHERE saved.user_id = ?
                           ORDER BY saved.created_at DESC");
    $stmt_posts->execute([$current_user_id, $current_user_id]);
    $posts = $stmt_posts->fetchAll();
} else {
    $order_by = $active_tab === 'popular'
        ? 'd.likes_count DESC, d.replies_count DESC, d.created_at DESC'
        : 'd.created_at DESC';
    $stmt_posts = $pdo->prepare("SELECT d.*, u.name as user_name, u.avatar, c.name as category_name,
                                  CASE WHEN saved.post_id IS NULL THEN 0 ELSE 1 END as is_saved,
                                  CASE WHEN liked.post_id IS NULL THEN 0 ELSE 1 END as is_liked
                           FROM discussions d
                           JOIN users u ON d.user_id = u.id
                           LEFT JOIN categories c ON d.category_id = c.id
                           LEFT JOIN community_saved_posts saved ON saved.post_id = d.id AND saved.user_id = ?
                           LEFT JOIN community_post_likes liked ON liked.post_id = d.id AND liked.user_id = ?
                           ORDER BY $order_by");
    $stmt_posts->execute([$current_user_id, $current_user_id]);
    $posts = $stmt_posts->fetchAll();
}

require_once 'includes/header.php';
?>

<main class="pt-32 pb-24 px-6 md:px-12 max-w-screen-2xl mx-auto">
    <?php if($success_msg): ?>
        <div id="toast-success" class="fixed bottom-6 right-6 z-[999] opacity-0 transition-opacity duration-500 bg-green-500 text-white px-6 py-4 rounded-lg shadow-2xl font-bold flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <?= htmlspecialchars($success_msg) ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const t = document.getElementById('toast-success');
                setTimeout(() => { t.classList.remove('opacity-0'); t.classList.add('opacity-100'); }, 100);
                setTimeout(() => { t.classList.remove('opacity-100'); t.classList.add('opacity-0'); setTimeout(() => t.remove(), 500); }, 3000);
            });
        </script>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div id="toast-error" class="fixed bottom-6 right-6 z-[999] opacity-0 transition-opacity duration-500 bg-red-500 text-white px-6 py-4 rounded-lg shadow-2xl font-bold flex items-center gap-3">
            <span class="material-symbols-outlined">error</span>
            <?= htmlspecialchars($error_msg) ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const t = document.getElementById('toast-error');
                setTimeout(() => { t.classList.remove('opacity-0'); t.classList.add('opacity-100'); }, 100);
                setTimeout(() => { t.classList.remove('opacity-100'); t.classList.add('opacity-0'); setTimeout(() => t.remove(), 500); }, 3000);
            });
        </script>
    <?php endif; ?>

    <!-- Header Section -->
    <header class="mb-16 flex flex-col md:flex-row md:items-end justify-between gap-8">
        <div class="max-w-2xl">
            <p class="text-primary font-label text-xs uppercase tracking-[0.2em] mb-4">Phòng Lab Sáng Tạo</p>
            <h1 class="text-5xl md:text-6xl font-headline tracking-tight text-on-background leading-tight">
                Cộng Đồng <br/><span class="italic text-primary">Tri Thức &amp; Vẻ Đẹp</span>
            </h1>
        </div>
        <button onclick="document.getElementById('writePostModal').classList.remove('hidden')" class="bg-gradient-to-br from-primary to-primary-container text-on-primary px-8 py-4 rounded-DEFAULT font-label font-semibold flex items-center gap-3 hover:opacity-90 transition-all sunken-shadow" style="border-radius: 0.125rem;">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">add</span>
            TẠO BÀI VIẾT MỚI
        </button>
    </header>

    <!-- Modal Write Post -->
    <div id="writePostModal" class="fixed inset-0 z-[100] bg-black/60 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-surface-container-lowest w-full max-w-2xl p-8 rounded-lg shadow-2xl relative">
            <button onclick="document.getElementById('writePostModal').classList.add('hidden')" class="absolute top-4 right-4 text-secondary hover:text-primary">
                <span class="material-symbols-outlined">close</span>
            </button>
            <h2 class="text-2xl font-serif mb-2 text-on-background">Đăng tải thảo luận mới</h2>
            <p class="text-sm text-secondary mb-6">Chia sẻ kiến thức, mẹo hoặc câu hỏi để cộng đồng chuyên gia giúp đỡ.</p>
            
            <?php if(!isset($_SESSION['user_id'])): ?>
                <div class="bg-surface-container p-4 rounded font-medium text-center">
                    Bạn cần <a href="login.php" class="text-primary hover:underline">Đăng nhập</a> để đăng bài viết.
                </div>
            <?php else: ?>
            <form method="POST" action="community.php" class="space-y-4">
                <input type="hidden" name="action" value="submit_post">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Tiêu đề bài viết</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 bg-surface-container-low border border-outline/20 rounded focus:border-primary focus:ring-1 focus:ring-primary text-on-background" placeholder="VD: Review kem phục hồi da nhạy cảm mùa đông">
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Chủ đề</label>
                    <select name="category_id" required class="w-full px-4 py-2 bg-surface-container-low border border-outline/20 rounded focus:border-primary focus:ring-1 focus:ring-primary text-on-background">
                        <option value="">-- Chọn Chủ Đề --</option>
                        <?php foreach($community_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Nội dung</label>
                    <textarea name="content" required rows="6" class="w-full px-4 py-2 bg-surface-container-low border border-outline/20 rounded focus:border-primary focus:ring-1 focus:ring-primary text-on-background" placeholder="Chia sẻ chi tiết tại đây..."></textarea>
                </div>
                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-primary text-on-primary px-8 py-3 rounded text-sm font-bold tracking-widest uppercase hover:opacity-90 transition">ĐĂNG BÀI</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.getElementById('writePostModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    </script>

    <!-- Main Layout: Bento Style -->
    <div class="grid grid-cols-12 gap-8">
        <!-- Sidebar: Categories & Trending -->
        <aside class="col-span-12 lg:col-span-3 space-y-12">
            <!-- Search & Filters -->
            <section>
                <h3 class="font-label text-xs uppercase tracking-widest text-on-surface-variant mb-6">Khám Phá</h3>
                <div class="space-y-2">
                    <div class="flex items-center gap-4 p-3 bg-primary text-on-primary rounded-lg cursor-pointer">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">tag</span>
                        <span class="font-label text-sm">Tất cả chủ đề</span>
                    </div>
                    <?php foreach($community_cats as $cat): ?>
                    <div class="flex items-center gap-4 p-3 bg-surface-container-low rounded-lg group hover:bg-surface-container transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-primary">spa</span>
                        <span class="font-label text-sm text-on-surface"><?= htmlspecialchars($cat['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Trending Topics -->
            <section class="bg-surface-container-low p-8 rounded-lg">
                <h3 class="font-label text-xs uppercase tracking-widest text-on-surface-variant mb-6 italic">Xu Hướng Tuần Này</h3>
                <ul class="space-y-6">
                    <?php foreach($trending_topics as $trend): ?>
                    <li class="group cursor-pointer">
                        <p class="text-xs text-primary mb-1">🔥 Nổi bật</p>
                        <h4 class="font-headline text-lg group-hover:text-primary transition-colors leading-snug">
                            <a href="#"><?= htmlspecialchars($trend['title']) ?></a>
                        </h4>
                        <p class="text-xs text-secondary mt-2"><?= $trend['view_count'] ?> lượt xem</p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>

        <!-- Discussion Feed -->
        <section class="col-span-12 lg:col-span-9 space-y-8">
            <!-- Filter Chips -->
            <div class="flex gap-4 overflow-x-auto pb-4 no-scrollbar">
                <?php
                $tab_base_class = 'px-6 py-2 rounded-full font-label text-xs tracking-wider uppercase cursor-pointer transition-colors';
                $tab_active_class = 'bg-primary text-on-primary';
                $tab_inactive_class = 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest';
                ?>
                <a href="community.php?tab=latest" class="<?= $tab_base_class ?> <?= $active_tab === 'latest' ? $tab_active_class : $tab_inactive_class ?>">Mới Nhất</a>
                <a href="community.php?tab=popular" class="<?= $tab_base_class ?> <?= $active_tab === 'popular' ? $tab_active_class : $tab_inactive_class ?>">Phổ Biến</a>
                <a href="community.php?tab=saved" class="<?= $tab_base_class ?> <?= $active_tab === 'saved' ? $tab_active_class : $tab_inactive_class ?>">Đã Lưu</a>
            </div>

            <!-- Discussion Cards -->
            <div class="space-y-6">
                <?php foreach($posts as $post): ?>
                <article id="post-<?= $post['id'] ?>" class="bg-surface-container-lowest p-8 rounded-lg sunken-shadow border-l-2 <?= $post['is_trending'] ? 'border-primary' : 'border-primary-fixed hover:border-primary' ?> transition-all group">
                    <div class="flex justify-between items-start mb-6">
                        <div class="flex items-center gap-4">
                            <img class="w-12 h-12 rounded-full object-cover <?= $post['is_trending'] ? 'ring-2 ring-primary p-0.5' : 'grayscale hover:grayscale-0' ?> transition-all duration-500" src="<?= htmlspecialchars($post['avatar']) ?>"/>
                            <div>
                                <h5 class="font-label text-sm font-bold text-on-surface"><?= htmlspecialchars($post['user_name']) ?></h5>
                                <p class="text-xs text-secondary">
                                    Đã đăng trong <span class="text-primary-container font-semibold"><?= htmlspecialchars($post['category_name']) ?></span> • <?= date('d/m/Y H:i', strtotime($post['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <details class="relative">
                            <summary class="list-none cursor-pointer">
                                <span class="material-symbols-outlined text-outline-variant hover:text-primary">more_horiz</span>
                            </summary>
                            <div class="absolute right-0 mt-2 w-48 bg-surface-container-lowest border border-outline-variant/20 rounded-lg shadow-2xl z-30 overflow-hidden">
                                <?php if(isset($_SESSION['user_id'])): ?>
                                <form method="POST" action="community.php">
                                    <input type="hidden" name="action" value="toggle_save_post">
                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                    <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-left text-sm text-on-surface hover:bg-surface-container transition-colors">
                                        <span class="material-symbols-outlined text-base <?= !empty($post['is_saved']) ? 'text-primary' : 'text-secondary' ?>">bookmark</span>
                                        <?= !empty($post['is_saved']) ? 'Bỏ lưu bài viết' : 'Lưu bài viết' ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <a href="login.php" class="flex items-center gap-3 px-4 py-3 text-sm text-on-surface hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-base text-secondary">login</span>
                                    Đăng nhập để lưu
                                </a>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                    
                    <h2 class="text-2xl font-headline mb-4 group-hover:text-primary transition-colors leading-snug">
                        <a href="#"><?= htmlspecialchars($post['title']) ?></a>
                    </h2>
                    <p class="text-on-surface-variant line-clamp-2 mb-6 font-body leading-relaxed">
                        <?= htmlspecialchars($post['content']) ?>
                    </p>
                    
                    <div class="flex items-center justify-between pt-6 border-t border-surface-container">
                        <div class="flex items-center gap-6">
                            <details class="group/stat cursor-pointer">
                                <summary class="flex items-center gap-2 text-secondary hover:text-primary transition-colors list-none">
                                    <span class="material-symbols-outlined text-sm">chat_bubble</span>
                                    <span class="text-xs font-label">Bình luận (<?= $post['replies_count'] ?>)</span>
                                </summary>
                                
                                <div class="mt-4 w-full">
                                    <?php 
                                    $c_stmt = $pdo->query("SELECT c.*, u.name as user_name, u.avatar, u.role FROM community_post_comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = " . $post['id'] . " ORDER BY c.created_at ASC");
                                    $comments = $c_stmt->fetchAll();
                                    
                                    $total_comments = count($comments);
                                    ?>
                                    <?php if($total_comments > 0): ?>
                                    <div class="mb-4">
                                        <?php render_community_comments($comments, null, 0, $post['id']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                    <details class="cursor-pointer group mt-2 mb-6">
                                        <summary class="text-sm font-bold text-secondary hover:text-primary list-none flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">reply</span> Viết bình luận gốc (<?= $total_comments ?> bình luận)
                                        </summary>
                                        <form method="POST" action="community.php" class="mt-2 flex gap-2">
                                            <input type="hidden" name="action" value="reply_post">
                                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                            <input type="text" name="reply_content" required placeholder="Viết thảo luận mới cho bài này..." class="flex-1 text-sm border px-3 py-2 rounded focus:border-primary">
                                            <button type="submit" class="bg-primary text-on-primary px-4 py-2 font-bold text-xs rounded hover:opacity-90">GỬI</button>
                                        </form>
                                    </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                            
                            <form method="POST" action="community.php" class="inline">
                                <input type="hidden" name="action" value="like_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                                <button type="submit" class="flex items-center gap-2 <?= !empty($post['is_liked']) ? 'text-red-500' : 'text-secondary hover:text-red-500' ?> group/stat cursor-pointer transition-colors">
                                    <span class="material-symbols-outlined text-sm" <?= !empty($post['is_liked']) ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>favorite</span>
                                    <span class="text-xs font-label"><?= $post['likes_count'] ?> Yêu thích</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
                
                <?php if(empty($posts)): ?>
                    <p class="text-secondary text-center py-10">Chưa có bài viết nào.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
