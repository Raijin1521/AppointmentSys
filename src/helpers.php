<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function current_user(): ?array
{
    static $checked = false;
    static $user = null;

    if ($checked) {
        return $user;
    }
    $checked = true;

    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || !isset($sessionUser['id'])) {
        return null;
    }

    $query = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $query->execute([(int) $sessionUser['id']]);
    $user = $query->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['user']);
    } else {
        $user['id'] = (int) $user['id'];
        $_SESSION['user'] = $user;
    }

    return $user;
}

function dashboard_for_role(string $role): string
{
    return match ($role) {
        'customer' => 'customer_dashboard.php',
        'barber' => 'barber_dashboard.php',
        'admin' => 'admin_dashboard.php',
        default => 'index.php',
    };
}

function require_role(string ...$roles): array
{
    $user = current_user();

    if ($user === null) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        page_start('Access denied');
        echo '<section class="panel"><h1>Access denied</h1><p>Your account cannot open this page.</p>';
        echo '<a class="button button-dark" href="' . h(dashboard_for_role($user['role'])) . '">Back to your dashboard</a></section>';
        page_end();
        exit;
    }

    return $user;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($stored) || $stored === '' || !hash_equals($stored, $submitted)) {
        http_response_code(400);
        exit('The form expired or could not be verified. Go back and try again.');
    }
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('This action requires a form submission.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($message) ? $message : null;
}

function page_start(string $title): void
{
    $user = current_user();
    $notice = take_flash();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> | Corner Chair</title>
        <link rel="stylesheet" href="public/styles.css">
    </head>
    <body>
    <header class="site-header">
        <a class="brand" href="index.php">CORNER<span>CHAIR</span></a>
        <nav aria-label="Main navigation">
            <a href="index.php#services">Services</a>
            <?php if ($user === null): ?>
                <a href="login.php">Log in</a>
                <a class="nav-book" href="register.php">Create account</a>
            <?php else: ?>
                <a href="<?= h(dashboard_for_role($user['role'])) ?>">Dashboard</a>
                <form class="nav-form" method="post" action="logout.php">
                    <?= csrf_field() ?>
                    <button class="nav-link" type="submit">Log out</button>
                </form>
            <?php endif; ?>
        </nav>
    </header>
    <main class="page-main">
        <?php if ($notice !== null): ?>
            <p class="notice notice-<?= h($notice['type']) ?>" role="status"><?= h($notice['message']) ?></p>
        <?php endif; ?>
    <?php
}

function page_end(): void
{
    ?>
    </main>
    <footer><a class="brand" href="index.php">CORNER<span>CHAIR</span></a><p>Appointments made easy.</p><span>© <?= date('Y') ?> CORNER CHAIR</span></footer>
    </body>
    </html>
    <?php
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);

    return $parsed !== false && $parsed->format('H:i') === $time;
}

// Booking transactions lock this barber's schedule row so simultaneous requests serialize.
function appointment_slot_is_available(
    PDO $pdo,
    int $barberId,
    int $serviceId,
    string $date,
    string $time,
    ?int $excludeAppointmentId = null,
    bool $allowPast = false
): bool {
    if (!valid_date($date) || !preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $time)) {
        return false;
    }

    $start = new DateTimeImmutable($date . ' ' . $time);
    if (!$allowPast && $start <= new DateTimeImmutable()) {
        return false;
    }

    $weekday = (int) $start->format('N');
    $lock = $pdo->inTransaction() ? ' FOR UPDATE' : '';
    $schedule = $pdo->prepare(
        'SELECT s.start_time, s.end_time FROM barber_schedules s
         JOIN users u ON u.id = s.barber_id
         WHERE s.barber_id = ? AND s.day_of_week = ? AND u.role = \'barber\' AND u.is_active = 1' . $lock
    );
    $schedule->execute([$barberId, $weekday]);
    $hours = $schedule->fetch();

    if (!$hours) {
        return false;
    }

    $serviceLock = $pdo->inTransaction() ? ' FOR UPDATE' : '';
    $service = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ? AND is_active = 1' . $serviceLock);
    $service->execute([$serviceId]);
    $duration = $service->fetchColumn();

    if ($duration === false) {
        return false;
    }

    $end = $start->modify('+' . (int) $duration . ' minutes');
    $opening = new DateTimeImmutable($date . ' ' . $hours['start_time']);
    $closing = new DateTimeImmutable($date . ' ' . $hours['end_time']);

    if ($start < $opening || $end > $closing) {
        return false;
    }

    $sql = "SELECT id FROM appointments
            WHERE barber_id = ? AND status <> 'cancelled'
              AND starts_at < ? AND ends_at > ?";
    $params = [$barberId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];

    if ($excludeAppointmentId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeAppointmentId;
    }

    $sql .= ' LIMIT 1';
    $conflict = $pdo->prepare($sql);
    $conflict->execute($params);

    return $conflict->fetch() === false;
}

// Start times are offered in half-hour increments, but duration can vary by service.
function available_start_times(PDO $pdo, int $barberId, int $serviceId, string $date): array
{
    if (!valid_date($date)) {
        return [];
    }

    $weekday = (int) (new DateTimeImmutable($date))->format('N');
    $schedule = $pdo->prepare(
        'SELECT start_time, end_time FROM barber_schedules WHERE barber_id = ? AND day_of_week = ?'
    );
    $schedule->execute([$barberId, $weekday]);
    $hours = $schedule->fetch();

    if (!$hours) {
        return [];
    }

    $open = new DateTimeImmutable($date . ' ' . $hours['start_time']);
    $close = new DateTimeImmutable($date . ' ' . $hours['end_time']);
    $minute = (int) $open->format('i');
    $offset = (30 - ($minute % 30)) % 30;
    if ($offset > 0) {
        $open = $open->modify('+' . $offset . ' minutes');
    }
    $slots = [];

    for ($slot = $open; $slot < $close; $slot = $slot->modify('+30 minutes')) {
        $time = $slot->format('H:i');
        if (appointment_slot_is_available($pdo, $barberId, $serviceId, $date, $time)) {
            $slots[] = $time;
        }
    }

    return $slots;
}