<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';

if (current_user() !== null) {
    redirect(dashboard_for_role(current_user()['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $query = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $query->execute([$email]);
    $user = $query->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        $error = 'The email or password was not recognized.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        redirect(dashboard_for_role($user['role']));
    }
}

page_start('Log in');
?>
<section class="auth-layout"><div class="auth-intro"><p class="eyebrow">WELCOME BACK</p><h1>Your chair<br><em>is waiting.</em></h1><p>Log in with your customer or shop staff account.</p></div>
    <form class="form-panel" method="post" action="login.php">
        <h2>Log in</h2>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <?= csrf_field() ?>
        <label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="username" required value="<?= h($_POST['email'] ?? '') ?>">
        <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button button-orange" type="submit">Log in</button>
        <p class="form-note">Need a customer account? <a href="register.php">Create one</a></p>
    </form>
</section>
<?php page_end(); ?>