<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';

if (current_user() !== null) {
    redirect(dashboard_for_role(current_user()['role']));
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
    } elseif (strlen($password) < 10) {
        $error = 'Choose a password with at least 10 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        try {
            $insert = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')");
            $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) db()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'customer'];
            flash('success', 'Your account is ready. You can book an appointment now.');
            redirect('customer_dashboard.php');
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '23000') {
                $error = 'An account with that email already exists.';
            } else {
                throw $exception;
            }
        }
    }
}

page_start('Create a customer account');
?>
<section class="auth-layout"><div class="auth-intro"><p class="eyebrow">FIRST TIME HERE?</p><h1>Make yourself<br><em>at home.</em></h1><p>Create an account to find a time, book a barber, and keep track of your appointments.</p></div>
    <form class="form-panel" method="post" action="register.php">
        <h2>Create account</h2><p class="muted">All fields are required.</p>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <?= csrf_field() ?>
        <label for="name">Your name</label><input id="name" name="name" type="text" maxlength="120" autocomplete="name" required value="<?= h($_POST['name'] ?? '') ?>">
        <label for="email">Email address</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= h($_POST['email'] ?? '') ?>">
        <label for="password">Password</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
        <label for="password-confirmation">Confirm password</label><input id="password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
        <button class="button button-orange" type="submit">Create account</button>
        <p class="form-note">Already registered? <a href="login.php">Log in</a></p>
    </form>
</section>
<?php page_end(); ?>