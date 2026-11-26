<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
$user = require_role('customer');
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $barberId = filter_var($_POST['barber_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $date = trim((string) ($_POST['date'] ?? ''));
    $time = trim((string) ($_POST['time'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($serviceId < 1 || $barberId < 1 || !valid_date($date) || mb_strlen($phone) > 40) {
        $error = 'Choose a valid service, barber, date, and time.';
    } else {
        try {
            $pdo->beginTransaction();
            if (!appointment_slot_is_available($pdo, $barberId, $serviceId, $date, $time)) {
                $pdo->rollBack();
                $error = 'That time is no longer available. Please choose another open slot.';
            } else {
                $durationQuery = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ? AND is_active = 1');
                $durationQuery->execute([$serviceId]);
                $duration = (int) $durationQuery->fetchColumn();
                $start = new DateTimeImmutable($date . ' ' . $time);
                $end = $start->modify('+' . $duration . ' minutes');
                $insert = $pdo->prepare(
                    "INSERT INTO appointments (customer_id, customer_name, customer_email, customer_phone, barber_id, service_id, starts_at, ends_at, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $insert->execute([
                    $user['id'], $user['name'], $user['email'], $phone, $barberId, $serviceId,
                    $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $user['id'],
                ]);
                $pdo->commit();
                flash('success', 'Your appointment has been booked.');
                redirect('customer_dashboard.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$services = $pdo->query('SELECT id, name, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY name')->fetchAll();
$barbers = $pdo->query("SELECT id, name FROM users WHERE role = 'barber' AND is_active = 1 ORDER BY name")->fetchAll();
$selectedService = (int) ($_POST['service_id'] ?? $_GET['service_id'] ?? 0);
$selectedBarber = (int) ($_POST['barber_id'] ?? $_GET['barber_id'] ?? 0);
$selectedDate = (string) ($_POST['date'] ?? $_GET['date'] ?? '');
$slots = $selectedService > 0 && $selectedBarber > 0 && valid_date($selectedDate)
    ? available_start_times($pdo, $selectedBarber, $selectedService, $selectedDate)
    : [];

page_start('Book an appointment');
?>
<section class="dashboard-heading"><div><p class="eyebrow">FIND YOUR TIME</p><h1>Book a <em>chair.</em></h1><p class="muted">Times shown are checked against the barber's schedule and existing appointments.</p></div></section>
<section class="booking-page-grid">
    <div class="booking-forms">
    <form class="form-panel" method="get" action="book_appointment.php">
        <h2>Appointment details</h2>
        <label for="service-id">Service</label><select id="service-id" name="service_id" required>
            <option value="">Choose a service</option>
            <?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>" <?= $selectedService === (int) $service['id'] ? 'selected' : '' ?>><?= h($service['name']) ?> · $<?= h(number_format((float) $service['price'], 2)) ?> · <?= (int) $service['duration_minutes'] ?> min</option><?php endforeach; ?>
        </select>
        <label for="barber-id">Barber</label><select id="barber-id" name="barber_id" required>
            <option value="">Choose a barber</option>
            <?php foreach ($barbers as $barber): ?><option value="<?= (int) $barber['id'] ?>" <?= $selectedBarber === (int) $barber['id'] ? 'selected' : '' ?>><?= h($barber['name']) ?></option><?php endforeach; ?>
        </select>
        <label for="date">Date</label><input id="date" name="date" type="date" required min="<?= h(date('Y-m-d')) ?>" value="<?= h($selectedDate) ?>">
        <button class="button button-dark filter-button" type="submit">Show available times</button>
    </form>
    <?php if ($selectedService > 0 && $selectedBarber > 0 && valid_date($selectedDate)): ?>
    <form class="form-panel" method="post" action="book_appointment.php">
        <h2>Choose a time</h2>
        <?php if ($error !== ''): ?><p class="notice notice-error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="service_id" value="<?= $selectedService ?>">
        <input type="hidden" name="barber_id" value="<?= $selectedBarber ?>">
        <input type="hidden" name="date" value="<?= h($selectedDate) ?>">
        <fieldset class="slot-options"><legend>Available start times</legend>
            <?php if ($slots === []): ?><p class="empty-state">No open times were found. Try another date or barber.</p>
            <?php else: ?><div class="slot-grid"><?php foreach ($slots as $slot): ?><label class="slot-choice"><input type="radio" name="time" value="<?= h($slot) ?>" required <?= ($_POST['time'] ?? '') === $slot ? 'checked' : '' ?>><span><?= h(date('g:i A', strtotime($slot))) ?></span></label><?php endforeach; ?></div><?php endif; ?>
        </fieldset>
        <label for="phone">Phone (optional)</label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= h($_POST['phone'] ?? '') ?>">
        <button class="button button-orange" type="submit" <?= $slots === [] ? 'disabled' : '' ?>>Confirm appointment</button>
    </form>
    <?php endif; ?>
    </div>
    <aside class="booking-aside"><p class="eyebrow">BEFORE YOU BOOK</p><h2>Good timing<br><em>is everything.</em></h2><p>Appointments are reserved for the selected service length. Please cancel ahead of time if your plans change.</p><a href="customer_dashboard.php">View your appointments</a></aside>
</section>
<?php page_end(); ?>