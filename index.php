<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

verify_csrf();

$route = $_GET['route'] ?? 'home';

try {
    match ($route) {
        'register' => page_register(),
        'login' => page_login(),
        'logout' => action_logout(),
        'profile' => page_profile(),
        'product' => page_product(),
        'cart' => page_cart(),
        'checkout' => page_checkout(),
        'orders' => page_orders(),
        'wishlist' => page_wishlist(),
        'seller' => page_seller_dashboard(),
        'seller_product' => page_seller_product(),
        'seller_orders' => page_seller_orders(),
        'admin' => page_admin_dashboard(),
        'admin_users' => page_admin_users(),
        'admin_categories' => page_admin_categories(),
        'admin_products' => page_admin_products(),
        'admin_orders' => page_admin_orders(),
        default => page_home(),
    };
} catch (PDOException $exception) {
    http_response_code(500);
    layout_header('Database error');
    echo '<div class="alert alert-danger">Database error. Run <code>setup.php</code> and confirm credentials in <code>config.php</code>.</div>';
    echo '<pre class="small text-muted">' . e($exception->getMessage()) . '</pre>';
    layout_footer();
}

function layout_header(string $title): void
{
    $user = current_user();
    $flash = flash();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | <?= APP_NAME ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/style.css" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">C2C Marketplace</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?route=cart">Cart (<?= cart_items_count() ?>)</a></li>
                    <?php if ($user): ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=orders">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=wishlist">Wishlist</a></li>
                        <?php if (in_array($user['role'], ['seller', 'admin'], true)): ?>
                            <li class="nav-item"><a class="nav-link" href="index.php?route=seller">Seller</a></li>
                        <?php endif; ?>
                        <?php if ($user['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="index.php?route=admin">Admin</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if ($user): ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=profile"><?= e($user['full_name']) ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=logout">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=login">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php?route=register">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container py-4">
        <?php if ($flash): ?>
            <div class="alert alert-info"><?= e($flash) ?></div>
        <?php endif; ?>
    <?php
}

function layout_footer(): void
{
    ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js"></script>
    </body>
    </html>
    <?php
}

function csrf_field(): string
{

    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';

}

function categories(bool $activeOnly = true): array
{

    $sql = 'SELECT * FROM categories' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
    return db()->query($sql)->fetchAll();

}

function product_image(?string $path, string $title): string
{

    if ($path) {
        return '<img class="card-img-top product-img" loading="lazy" src="' . e($path) . '" alt="' . e($title) . '">';
    }

    return '<div class="placeholder-img">No image</div>';

}

function product_card(array $product): void
{

    ?>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
            <?= product_image($product['image_path'] ?? null, $product['title']) ?>
            <div class="card-body d-flex flex-column">
                <div class="small text-muted mb-1"><?= e($product['category_name'] ?? 'Uncategorised') ?></div>
                <h2 class="h6"><a class="text-decoration-none text-dark" href="index.php?route=product&id=<?= (int) $product['id'] ?>"><?= e($product['title']) ?></a></h2>
                <div class="price mb-2"><?= money($product['price']) ?></div>
                <div class="small text-muted mb-3"><?= (int) $product['stock_quantity'] ?> in stock</div>
                <a class="btn btn-success btn-sm mt-auto" href="index.php?route=product&id=<?= (int) $product['id'] ?>">View product</a>
            </div>
        </div>
    </div>
    <?php

}

function upload_image(?string $existing = null): ?string
{

    if (empty($_FILES['image']['name'])) {
        return $existing;
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['image']['tmp_name']);
    if (!isset($allowed[$mime])) {
        flash('Only JPG, PNG, and WEBP images are supported.');
        return $existing;
    }

    $name = 'uploads/' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $name);

    return $name;

}

function page_home(): void
{

    $q = trim($_GET['q'] ?? '');
    $category = (int) ($_GET['category'] ?? 0);
    $sort = $_GET['sort'] ?? 'newest';
    $where = ['p.is_active = 1', 'c.is_active = 1'];
    $params = [];

    if ($q !== '') {
        $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($category > 0) {
        $where[] = 'p.category_id = ?';
        $params[] = $category;
    }

    $order = match ($sort) {
        'price_asc' => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        default => 'p.created_at DESC',
    };

    $stmt = db()->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE " . implode(' AND ', $where) . " ORDER BY $order LIMIT 48");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    layout_header('Shop');
    ?>
    <section class="hero p-4 p-md-5 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-6 fw-bold">Buy and sell locally in South Africa</h1>
                <p class="lead mb-0">Simple C2C trading with ZAR pricing, seller stock management, and simulated EFT or Cash on Collection checkout.</p>
            </div>
            <div class="col-lg-5">
                <form class="card card-body" method="get">
                    <input type="hidden" name="route" value="home">
                    <label class="form-label" for="q">Search marketplace</label>
                    <input class="form-control mb-2" id="q" name="q" value="<?= e($q) ?>" placeholder="Search products">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <select class="form-select" name="category">
                                <option value="0">All categories</option>
                                <?php foreach (categories() as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>" <?= selected((string) $category, (string) $cat['id']) ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <select class="form-select" name="sort">
                                <option value="newest" <?= selected($sort, 'newest') ?>>Newest</option>
                                <option value="price_asc" <?= selected($sort, 'price_asc') ?>>Price Low-High</option>
                                <option value="price_desc" <?= selected($sort, 'price_desc') ?>>Price High-Low</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-success mt-3">Search</button>
                </form>
            </div>
        </div>
    </section>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Latest products</h2>
        <span class="text-muted small"><?= count($products) ?> result(s)</span>
    </div>
    <div class="row g-3">
        <?php foreach ($products as $product) product_card($product); ?>
        <?php if (!$products): ?>
            <p class="text-muted">No products found.</p>
        <?php endif; ?>
    </div>
    <?php
    layout_footer();

}

function page_register(): void
{

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $role = $_POST['role'] === 'seller' ? 'seller' : 'buyer';
        $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash, role, province) VALUES (?, ?, ?, ?, ?)');
        try {

            $stmt->execute([trim($_POST['full_name']), strtolower(trim($_POST['email'])), password_hash($_POST['password'], PASSWORD_BCRYPT), $role, $_POST['province'] ?: null]);
            flash('Registration successful. Please log in.');
            redirect('index.php?route=login');
        } catch (PDOException) {
            flash('Email address is already registered.');
        }
    }

    layout_header('Register');
    ?>
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card card-body">
                <h1 class="h4 mb-3">Create account</h1>
                <form method="post">
                    <?= csrf_field() ?>
                    <label class="form-label">Full name</label>
                    <input class="form-control mb-2" name="full_name" required>
                    <label class="form-label">Email</label>
                    <input class="form-control mb-2" type="email" name="email" required>
                    <label class="form-label">Password</label>
                    <input class="form-control mb-2" type="password" name="password" minlength="8" required>
                    <label class="form-label">Role</label>
                    <select class="form-select mb-2" name="role">
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                    </select>
                    <label class="form-label">Province</label>
                    <select class="form-select mb-3" name="province">
                        <option value="">Select province</option>
                        <?php foreach (PROVINCES as $province): ?>
                            <option><?= e($province) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success w-100">Register</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    layout_footer();

}

function page_login(): void
{

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($_POST['email']))]);
        $user = $stmt->fetch();
        if ($user && password_verify($_POST['password'], $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            flash('Welcome back.');
            redirect('index.php');
        }

        flash('Invalid email or password.');

    }

    layout_header('Login');
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card card-body">
                <h1 class="h4 mb-3">Login</h1>
                <form method="post">
                    <?= csrf_field() ?>
                    <label class="form-label">Email</label>
                    <input class="form-control mb-2" type="email" name="email" required>
                    <label class="form-label">Password</label>
                    <input class="form-control mb-3" type="password" name="password" required>
                    <button class="btn btn-success w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    layout_footer();


}

function action_logout(): void
{
    unset($_SESSION['user_id']);
    flash('You have been logged out.');
    redirect('index.php');


}

function page_profile(): void
{



    $user = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = db()->prepare('UPDATE users SET full_name = ?, phone = ?, address = ?, city = ?, province = ?, postal_code = ? WHERE id = ?');
        $stmt->execute([trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['address']), trim($_POST['city']), $_POST['province'], trim($_POST['postal_code']), $user['id']]);
        flash('Profile updated.');
        redirect('index.php?route=profile');
    }

    layout_header('Profile');
    ?>
    <div class="card card-body">
        <h1 class="h4 mb-3">Profile</h1>
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label">Full name</label>
                <input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= e($user['phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">City</label>
                <input class="form-control" name="city" value="<?= e($user['city']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Shipping address</label>
                <textarea class="form-control" name="address" rows="3"><?= e($user['address']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Province</label>
                <select class="form-select" name="province">
                    <?php foreach (PROVINCES as $province): ?>
                        <option <?= selected((string) $user['province'], $province) ?>><?= e($province) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Postal code</label>
                <input class="form-control" name="postal_code" value="<?= e($user['postal_code']) ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-success">Save profile</button>
            </div>
        </form>
    </div>
    <?php
    layout_footer();



}

function page_product(): void
{

    $id = (int) ($_GET['id'] ?? 0);
    $user = current_user();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'cart';
        if ($action === 'cart') {
            $qty = max(1, (int) $_POST['quantity']);
            $stmt = db()->prepare('SELECT stock_quantity FROM products WHERE id = ? AND is_active = 1');
            $stmt->execute([$id]);
            $stock = (int) ($stmt->fetchColumn() ?: 0);
            if ($stock < 1) {
                flash('This product is out of stock.');
            } else {
                $_SESSION['cart'][$id] = min(($_SESSION['cart'][$id] ?? 0) + $qty, $stock);
                flash('Product added to cart.');
            }
        }
        if ($action === 'wishlist') {
            $user = require_login();
            $stmt = db()->prepare('SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$user['id'], $id]);
            if ($stmt->fetchColumn()) {
                db()->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?')->execute([$user['id'], $id]);
                flash('Removed from wishlist.');
            } else {
                db()->prepare('INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)')->execute([$user['id'], $id]);
                flash('Added to wishlist.');
            }
        }
        if ($action === 'review') {
            $user = require_login();
            $stmt = db()->prepare('SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.buyer_id = ? AND oi.product_id = ? AND o.status <> "Cancelled"');
            $stmt->execute([$user['id'], $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $stmt = db()->prepare('INSERT INTO reviews (product_id, buyer_id, rating, review_text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text), created_at = CURRENT_TIMESTAMP');
                $stmt->execute([$id, $user['id'], max(1, min(5, (int) $_POST['rating'])), trim($_POST['review_text'])]);
                flash('Review saved.');
            } else {
                flash('Only buyers who purchased this product can review it.');
            }
        }
        redirect('index.php?route=product&id=' . $id);
    }

    $stmt = db()->prepare('SELECT p.*, c.name AS category_name, u.full_name AS seller_name, COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.seller_id LEFT JOIN reviews r ON r.product_id = p.id WHERE p.id = ? GROUP BY p.id');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product || (!$product['is_active'] && (!$user || !in_array($user['role'], ['admin', 'seller'], true)))) {
        http_response_code(404);
        exit('Product not found');
    }

    $stmt = db()->prepare('SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.buyer_id WHERE r.product_id = ? ORDER BY r.created_at DESC');
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll();
    $canReview = false;
    if ($user) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.buyer_id = ? AND oi.product_id = ? AND o.status <> "Cancelled"');
        $stmt->execute([$user['id'], $id]);
        $canReview = (int) $stmt->fetchColumn() > 0;
    }

    layout_header($product['title']);
    ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <?= product_image($product['image_path'], $product['title']) ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-body">
                <div class="text-muted mb-1"><?= e($product['category_name']) ?></div>
                <h1 class="h3"><?= e($product['title']) ?></h1>
                <div class="price fs-4 mb-2"><?= money($product['price']) ?></div>
                <p class="mb-1"><strong>Stock:</strong> <?= (int) $product['stock_quantity'] ?></p>
                <p class="mb-1"><strong>Seller:</strong> <?= e($product['seller_name']) ?></p>
                <p class="mb-3"><strong>Rating:</strong> <?= number_format((float) $product['avg_rating'], 1) ?>/5 from <?= (int) $product['review_count'] ?> review(s)</p>
                <p><?= nl2br(e($product['description'])) ?></p>
                <form method="post" class="d-flex gap-2 mb-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cart">
                    <input class="form-control" type="number" name="quantity" min="1" max="<?= (int) $product['stock_quantity'] ?>" value="1" style="max-width: 110px">
                    <button class="btn btn-success" <?= (int) $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>Add to cart</button>
                </form>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="wishlist">
                    <button class="btn btn-outline-success btn-sm">Toggle wishlist</button>
                </form>
            </div>
        </div>
    </div>
    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card card-body">
                <h2 class="h5">Reviews</h2>
                <?php foreach ($reviews as $review): ?>
                    <div class="border-top py-3">
                        <strong><?= (int) $review['rating'] ?>/5</strong> by <?= e($review['full_name']) ?>
                        <p class="mb-0"><?= nl2br(e($review['review_text'])) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$reviews): ?>
                    <p class="text-muted mb-0">No reviews yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-body">
                <h2 class="h5">Leave a review</h2>
                <?php if ($canReview): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="review">
                        <label class="form-label">Rating</label>
                        <select class="form-select mb-2" name="rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>"><?= $i ?> star(s)</option>
                            <?php endfor; ?>
                        </select>
                        <label class="form-label">Review</label>
                        <textarea class="form-control mb-1" name="review_text" required></textarea>
                        <div class="text-muted small mb-3" data-word-count-for="review_text">0 words</div>
                        <button class="btn btn-success">Submit review</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted mb-0">Purchase this product before reviewing it.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    layout_footer();


}

function cart_products(): array
{

    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT p.*, u.full_name AS seller_name FROM products p JOIN users u ON u.id = p.seller_id WHERE p.id IN ($placeholders) AND p.is_active = 1");
    $stmt->execute($ids);
    $items = [];
    foreach ($stmt->fetchAll() as $product) {
        $product['cart_quantity'] = min((int) $cart[$product['id']], (int) $product['stock_quantity']);
        $items[] = $product;
    }
    return $items;
    
}

function page_cart(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST['qty'] ?? [] as $id => $qty) {
            $qty = max(0, (int) $qty);
            if ($qty === 0) {
                unset($_SESSION['cart'][(int) $id]);
            } else {
                $_SESSION['cart'][(int) $id] = $qty;
            }
        }
        flash('Cart updated.');
        redirect('index.php?route=cart');
    }

    $items = cart_products();
    $total = array_reduce($items, fn($sum, $item) => $sum + ((float) $item['price'] * (int) $item['cart_quantity']), 0.0);
    layout_header('Cart');
    ?>
    <h1 class="h4 mb-3">Shopping cart</h1>
    <form method="post" class="card card-body">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= e($item['title']) ?>
                                <div class="small text-muted">Seller: <?= e($item['seller_name']) ?></div>
                            </td>
                            <td><?= money($item['price']) ?></td>
                            <td><input class="form-control" type="number" name="qty[<?= (int) $item['id'] ?>]" value="<?= (int) $item['cart_quantity'] ?>" min="0" max="<?= (int) $item['stock_quantity'] ?>" style="max-width: 100px"></td>
                            <td><?= money((float) $item['price'] * (int) $item['cart_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$items): ?>
            <p class="text-muted">Your cart is empty.</p>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center">
            <strong>Total: <?= money($total) ?></strong>
            <div>
                <button class="btn btn-outline-secondary">Update cart</button>
                <?php if ($items): ?>
                    <a class="btn btn-success" href="index.php?route=checkout">Checkout</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <?php
    layout_footer();
}

function page_checkout(): void
{
    $user = require_login();
    $items = cart_products();
    if (!$items) {
        flash('Your cart is empty.');
        redirect('index.php?route=cart');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payment = in_array($_POST['payment_method'], ['EFT', 'Cash on Collection'], true) ? $_POST['payment_method'] : 'EFT';
        $address = trim($_POST['delivery_address']);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $locked = [];
            $total = 0.0;
            foreach ($_SESSION['cart'] as $productId => $qty) {
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
                $stmt->execute([(int) $productId]);
                $product = $stmt->fetch();
                if (!$product || (int) $product['stock_quantity'] < (int) $qty) {
                    throw new RuntimeException('Stock changed. Please update your cart.');
                }
                $locked[] = [$product, (int) $qty];
                $total += (float) $product['price'] * (int) $qty;
            }
            $orderNo = 'C2C' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('INSERT INTO orders (order_number, buyer_id, payment_method, delivery_address, total_amount) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$orderNo, $user['id'], $payment, $address, $total]);
            $orderId = (int) $pdo->lastInsertId();
            foreach ($locked as [$product, $qty]) {
                $pdo->prepare('INSERT INTO order_items (order_id, product_id, seller_id, title_snapshot, price_snapshot, quantity) VALUES (?, ?, ?, ?, ?, ?)')->execute([$orderId, $product['id'], $product['seller_id'], $product['title'], $product['price'], $qty]);
                $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?')->execute([$qty, $product['id']]);
            }
            $pdo->commit();
            unset($_SESSION['cart']);
            flash('Checkout successful. Order ID: ' . $orderNo);
            redirect('index.php?route=orders');
        } catch (Throwable $exception) {
            $pdo->rollBack();
            flash($exception->getMessage());
            redirect('index.php?route=cart');
        }
    }

    $defaultAddress = trim(($user['address'] ?? '') . "\n" . ($user['city'] ?? '') . ' ' . ($user['province'] ?? '') . ' ' . ($user['postal_code'] ?? ''));
    $total = array_reduce($items, fn($sum, $item) => $sum + ((float) $item['price'] * (int) $item['cart_quantity']), 0.0);
    layout_header('Checkout');
    ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card card-body">
                <h1 class="h4">Checkout</h1>
                <form method="post">
                    <?= csrf_field() ?>
                    <label class="form-label">Payment method</label>
                    <select class="form-select mb-3" name="payment_method">
                        <option>EFT</option>
                        <option>Cash on Collection</option>
                    </select>
                    <label class="form-label">Delivery address</label>
                    <textarea class="form-control mb-3" name="delivery_address" rows="5" required><?= e($defaultAddress) ?></textarea>
                    <button class="btn btn-success">Place order</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-body">
                <h2 class="h5">Order summary</h2>
                <?php foreach ($items as $item): ?>
                    <div class="d-flex justify-content-between border-top py-2">
                        <span><?= e($item['title']) ?> x <?= (int) $item['cart_quantity'] ?></span>
                        <strong><?= money((float) $item['price'] * (int) $item['cart_quantity']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="d-flex justify-content-between fs-5 border-top pt-3">
                    <strong>Total</strong>
                    <strong><?= money($total) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <?php
    layout_footer();
}

function page_orders(): void
{
    $user = require_login();
    $stmt = db()->prepare('SELECT * FROM orders WHERE buyer_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll();
    layout_header('My orders');
    ?>
    <h1 class="h4 mb-3">My orders</h1>
    <?php
    foreach ($orders as $order) {
        $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt->execute([$order['id']]);
        ?>
        <div class="card card-body mb-3">
            <div class="d-flex justify-content-between">
                <h2 class="h5"><?= e($order['order_number']) ?></h2>
                <span class="badge bg-secondary"><?= e($order['status']) ?></span>
            </div>
            <p class="mb-1">Payment: <?= e($order['payment_method']) ?></p>
            <p class="mb-1">Delivery: <?= nl2br(e($order['delivery_address'])) ?></p>
            <p><strong>Total: <?= money($order['total_amount']) ?></strong></p>
            <div class="table-responsive">
                <table class="table">
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($stmt->fetchAll() as $item): ?>
                        <tr>
                            <td><?= e($item['title_snapshot']) ?></td>
                            <td><?= (int) $item['quantity'] ?></td>
                            <td><?= e($item['fulfillment_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }
    if (!$orders) {
        ?>
        <p class="text-muted">No orders yet.</p>
        <?php
    }
    layout_footer();
}

function page_wishlist(): void
{
    $user = require_login();
    $stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM wishlist w JOIN products p ON p.id = w.product_id JOIN categories c ON c.id = p.category_id WHERE w.user_id = ? ORDER BY w.created_at DESC');
    $stmt->execute([$user['id']]);
    $products = $stmt->fetchAll();
    layout_header('Wishlist');
    ?>
    <h1 class="h4 mb-3">Wishlist</h1>
    <div class="row g-3">
    <?php
    foreach ($products as $product) product_card($product);
    ?>
    </div>
    <?php
    if (!$products) {
        ?>
        <p class="text-muted">Your wishlist is empty.</p>
        <?php
    }
    layout_footer();
}

function page_seller_dashboard(): void
{
    $user = require_role(['seller', 'admin']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int) $_POST['product_id'];
        $where = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND seller_id = ?';
        $params = $user['role'] === 'admin' ? [$id] : [$id, $user['id']];
        if ($_POST['action'] === 'toggle') {
            db()->prepare("UPDATE products SET is_active = 1 - is_active WHERE $where")->execute($params);
            flash('Listing status updated.');
        }
        if ($_POST['action'] === 'delete') {
            db()->prepare("DELETE FROM products WHERE $where")->execute($params);
            flash('Listing deleted.');
        }
        redirect('index.php?route=seller');
    }

    if ($user['role'] === 'admin') {
        $products = db()->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
    } else {
        $stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.seller_id = ? ORDER BY p.created_at DESC');
        $stmt->execute([$user['id']]);
        $products = $stmt->fetchAll();
    }

    layout_header('Seller dashboard');
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Seller dashboard</h1>
        <div>
            <a class="btn btn-outline-success" href="index.php?route=seller_orders">Orders</a>
            <a class="btn btn-success" href="index.php?route=seller_product">Add product</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= e($product['title']) ?></td>
                        <td><?= e($product['category_name']) ?></td>
                        <td><?= money($product['price']) ?></td>
                        <td><?= (int) $product['stock_quantity'] ?></td>
                        <td><?= $product['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="index.php?route=seller_product&id=<?= (int) $product['id'] ?>">Edit</a>
                            <form class="d-inline" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="toggle">Toggle</button>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('Delete this listing?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$products): ?>
        <p class="text-muted">No listings yet.</p>
    <?php endif; ?>
    <?php
    layout_footer();
}

function page_seller_product(): void
{
    $user = require_role(['seller', 'admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $product = ['title' => '', 'description' => '', 'price' => '', 'stock_quantity' => 0, 'category_id' => '', 'image_path' => null, 'is_active' => 1];
    if ($id) {
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            exit('Product not found');
        }
        if (!$product || ($user['role'] !== 'admin' && (int) $product['seller_id'] !== (int) $user['id'])) {
            http_response_code(403);
            exit('Access denied');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $image = upload_image($product['image_path'] ?? null);
        if ($id) {
            db()->prepare('UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, stock_quantity = ?, image_path = ?, is_active = ? WHERE id = ?')->execute([(int) $_POST['category_id'], trim($_POST['title']), trim($_POST['description']), (float) $_POST['price'], (int) $_POST['stock_quantity'], $image, isset($_POST['is_active']) ? 1 : 0, $id]);
            flash('Product updated.');
        } else {
            db()->prepare('INSERT INTO products (seller_id, category_id, title, description, price, stock_quantity, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'], (int) $_POST['category_id'], trim($_POST['title']), trim($_POST['description']), (float) $_POST['price'], (int) $_POST['stock_quantity'], $image, isset($_POST['is_active']) ? 1 : 0]);
            flash('Product created.');
        }
        redirect('index.php?route=seller');
    }

    layout_header($id ? 'Edit product' : 'Add product');
    ?>
    <div class="card card-body">
        <h1 class="h4 mb-3"><?= $id ? 'Edit product' : 'Add product' ?></h1>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-8">
                <label class="form-label">Title</label>
                <input class="form-control" name="title" value="<?= e($product['title']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" required>
                    <?php foreach (categories() as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= selected((string) $product['category_id'], (string) $cat['id']) ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Price (ZAR)</label>
                <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= e((string) $product['price']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Stock quantity</label>
                <input class="form-control" type="number" min="0" name="stock_quantity" value="<?= (int) $product['stock_quantity'] ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control mb-1" name="description" rows="5" required><?= e($product['description']) ?></textarea>
                <div class="text-muted small" data-word-count-for="description">0 words</div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Product image</label>
                <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="active" <?= checked((bool) $product['is_active']) ?>>
                    <label class="form-check-label" for="active">Active listing</label>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-success">Save product</button>
            </div>
        </form>
    </div>
    <?php
    layout_footer();
}

function page_seller_orders(): void
{
    $user = require_role(['seller', 'admin']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $allowed = ['Pending', 'Processing', 'Completed', 'Cancelled'];
        $status = in_array($_POST['status'], $allowed, true) ? $_POST['status'] : 'Pending';
        $where = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND seller_id = ?';
        $params = $user['role'] === 'admin' ? [$status, (int) $_POST['item_id']] : [$status, (int) $_POST['item_id'], $user['id']];
        db()->prepare("UPDATE order_items SET fulfillment_status = ? WHERE $where")->execute($params);
        flash('Fulfillment status updated.');
        redirect('index.php?route=seller_orders');
    }

    $sql = 'SELECT oi.*, o.order_number, o.payment_method, o.delivery_address, o.created_at, u.full_name AS buyer_name FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN users u ON u.id = o.buyer_id';
    $params = [];
    if ($user['role'] !== 'admin') {
        $sql .= ' WHERE oi.seller_id = ?';
        $params[] = $user['id'];
    }
    $sql .= ' ORDER BY o.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    layout_header('Seller orders');
    ?>
    <h1 class="h4 mb-3">Seller orders</h1>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Buyer</th>
                    <th>Item</th>
                    <th>Payment</th>
                    <th>Delivery</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['order_number']) ?></td>
                        <td><?= e($item['buyer_name']) ?></td>
                        <td><?= e($item['title_snapshot']) ?> x <?= (int) $item['quantity'] ?></td>
                        <td><?= e($item['payment_method']) ?></td>
                        <td><?= nl2br(e($item['delivery_address'])) ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <select class="form-select form-select-sm" name="status">
                                    <?php foreach (['Pending', 'Processing', 'Completed', 'Cancelled'] as $status): ?>
                                        <option <?= selected($item['fulfillment_status'], $status) ?>><?= $status ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-success">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$items): ?>
        <p class="text-muted">No seller orders yet.</p>
    <?php endif; ?>
    <?php
    layout_footer();
}

function page_admin_dashboard(): void
{
    require_role('admin');
    $stats = [
        'Total users' => db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'Total orders' => db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'Total products' => db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'Total revenue' => money(db()->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status <> "Cancelled"')->fetchColumn()),
    ];
    layout_header('Admin dashboard');
    ?>
    <h1 class="h4 mb-3">Admin dashboard</h1>
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $label => $value): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card card-body">
                    <div class="text-muted"><?= e($label) ?></div>
                    <div class="fs-4 fw-bold"><?= e((string) $value) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success" href="index.php?route=admin_users">Manage users</a>
        <a class="btn btn-success" href="index.php?route=admin_categories">Manage categories</a>
        <a class="btn btn-success" href="index.php?route=admin_products">Moderate products</a>
        <a class="btn btn-success" href="index.php?route=admin_orders">View orders</a>
    </div>
    <?php
    layout_footer();
}

function page_admin_users(): void
{
    $admin = require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $role = in_array($_POST['role'], ['buyer', 'seller', 'admin'], true) ? $_POST['role'] : 'buyer';
        if ((int) $_POST['user_id'] !== (int) $admin['id']) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, (int) $_POST['user_id']]);
            flash('User role updated.');
        }
        redirect('index.php?route=admin_users');
    }
    $users = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
    layout_header('Manage users');
    ?>
    <h1 class="h4 mb-3">Manage users</h1>
    <div class="table-responsive">
        <table class="table align-middle">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Province</th>
                <th></th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['full_name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e($user['role']) ?></td>
                    <td><?= e($user['province']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <select class="form-select form-select-sm" name="role">
                                <?php foreach (['buyer', 'seller', 'admin'] as $role): ?>
                                    <option <?= selected($user['role'], $role) ?>><?= $role ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-success" <?= (int) $user['id'] === (int) $admin['id'] ? 'disabled' : '' ?>>Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    layout_footer();
}

function page_admin_categories(): void
{
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['action'] === 'add' && trim($_POST['name']) !== '') db()->prepare('INSERT IGNORE INTO categories (name) VALUES (?)')->execute([trim($_POST['name'])]);
        if ($_POST['action'] === 'toggle') db()->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?')->execute([(int) $_POST['category_id']]);
        flash('Category updated.');
        redirect('index.php?route=admin_categories');
    }
    layout_header('Manage categories');
    ?>
    <h1 class="h4 mb-3">Manage categories</h1>
    <form method="post" class="card card-body mb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="input-group">
            <input class="form-control" name="name" placeholder="New category">
            <button class="btn btn-success">Add</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table">
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach (categories(false) as $cat): ?>
                <tr>
                    <td><?= e($cat['name']) ?></td>
                    <td><?= $cat['is_active'] ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary">Toggle</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    layout_footer();
}

function page_admin_products(): void
{
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['action'] === 'toggle') db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([(int) $_POST['product_id']]);
        if ($_POST['action'] === 'feature') db()->prepare('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?')->execute([(int) $_POST['product_id']]);
        flash('Product moderation updated.');
        redirect('index.php?route=admin_products');
    }
    $products = db()->query('SELECT p.*, u.full_name AS seller_name, c.name AS category_name FROM products p JOIN users u ON u.id = p.seller_id JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
    layout_header('Moderate products');
    ?>
    <h1 class="h4 mb-3">Moderate products</h1>
    <div class="table-responsive">
        <table class="table align-middle">
            <tr>
                <th>Product</th>
                <th>Seller</th>
                <th>Category</th>
                <th>Status</th>
                <th>Featured</th>
                <th></th>
            </tr>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= e($product['title']) ?></td>
                    <td><?= e($product['seller_name']) ?></td>
                    <td><?= e($product['category_name']) ?></td>
                    <td><?= $product['is_active'] ? 'Active' : 'Inactive' ?></td>
                    <td><?= $product['is_featured'] ? 'Yes' : 'No' ?></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" name="action" value="toggle">Toggle active</button>
                            <button class="btn btn-sm btn-outline-success" name="action" value="feature">Feature</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    layout_footer();
}

function page_admin_orders(): void
{
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $allowed = ['Pending', 'Processing', 'Completed', 'Cancelled'];
        $status = in_array($_POST['status'], $allowed, true) ? $_POST['status'] : 'Pending';
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, (int) $_POST['order_id']]);
        flash('Order status updated.');
        redirect('index.php?route=admin_orders');
    }
    $orders = db()->query('SELECT o.*, u.full_name AS buyer_name FROM orders o JOIN users u ON u.id = o.buyer_id ORDER BY o.created_at DESC')->fetchAll();
    layout_header('All orders');
    ?>
    <h1 class="h4 mb-3">All orders and transactions</h1>
    <div class="table-responsive">
        <table class="table align-middle">
            <tr>
                <th>Order</th>
                <th>Buyer</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= e($order['order_number']) ?></td>
                    <td><?= e($order['buyer_name']) ?></td>
                    <td><?= e($order['payment_method']) ?></td>
                    <td><?= money($order['total_amount']) ?></td>
                    <td><?= e($order['status']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['Pending', 'Processing', 'Completed', 'Cancelled'] as $status): ?>
                                    <option <?= selected($order['status'], $status) ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-success">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (!$orders): ?>
        <p class="text-muted">No orders yet.</p>
    <?php endif; ?>
    <?php
    layout_footer();
}
