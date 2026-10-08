<?php
$page_title = 'Đăng Ký - The Editorial Muse';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = 'Email này đã được sử dụng.';
    } else {
        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=random';
        // In reality, use password_hash
        $stmt_in = $pdo->prepare("INSERT INTO users (name, email, password, avatar, role, reputation) VALUES (?, ?, ?, ?, 'user', 0)");
        if ($stmt_in->execute([$name, $email, $password, $avatar])) {
            header("Location: login.php?registered=1");
            exit;
        } else {
            $error = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= htmlspecialchars($page_title) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,400;0,700;1,400&family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>body { font-family: 'Manrope', sans-serif; background-color: #fcf9f8; color: #1c1b1b; } h1, h2, .font-serif { font-family: 'Noto Serif', serif; }</style>
</head>
<body class="flex items-center justify-center min-h-screen bg-stone-50">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-sm border border-stone-200">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-serif italic text-stone-900 mb-2">The Editorial Muse</h1>
            <p class="text-stone-500 text-sm">Tạo tài khoản mới</p>
        </div>
        
        <?php if($error): ?>
        <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Tên hiển thị</label>
                <input type="text" name="name" required class="w-full px-4 py-2 border border-stone-300 rounded focus:ring-amber-700 focus:border-amber-700" placeholder="Tên của bạn">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-2 border border-stone-300 rounded focus:ring-amber-700 focus:border-amber-700" placeholder="your@email.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Mật khẩu</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-stone-300 rounded focus:ring-amber-700 focus:border-amber-700" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full bg-[#775a19] text-white py-3 rounded text-sm font-bold tracking-widest uppercase hover:opacity-90 transition">Đăng Ký</button>
        </form>
        
        <p class="text-center mt-6 text-sm text-stone-500">
            Đã có tài khoản? <a href="login.php" class="text-amber-700 hover:underline">Đăng nhập</a>
            <br> <a href="index.php" class="text-stone-400 hover:underline mt-2 inline-block">← Trở về trang chủ</a>
        </p>
    </div>
</body>
</html>
