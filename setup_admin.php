<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';

$adminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCount > 0) {
    flash('error', 'An owner account already exists. Please log in.');
    redirect('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $error = 'Enter a valid name and email address.';
    } elseif (strlen($password) < 12) {
        $error = 'Choose a password with at least 12 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        try {
            $insert = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Owner account created. Log in to manage your shop.');
            redirect('login.php');
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '23000') {
                $error = 'An account already uses that email address.';
            } else {
                throw $exception;
            }
        }
    }
}

page_start('Set up shop owner');
?>
<section class="auth-layout"><div class="auth-intro"><p class="eyebrow">ONE-TIME SETUP</p><h1>Set up the<br><em>shop owner.</em></h1><p>This page only works while the database has no owner account.</p></div>
    <form class="form-panel" method="post" action="setup_admin.php">
        <h2>Owner account</h2>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <?= csrf_field() ?>
        <label for="name">Your name</label><input id="name" name="name" type="text" maxlength="120" autocomplete="name" required value="<?= h($_POST['name'] ?? '') ?>">
        <label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= h($_POST['email'] ?? '') ?>">
        <label for="password">Password (12+ characters)</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
        <label for="password-confirmation">Confirm password</label><input id="password-confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
        <button class="button button-orange" type="submit">Create owner account</button>
    </form>
</section>
<?php page_end(); ?>