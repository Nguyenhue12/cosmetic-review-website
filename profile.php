<?php
$active_page = 'profile';
$page_title = 'Trang Cá Nhân - The Editorial Muse';
require_once 'config/database.php';

// Auth enforcement
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $new_name = $_POST['name'] ?? '';
    // Optional password update
    $new_password = $_POST['password'] ?? '';
    
    if ($new_password) {
        $stmt_upd = $pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
        $stmt_upd->execute([$new_name, $new_password, $user_id]);
    } else {
        $stmt_upd = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt_upd->execute([$new_name, $user_id]);
    }
    $_SESSION['user_name'] = $new_name; // update session
    $success_msg = 'Cập nhật thành công!';
}

$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();
if (!$user) {
    session_unset();
    header("Location: login.php");
    exit;
}
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_avatar'] = $user['avatar'];

// Fetch User's reviews
$stmt_revs = $pdo->prepare("SELECT r.*, s.name as service_name, s.image_url, c.name as category_name 
                            FROM reviews r 
                            JOIN services s ON r.service_id = s.id 
                            LEFT JOIN categories c ON s.category_id = c.id
                            WHERE r.user_id = ? ORDER BY r.created_at DESC");
$stmt_revs->execute([$user_id]);
$reviews = $stmt_revs->fetchAll();

// Fetch User's activities (discussions, community comments, service review comments)
$activities = [];

// 1. Posts in Community
$stmt_discs = $pdo->prepare("SELECT id, title as target_name, content, created_at, 'post' as type, id as target_id FROM discussions WHERE user_id = ?");
$stmt_discs->execute([$user_id]);
$acts_discs = $stmt_discs->fetchAll();
foreach($acts_discs as $act) $activities[] = $act;

// 2. Comments in Community
$stmt_ccomments = $pdo->prepare("SELECT c.id, d.title as target_name, c.content, c.created_at, 'community_comment' as type, d.id as target_id 
                                 FROM community_post_comments c 
                                 JOIN discussions d ON c.post_id = d.id 
                                 WHERE c.user_id = ?");
$stmt_ccomments->execute([$user_id]);
$acts_ccomments = $stmt_ccomments->fetchAll();
foreach($acts_ccomments as $act) $activities[] = $act;

// 3. Comments in Services (review_comments)
$stmt_rcomments = $pdo->prepare("SELECT rc.id, s.name as target_name, rc.content, rc.created_at, 'service_comment' as type, s.id as target_id 
                                 FROM review_comments rc 
                                 JOIN reviews r ON rc.review_id = r.id 
                                 JOIN services s ON r.service_id = s.id 
                                 WHERE rc.user_id = ?");
$stmt_rcomments->execute([$user_id]);
$acts_rcomments = $stmt_rcomments->fetchAll();
foreach($acts_rcomments as $act) $activities[] = $act;

// Sort all activities by created_at DESC
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$review_count = count($reviews) + count($activities);

require_once 'includes/header.php';
?>

<main class="pt-32 pb-20 px-6 md:px-12 max-w-screen-xl mx-auto min-h-screen">
    <?php if($success_msg): ?>
        <div class="mb-4 bg-green-100 text-green-800 p-4 rounded-lg font-bold">
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Header Section -->
    <header class="flex flex-col md:flex-row items-center md:items-end gap-8 mb-20">
        <div class="relative">
            <div class="w-40 h-40 rounded-lg overflow-hidden ring-4 ring-surface-container-low shadow-lg bg-stone-200">
                <img alt="User avatar" class="w-full h-full object-cover" src="<?= htmlspecialchars($user['avatar']) ?>"/>
            </div>
            <?php if($user['role'] == 'expert' || $user['role'] == 'admin'): ?>
            <div class="absolute -bottom-3 -right-3 bg-primary-container text-on-primary-container px-4 py-1 rounded-full text-xs font-bold tracking-widest flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">verified</span>
                <?= $user['role'] == 'admin' ? 'ADMIN' : 'CHUYÊN GIA' ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="flex-1 text-center md:text-left">
            <h1 class="text-5xl font-headline tracking-tight text-on-background mb-2 italic">Trang cá nhân</h1>
            <p class="text-lg text-secondary font-body font-light">Chào mừng trở lại, <span class="font-semibold text-primary"><?= htmlspecialchars($user['name']) ?></span>. Atelier của bạn đã sẵn sàng.</p>
            
            <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-6">
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase tracking-widest text-outline font-bold">Vai trò</span>
                    <span class="text-2xl font-headline"><?= htmlspecialchars($user['role']) ?></span>
                </div>
                <div class="w-[1px] h-10 bg-outline-variant/30 hidden md:block"></div>
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase tracking-widest text-outline font-bold">Số lượng bài viết</span>
                    <span class="text-2xl font-headline"><?= $review_count ?></span>
                </div>
                <div class="w-[1px] h-10 bg-outline-variant/30 hidden md:block"></div>
            </div>
        </div>
        
        <div class="flex gap-4">
            <button onclick="document.getElementById('editProfileModal').classList.remove('hidden')" class="bg-gradient-to-br from-primary to-primary-container text-on-primary px-8 py-3 rounded-lg font-label text-sm font-semibold tracking-wide shadow-sm hover:opacity-90 transition-all">
                CHỈNH SỬA
            </button>
        </div>
    </header>

    <!-- Modal Edit Profile -->
    <div id="editProfileModal" class="fixed inset-0 z-[100] bg-black/60 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-surface-container-lowest w-full max-w-md p-8 rounded-lg shadow-2xl relative">
            <button onclick="document.getElementById('editProfileModal').classList.add('hidden')" class="absolute top-4 right-4 text-secondary hover:text-primary">
                <span class="material-symbols-outlined">close</span>
            </button>
            <h2 class="text-2xl font-serif mb-6 text-on-background">Thay đổi thông tin</h2>
            <form method="POST" action="profile.php" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Tên hiển thị</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full px-4 py-2 bg-surface-container-low border border-outline/20 rounded focus:border-primary focus:ring-1 focus:ring-primary text-on-background">
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Đổi mật khẩu mới (Bỏ trống nếu không đổi)</label>
                    <input type="password" name="password" class="w-full px-4 py-2 bg-surface-container-low border border-outline/20 rounded focus:border-primary focus:ring-1 focus:ring-primary text-on-background">
                </div>
                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-primary text-on-primary px-8 py-3 rounded text-sm font-bold tracking-widest uppercase hover:opacity-90 transition">LƯU THAY ĐỔI</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Script to close modal when clicking outside -->
    <script>
        document.getElementById('editProfileModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    </script>

    <!-- Dashboard Bento Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Sidebar: Profile Settings & Info -->
        <div class="lg:col-span-4 space-y-8">
            <section class="bg-surface-container-low p-8 rounded-xl">
                <h3 class="text-xs uppercase tracking-[0.2em] text-primary font-bold mb-8">CÀI ĐẶT TÀI KHOẢN</h3>
                <nav class="space-y-6">
                    <a class="flex items-center justify-between group cursor-pointer" onclick="document.getElementById('editProfileModal').classList.remove('hidden')">
                        <div class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors">person_outline</span>
                            <span class="text-sm font-medium">Thông tin cá nhân</span>
                        </div>
                        <span class="material-symbols-outlined text-sm text-outline-variant">chevron_right</span>
                    </a>
                </nav>
            </section>
        </div>

        <!-- Main Content: Reviews & Discussions -->
        <div class="lg:col-span-8 space-y-12">
            <!-- My Reviews Section -->
            <section>
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h2 class="text-3xl font-headline italic">Đánh giá của tôi</h2>
                        <div class="h-1 w-12 bg-primary-container mt-2"></div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($reviews as $rev): ?>
                    <div class="bg-surface-container-lowest p-6 group transition-all duration-300 shadow-sm border border-outline-variant/20">
                        <div class="flex items-start gap-4 mb-4">
                            <img alt="<?= htmlspecialchars($rev['service_name']) ?>" class="w-20 h-20 object-cover rounded-sm shadow-sm transition-transform group-hover:scale-105" src="<?= htmlspecialchars($rev['image_url']) ?>"/>
                            <div>
                                <span class="text-[10px] text-primary font-bold uppercase tracking-widest"><?= htmlspecialchars($rev['category_name']) ?></span>
                                <h4 class="font-headline text-lg leading-tight mt-1"><?= htmlspecialchars($rev['service_name']) ?></h4>
                                <div class="flex text-amber-500 mt-1">
                                    <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($rev['rating'] >= $i) {
                                                echo '<span class="material-symbols-outlined text-sm" style="font-variation-settings: \'FILL\' 1;">star</span>';
                                            } else {
                                                echo '<span class="material-symbols-outlined text-sm">star</span>';
                                            }
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-secondary line-clamp-3 leading-relaxed italic">"<?= nl2br(htmlspecialchars($rev['content'])) ?>"</p>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($reviews)): ?>
                        <p class="text-secondary text-sm">Bạn chưa có đánh giá nào.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- My Discussions Section -->
            <section>
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h2 class="text-3xl font-headline italic">Hoạt động cộng đồng</h2>
                        <div class="h-1 w-12 bg-primary-container mt-2"></div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <?php foreach($activities as $act): ?>
                    <?php
                        $act_title = '';
                        $act_link = '#';
                        $act_icon = '';
                        
                        if ($act['type'] == 'post') {
                            $act_title = 'Đã đăng bài thảo luận: <span class="font-bold text-primary">"' . htmlspecialchars($act['target_name']) . '"</span>';
                            $act_link = "community.php#post-" . $act['target_id'];
                            $act_icon = 'article';
                        } elseif ($act['type'] == 'community_comment') {
                            $act_title = 'Đã bình luận trong bài: <span class="font-bold text-primary">"' . htmlspecialchars($act['target_name']) . '"</span>';
                            $act_link = "community.php#post-" . $act['target_id'];
                            $act_icon = 'forum';
                        } elseif ($act['type'] == 'service_comment') {
                            $act_title = 'Đã phản hồi đánh giá cho dịch vụ: <span class="font-bold text-primary">"' . htmlspecialchars($act['target_name']) . '"</span>';
                            $act_link = "service_detail.php?id=" . $act['target_id'] . "#reviews-section";
                            $act_icon = 'chat_bubble';
                        }
                    ?>
                    <a href="<?= $act_link ?>" class="block bg-white border border-outline-variant/20 border-l-4 border-l-primary p-6 hover:bg-surface-container-low transition-colors shadow-sm rounded-r-lg group">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0 group-hover:bg-primary group-hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-sm"><?= $act_icon ?></span>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-secondary mb-1 flex items-center gap-2">
                                    <span class="font-label tracking-widest uppercase text-[10px]"><?= date('d M Y, H:i', strtotime($act['created_at'])) ?></span>
                                </div>
                                <h5 class="text-sm font-body text-on-background mb-2"><?= $act_title ?></h5>
                                <?php if(!empty($act['content'])): ?>
                                    <p class="text-sm text-on-surface-variant italic line-clamp-2 bg-surface-container-lowest p-3 rounded border border-outline/10 text-secondary">"<?= nl2br(htmlspecialchars($act['content'])) ?>"</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if(empty($activities)): ?>
                        <p class="text-secondary text-sm">Bạn chưa có hoạt động nào trong cộng đồng.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
