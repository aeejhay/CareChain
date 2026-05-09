<?php
$pageTitle = 'Login';
require_once 'includes/header.php';

if (isLoggedIn()) redirect('/carechain/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        
        flash('success', 'Welcome back!');
        redirect('/carechain/dashboard.php');
    } else {
        $errors[] = 'Invalid email or password';
    }
}
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="subtitle">Log in to your CareChain account</p>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= $e ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Log In</button>
        </form>
        
        <div class="auth-footer">
            Don't have an account? <a href="/carechain/register.php">Sign up</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
