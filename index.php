<?php
$active_page = 'home';
$page_title = 'Trang Chủ - The Editorial Muse';
require_once 'config/database.php';
require_once 'includes/service_post_functions.php';

// Lấy các bài đăng dịch vụ hàng đầu từ nguồn cấp dữ liệu cộng đồng
$stmt_services = $pdo->query("SELECT sp.*, c.name as category_name FROM service_posts sp LEFT JOIN categories c ON sp.category_id = c.id WHERE sp.status = 'active' ORDER BY (sp.likes_count + sp.comments_count + sp.shares_count) DESC, sp.created_at DESC LIMIT 3");
$featured_services = $stmt_services->fetchAll();

// Lấy các cuộc thảo luận đang thịnh hành nhất
$stmt_discussions = $pdo->query("SELECT d.*, u.name as user_name, u.avatar FROM discussions d JOIN users u ON d.user_id = u.id WHERE is_trending = 1 ORDER BY created_at DESC LIMIT 2");
$discussions = $stmt_discussions->fetchAll();

// Fetch top liked service post for spotlight
$stmt_spotlight = $pdo->query("SELECT sp.* FROM service_posts sp WHERE sp.status = 'active' ORDER BY sp.likes_count DESC LIMIT 1");
$spotlight_post = $stmt_spotlight->fetch();
$spotlight_media = $spotlight_post ? service_posts_fetch_media($pdo, $spotlight_post['id']) : [];
$spotlight_first_media = !empty($spotlight_media) ? $spotlight_media[0] : null;

require_once 'includes/header.php';
?>

<main>
    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center pt-20 overflow-hidden bg-surface-container-low">
        <div class="max-w-screen-2xl mx-auto px-12 w-full grid grid-cols-1 md:grid-cols-12 gap-12 items-center">
            <div class="md:col-span-6 z-10">
                <span class="inline-block label-md uppercase tracking-[0.2em] text-primary mb-6 font-semibold">TINH HOA LÀM ĐẸP</span>
                <h1 class="text-6xl md:text-8xl font-serif leading-[1.1] text-on-background mb-8 -ml-1">
                    Discover <br/>
                    <span class="italic text-primary-container">Beauty</span> <br/>
                    Excellence.
                </h1>
                <p class="text-lg text-secondary max-w-md mb-10 leading-relaxed">
                    Nơi hội tụ những đánh giá chuyên sâu và trải nghiệm xa xỉ về thế giới mỹ phẩm cao cấp dành cho những quý cô tinh tế.
                </p>
                <div class="flex items-center space-x-8">
                    <a href="services.php" class="bg-gradient-to-br from-primary to-primary-container text-on-primary px-10 py-4 rounded-lg hover:opacity-90 transition-all font-medium tracking-wide">
                        Khám phá ngay
                    </a>
                    <a href="community.php" class="text-primary hover:underline underline-offset-8 transition-all font-semibold flex items-center">
                        Xem bộ sưu tập
                        <span class="material-symbols-outlined ml-2" data-icon="arrow_right_alt">arrow_right_alt</span>
                    </a>
                </div>
            </div>
            <div class="md:col-span-6 relative h-[600px] md:h-[800px]">
                <div class="absolute inset-0 bg-surface-container-highest/20 rounded-full blur-3xl -z-10 transform translate-x-20"></div>
                <img alt="Luxury Cosmetics" class="w-full h-full object-cover rounded-lg shadow-2xl transform rotate-2 hover:rotate-0 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCZ6xSXekV2qSGu7Z0bmi9P4GM9z2O5KblcV-GcFb96Qtt4dAZ9HTzZxwqWjy6I9D0dzGlTAw0VQlfyaUmt_BCMD0PaRI6E7IkU_45vgmmJT8W2dPtIyKP3f1yBObR6C7_ADOaqhErAxMVDs7VONzAt4ygN8-EVNznVaOH6rzxUsBFDRucPs1mZos6wFwKdByvXWaGo9yS_B-yoPkR4_4DB7rIOC3CvWBOxFjG8T1LrqR3rXEGdnU1-KWl36eTQhngK3z1CKAEkTs4"/>
                <!-- Asymmetrical element -->
                <div class="absolute bottom-6 -left-10 p-8 bg-surface-container-lowest shadow-2xl max-w-xs rounded-lg hidden lg:block">
                    <p class="font-vietnamese-serif italic text-xl mb-2">"Vẻ đẹp bắt đầu từ sự tự tin."</p>
                    <p class="text-sm text-secondary uppercase tracking-widest">— Editorial Muse</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Service Posts -->
    <section class="py-32 px-12 max-w-screen-2xl mx-auto">
        <div class="flex justify-between items-end mb-20">
            <div class="max-w-xl">
                <h2 class="text-4xl font-vietnamese-serif italic mb-6 text-on-background">Dịch Vụ Đang Được Thảo Luận</h2>
                <p class="text-secondary leading-relaxed">Những dịch vụ mỹ phẩm được cộng đồng đăng, xem, bình luận và chia sẻ nhiều nhất.</p>
            </div>
            <div class="flex space-x-4">
                <button class="w-12 h-12 rounded-full border border-outline-variant/20 flex items-center justify-center hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button class="w-12 h-12 rounded-full border border-outline-variant/20 flex items-center justify-center hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
            <?php 
            $delay = 0;
            foreach ($featured_services as $service): 
                $translateClass = $delay == 1 ? 'transform md:translate-y-12' : '';
                $home_media = service_posts_fetch_media($pdo, $service['id']);
                $first_home_media = $home_media[0] ?? null;
            ?>
            <a href="service_detail.php?id=<?= $service['id'] ?>" class="group cursor-pointer block <?= $translateClass ?>">
                <div class="relative overflow-hidden mb-8 aspect-[4/5] rounded-lg">
                    <?php if($first_home_media && $first_home_media['media_type'] === 'video'): ?>
                        <video src="<?= htmlspecialchars($first_home_media['url']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" muted playsinline preload="metadata"></video>
                        <span class="material-symbols-outlined absolute inset-0 flex items-center justify-center text-white text-5xl drop-shadow">play_circle</span>
                    <?php else: ?>
                        <img alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="<?= htmlspecialchars(($first_home_media['url'] ?? $service['image_url']) ?: 'https://placehold.co/700x900/f0eded/775a19?text=The+Editorial+Muse') ?>"/>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-black/5 group-hover:bg-transparent transition-colors"></div>
                </div>
                <div class="pl-2 border-l-2 border-primary-fixed">
                    <span class="text-xs tracking-[0.2em] text-primary font-bold uppercase mb-2 block"><?= htmlspecialchars($service['location'] ?: ($service['category_name'] ?? 'Dịch vụ')) ?></span>
                    <h3 class="text-2xl font-vietnamese-serif mb-2 group-hover:text-primary transition-colors"><?= htmlspecialchars($service['title']) ?></h3>
                    <p class="text-secondary text-sm mb-4 line-clamp-3"><?= htmlspecialchars($service['content']) ?></p>
                    <div class="flex items-center gap-4 text-secondary text-xs font-bold">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">thumb_up</span><?= (int)$service['likes_count'] ?></span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">chat_bubble</span><?= (int)$service['comments_count'] ?></span>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">ios_share</span><?= (int)$service['shares_count'] ?></span>
                    </div>
                </div>
            </a>
            <?php 
            $delay++;
            endforeach; 
            ?>
        </div>
    </section>

    <!-- Latest Discussions: Community -->
    <section class="bg-surface-container-low py-32 px-12 overflow-hidden">
        <div class="max-w-screen-2xl mx-auto flex flex-col md:flex-row gap-20">
            <div class="md:w-1/3">
                <h2 class="text-4xl font-vietnamese-serif italic mb-8 text-on-background">Thảo Luận <br/> Mới Nhất</h2>
                <p class="text-secondary mb-10 leading-relaxed">Kết nối với cộng đồng những người yêu cái đẹp, chia sẻ bí quyết và giải đáp thắc mắc về các quy trình chăm sóc da.</p>
                <a href="community.php" class="inline-flex items-center space-x-3 text-primary group font-bold tracking-widest uppercase text-xs">
                    <span>Tham gia cộng đồng</span>
                    <span class="material-symbols-outlined group-hover:translate-x-2 transition-transform">arrow_forward</span>
                </a>
            </div>
            
            <div class="md:w-2/3 grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($discussions as $post): ?>
                <div class="bg-surface-container-lowest p-8 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-primary-fixed overflow-hidden">
                            <img alt="Avatar" src="<?= htmlspecialchars($post['avatar']) ?>"/>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold"><?= htmlspecialchars($post['user_name']) ?></h4>
                            <p class="text-[10px] text-secondary uppercase tracking-widest">Gần đây</p>
                        </div>
                    </div>
                    <a href="community.php" class="block">
                        <h3 class="text-lg font-vietnamese-serif mb-4 leading-snug hover:text-primary transition-colors"><?= htmlspecialchars($post['title']) ?></h3>
                    </a>
                    <p class="text-sm text-secondary line-clamp-3 mb-6"><?= htmlspecialchars($post['content']) ?></p>
                    <div class="flex items-center space-x-6 text-secondary text-xs">
                        <span class="flex items-center"><span class="material-symbols-outlined text-sm mr-1">chat_bubble_outline</span> <?= $post['replies_count'] ?> phản hồi</span>
                        <span class="flex items-center"><span class="material-symbols-outlined text-sm mr-1">favorite_border</span> <?= $post['likes_count'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Product Spotlight Component (Editorial Style) -->
                <?php if ($spotlight_post): ?>
                <div class="md:col-span-2 mt-12 bg-surface-container-lowest p-10 flex flex-col md:flex-row items-center gap-12 rounded-lg relative overflow-visible">
                    <div class="w-full md:w-1/2 -mt-20 md:mt-0 md:-ml-16">
                        <?php if(count($spotlight_media) > 1): ?>
                            <div class="grid grid-cols-2 gap-3">
                                <?php foreach(array_slice($spotlight_media, 0, 3) as $media): ?>
                                    <div class="relative overflow-hidden rounded-sm border-[12px] border-white bg-black/5 aspect-square">
                                        <?php if($media['media_type'] === 'video'): ?>
                                            <video src="<?= htmlspecialchars($media['url']) ?>" class="w-full h-full object-cover" muted playsinline preload="metadata"></video>
                                            <span class="material-symbols-outlined absolute inset-0 flex items-center justify-center text-white text-4xl drop-shadow">play_circle</span>
                                        <?php else: ?>
                                            <img alt="<?= htmlspecialchars($spotlight_post['title']) ?>" class="w-full h-full object-cover" src="<?= htmlspecialchars($media['url']) ?>"/>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <?php if($spotlight_first_media && $spotlight_first_media['media_type'] === 'video'): ?>
                                <video src="<?= htmlspecialchars($spotlight_first_media['url']) ?>" class="w-full aspect-square object-cover shadow-2xl rounded-sm border-[12px] border-white" muted playsinline preload="metadata"></video>
                                <span class="material-symbols-outlined absolute inset-0 flex items-center justify-center text-white text-5xl drop-shadow">play_circle</span>
                            <?php else: ?>
                                <img alt="<?= htmlspecialchars($spotlight_post['title']) ?>" class="w-full aspect-square object-cover shadow-2xl rounded-sm border-[12px] border-white" src="<?= htmlspecialchars(($spotlight_first_media['url'] ?? $spotlight_post['image_url']) ?: 'https://placehold.co/700x900/f0eded/775a19?text=The+Editorial+Muse') ?>"/>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="w-full md:w-1/2">
                        <span class="label-md uppercase tracking-[0.2em] text-primary-fixed-dim bg-primary/10 px-3 py-1 mb-4 inline-block font-bold">EDITOR'S CHOICE</span>
                        <h3 class="text-3xl font-serif mb-4"><?= htmlspecialchars($spotlight_post['title']) ?></h3>
                        <p class="text-secondary italic mb-6">"<?= htmlspecialchars(substr($spotlight_post['content'], 0, 150)) ?>..."</p>
                        <div class="flex items-center justify-between border-t border-outline-variant/10 pt-6">
                            <div class="flex items-center gap-4 text-secondary text-xs font-bold">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">thumb_up</span><?= (int)$spotlight_post['likes_count'] ?></span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">chat_bubble</span><?= (int)$spotlight_post['comments_count'] ?></span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">ios_share</span><?= (int)$spotlight_post['shares_count'] ?></span>
                            </div>
                            <a href="service_detail.php?id=<?= (int)$spotlight_post['id'] ?>" class="bg-primary text-on-primary px-6 py-3 rounded-lg text-sm font-medium hover:opacity-90 transition-opacity">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-24 px-12 text-center max-w-3xl mx-auto">
        <h2 class="text-3xl font-serif mb-6 italic">Join The Atelier</h2>
        <p class="text-secondary mb-12">Đăng ký nhận bản tin để không bỏ lỡ những đánh giá độc quyền và lời mời tham dự các sự kiện làm đẹp xa xỉ.</p>
        <form class="flex flex-col md:flex-row gap-4 items-center" onsubmit="handleSubscribe(event);">
            <input class="w-full bg-surface-container-low border-none focus:ring-1 focus:ring-primary py-4 px-6 text-on-background placeholder:text-stone-400 rounded-lg" placeholder="Email của bạn..." type="email" required/>
            <button class="w-full md:w-auto bg-stone-900 text-white px-10 py-4 rounded-lg hover:opacity-90 transition-all font-medium whitespace-nowrap" type="submit">Đăng ký</button>
        </form>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
