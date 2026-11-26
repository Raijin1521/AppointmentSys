<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
$admin = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_service') {
        $id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $duration = filter_var($_POST['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 500 || $price === false || $price < 0 || !$duration || $duration > 1440) {
            flash('error', 'Enter a service name, non-negative price, and duration between 1 and 1440 minutes.');
        } else {
            try {
                if ($id > 0) {
                    $save = $pdo->prepare('UPDATE services SET name = ?, description = ?, price = ?, duration_minutes = ?, is_active = 1 WHERE id = ?');
                    $save->execute([$name, $description, $price, $duration, $id]);
                } else {
                    $save = $pdo->prepare('INSERT INTO services (name, description, price, duration_minutes) VALUES (?, ?, ?, ?)');
                    $save->execute([$name, $description, $price, $duration]);
                }
                flash('success', 'Service saved.');
            } catch (PDOException $exception) {
                if (($exception->errorInfo[0] ?? '') === '23000') {
                    flash('error', 'A service with that name already exists.');
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'delete_service') {
        $id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            $delete = $pdo->prepare('DELETE FROM services WHERE id = ?');
            $delete->execute([$id]);
            flash($delete->rowCount() === 1 ? 'success' : 'error', $delete->rowCount() === 1 ? 'Service deleted.' : 'Service not found.');
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '23000') {
                $deactivate = $pdo->prepare('UPDATE services SET is_active = 0 WHERE id = ?');
                $deactivate->execute([$id]);
                flash('success', 'This service has appointment history, so it was hidden from booking and its records were preserved.');
            } else {
                throw $exception;
            }
        }
    } elseif ($action === 'save_staff') {
        $id = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || $id === 0 && strlen($password) < 10 || $password !== '' && strlen($password) < 10) {
            flash('error', 'Enter a valid name and email. New staff passwords need at least 10 characters.');
        } else {
            try {
                if ($id > 0) {
                    if ($password === '') {
                        $save = $pdo->prepare("UPDATE users SET name = ?, email = ?, is_active = 1 WHERE id = ? AND role = 'barber'");
                        $save->execute([$name, $email, $id]);
                    } else {
                        $save = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, is_active = 1 WHERE id = ? AND role = 'barber'");
                        $save->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $id]);
                    }
                } else {
                    $save = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'barber')");
                    $save->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                }
                flash('success', 'Barber account saved.');
            } catch (PDOException $exception) {
                if (($exception->errorInfo[0] ?? '') === '23000') {
                    flash('error', 'That email address is already in use.');
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'delete_staff') {
        $id = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        try {
            $delete = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'barber'");
            $delete->execute([$id]);
            flash($delete->rowCount() === 1 ? 'success' : 'error', $delete->rowCount() === 1 ? 'Staff account deleted.' : 'Staff account not found.');
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? '') === '23000') {
                $deactivate = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'barber'");
                $deactivate->execute([$id]);
                flash('success', 'This barber has appointment history, so the account was deactivated and the records were preserved.');
            } else {
                throw $exception;
            }
        }
    } elseif ($action === 'save_schedule') {
        $barberId = filter_var($_POST['barber_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $day = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $start = trim((string) ($_POST['start_time'] ?? ''));
        $end = trim((string) ($_POST['end_time'] ?? ''));
        if (!$barberId || $day === false || $day < 1 || $day > 7 || !valid_time($start) || !valid_time($end) || $start >= $end) {
            flash('error', 'Choose valid opening and closing times, or use the off-day button.');
        } else {
            $save = $pdo->prepare('INSERT INTO barber_schedules (barber_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time)');
            $save->execute([$barberId, $day, $start, $end]);
            flash('success', 'Working hours saved.');
        }
    } elseif ($action === 'delete_schedule') {
        $barberId = filter_var($_POST['barber_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $day = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $delete = $pdo->prepare('DELETE FROM barber_schedules WHERE barber_id = ? AND day_of_week = ?');
        $delete->execute([$barberId, $day]);
        flash('success', 'That day is now marked as an off day.');
    } elseif ($action === 'save_appointment') {
        $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $barberId = filter_var($_POST['barber_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $name = trim((string) ($_POST['customer_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['customer_email'] ?? '')));
        $phone = trim((string) ($_POST['customer_phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'pending');

        if ($name === '' && $customerId === 0 || mb_strlen($name) > 120 || $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) || mb_strlen($phone) > 40 || mb_strlen($notes) > 1000 || $serviceId < 1 || $barberId < 1 || !valid_date($date) || !valid_time($time) || !in_array($status, ['pending', 'completed', 'no_show', 'cancelled'], true)) {
            flash('error', 'Complete the appointment details with valid values.');
        } elseif ($customerId > 0) {
            $customerLookup = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'customer' AND is_active = 1");
            $customerLookup->execute([$customerId]);
            $customer = $customerLookup->fetch();
            if (!$customer) {
                flash('error', 'Select a valid customer account or leave it as a walk-in.');
            } else {
                $email = $customer['email'];
                $name = trim((string) ($_POST['customer_name'] ?? '')) ?: $customer['name'];
                save_admin_appointment($pdo, $admin, $appointmentId, $customerId, $name, $email, $phone, $barberId, $serviceId, $date, $time, $status, $notes);
            }
        } else {
            save_admin_appointment($pdo, $admin, $appointmentId, null, $name, $email, $phone, $barberId, $serviceId, $date, $time, $status, $notes);
        }
    } elseif ($action === 'cancel_appointment') {
        $id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $cancel = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $cancel->execute([$id]);
        flash($cancel->rowCount() === 1 ? 'success' : 'error', $cancel->rowCount() === 1 ? 'Appointment cancelled.' : 'Appointment not found.');
    }

    redirect('admin_dashboard.php');
}

// Validate and save owner-created or edited bookings with the same overlap rules as customer bookings.
function save_admin_appointment(PDO $pdo, array $admin, int $appointmentId, ?int $customerId, string $name, string $email, string $phone, int $barberId, int $serviceId, string $date, string $time, string $status, string $notes): void
{
    $pdo->beginTransaction();
    try {
        if ($status !== 'cancelled' && !appointment_slot_is_available($pdo, $barberId, $serviceId, $date, $time, $appointmentId ?: null, $appointmentId > 0)) {
            $pdo->rollBack();
            flash('error', 'That time conflicts with the barber\'s schedule or another appointment.');
            return;
        }

        $durationQuery = $pdo->prepare('SELECT duration_minutes FROM services WHERE id = ?');
        $durationQuery->execute([$serviceId]);
        $duration = (int) $durationQuery->fetchColumn();
        if ($duration < 1 || !valid_time($time)) {
            $pdo->rollBack();
            flash('error', 'Choose a valid active service and start time.');
            return;
        }
        $start = new DateTimeImmutable($date . ' ' . $time);
        $end = $start->modify('+' . $duration . ' minutes');

        if ($appointmentId > 0) {
            $save = $pdo->prepare('UPDATE appointments SET customer_id = ?, customer_name = ?, customer_email = ?, customer_phone = ?, barber_id = ?, service_id = ?, starts_at = ?, ends_at = ?, status = ?, notes = ? WHERE id = ?');
            $save->execute([$customerId, $name, $email, $phone, $barberId, $serviceId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $status, $notes, $appointmentId]);
        } else {
            $save = $pdo->prepare('INSERT INTO appointments (customer_id, customer_name, customer_email, customer_phone, barber_id, service_id, starts_at, ends_at, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $save->execute([$customerId, $name, $email, $phone, $barberId, $serviceId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $status, $notes, $admin['id']]);
        }
        $pdo->commit();
        flash('success', $appointmentId > 0 ? 'Appointment updated.' : 'Appointment created.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

$services = $pdo->query('SELECT * FROM services ORDER BY is_active DESC, name')->fetchAll();
$serviceEditId = filter_var($_GET['edit_service'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$serviceEditing = null;
foreach ($services as $service) {
    if ((int) $service['id'] === $serviceEditId) {
        $serviceEditing = $service;
        break;
    }
}
$staff = $pdo->query("SELECT id, name, email, is_active FROM users WHERE role = 'barber' ORDER BY is_active DESC, name")->fetchAll();
$customers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY name")->fetchAll();
$schedules = $pdo->query('SELECT barber_id, day_of_week, start_time, end_time FROM barber_schedules')->fetchAll();
$scheduleMap = [];
foreach ($schedules as $schedule) {
    $scheduleMap[(int) $schedule['barber_id']][(int) $schedule['day_of_week']] = $schedule;
}
$records = $pdo->query('SELECT a.*, s.name AS service_name, u.name AS barber_name FROM appointments a JOIN services s ON s.id = a.service_id JOIN users u ON u.id = a.barber_id ORDER BY a.starts_at DESC')->fetchAll();
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$editing = null;
if ($editId > 0) {
    $find = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
    $find->execute([$editId]);
    $editing = $find->fetch() ?: null;
}

page_start('Shop management');
?>
<section class="dashboard-heading"><div><p class="eyebrow">OWNER WORKSPACE</p><h1>Run the <em>shop.</em></h1><p class="muted">Services, staff schedules, and every appointment.</p></div></section>

<section class="admin-grid">
    <div class="panel" id="service-editor"><div class="panel-heading"><div><p class="eyebrow">MENU</p><h2>Services</h2></div></div>
        <form class="compact-form" method="post" action="admin_dashboard.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_service"><input type="hidden" name="service_id" value="<?= (int) ($serviceEditing['id'] ?? 0) ?>">
            <label>Service name<input name="name" maxlength="120" required value="<?= h($serviceEditing['name'] ?? '') ?>"></label>
            <label>Price<input name="price" type="number" min="0" step="0.01" required value="<?= h($serviceEditing['price'] ?? '') ?>"></label>
            <label>Duration (minutes)<input name="duration_minutes" type="number" min="1" max="1440" required value="<?= h($serviceEditing['duration_minutes'] ?? '') ?>"></label>
            <label>Description<input name="description" maxlength="500" value="<?= h($serviceEditing['description'] ?? '') ?>"></label>
            <button class="button button-orange" type="submit"><?= $serviceEditing ? 'Save service' : 'Add service' ?></button>
            <?php if ($serviceEditing): ?><a class="text-button" href="admin_dashboard.php#service-editor">Stop editing</a><?php endif; ?>
        </form>
        <div class="admin-list"><?php foreach ($services as $service): ?><article class="admin-list-row <?= $service['is_active'] ? '' : 'is-inactive' ?>">
            <div><strong><?= h($service['name']) ?></strong><small>$<?= h(number_format((float) $service['price'], 2)) ?> · <?= (int) $service['duration_minutes'] ?> min<?= $service['is_active'] ? '' : ' · Hidden' ?></small></div>
            <div class="table-actions"><a class="text-button" href="admin_dashboard.php?edit_service=<?= (int) $service['id'] ?>#service-editor">Edit</a><?php if ($service['is_active']): ?><form method="post" action="admin_dashboard.php"><?= csrf_field() ?><input type="hidden" name="action" value="delete_service"><input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>"><button class="text-button danger-text" type="submit">Remove</button></form><?php endif; ?></div>
        </article><?php endforeach; ?></div>
    </div>

    <div class="panel"><div class="panel-heading"><div><p class="eyebrow">TEAM</p><h2>Barber staff</h2></div></div>
        <form class="compact-form" method="post" action="admin_dashboard.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_staff">
            <label>Name<input name="name" maxlength="120" required></label><label>Email<input name="email" type="email" maxlength="190" required></label>
            <label>Temporary password<input name="password" type="password" minlength="10" required autocomplete="new-password"></label>
            <button class="button button-orange" type="submit">Add barber</button>
        </form>
        <div class="admin-list"><?php foreach ($staff as $barber): ?><article class="staff-card <?= $barber['is_active'] ? '' : 'is-inactive' ?>">
            <form class="compact-form" method="post" action="admin_dashboard.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_staff"><input type="hidden" name="staff_id" value="<?= (int) $barber['id'] ?>">
                <label>Name<input name="name" maxlength="120" required value="<?= h($barber['name']) ?>"></label><label>Email<input name="email" type="email" maxlength="190" required value="<?= h($barber['email']) ?>"></label>
                <label>New password (leave blank to keep)<input name="password" type="password" minlength="10" autocomplete="new-password"></label>
                <button class="text-button" type="submit">Save staff</button>
            </form>
            <?php if ($barber['is_active']): ?><form method="post" action="admin_dashboard.php"><?= csrf_field() ?><input type="hidden" name="action" value="delete_staff"><input type="hidden" name="staff_id" value="<?= (int) $barber['id'] ?>"><button class="text-button danger-text" type="submit">Delete account</button></form><?php else: ?><span class="muted">Deactivated</span><?php endif; ?>
        </article><?php endforeach; ?></div>
    </div>
</section>

<section class="panel"><div class="panel-heading"><div><p class="eyebrow">WEEKLY HOURS</p><h2>Barber schedules</h2></div></div>
    <?php if ($staff === []): ?><p class="empty-state">Add barber staff before setting working hours.</p><?php endif; ?>
    <?php foreach ($staff as $barber): ?><details class="schedule-details"><summary><?= h($barber['name']) ?><?= $barber['is_active'] ? '' : ' · Inactive' ?></summary>
        <div class="schedule-grid"><?php for ($day = 1; $day <= 7; $day++): $hours = $scheduleMap[(int) $barber['id']][$day] ?? null; ?>
            <form class="schedule-day" method="post" action="admin_dashboard.php">
                <?= csrf_field() ?><input type="hidden" name="barber_id" value="<?= (int) $barber['id'] ?>"><input type="hidden" name="day_of_week" value="<?= $day ?>">
                <strong><?= h(['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$day]) ?></strong>
                <label>Opens<input type="time" step="1800" name="start_time" value="<?= h($hours['start_time'] ?? '') ?>" <?= $barber['is_active'] ? '' : 'disabled' ?>></label>
                <label>Closes<input type="time" step="1800" name="end_time" value="<?= h($hours['end_time'] ?? '') ?>" <?= $barber['is_active'] ? '' : 'disabled' ?>></label>
                <?php if ($barber['is_active']): ?><button class="text-button" type="submit" name="action" value="save_schedule">Save hours</button><button class="text-button danger-text" type="submit" name="action" value="delete_schedule">Off day</button><?php endif; ?>
                <?php if (!$hours): ?><small class="muted">Off day</small><?php endif; ?>
            </form>
        <?php endfor; ?></div>
    </details><?php endforeach; ?>
</section>

<section class="panel" id="appointment-editor"><div class="panel-heading"><div><p class="eyebrow">APPOINTMENT DESK</p><h2><?= $editing ? 'Edit appointment #' . (int) $editing['id'] : 'Create appointment' ?></h2></div><?php if ($editing): ?><a href="admin_dashboard.php#appointment-editor">New appointment</a><?php endif; ?></div>
    <?php if ($staff === [] || $services === []): ?><p class="notice notice-error">Add at least one barber and service before creating appointments.</p><?php else: ?>
    <form class="appointment-editor" method="post" action="admin_dashboard.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_appointment"><input type="hidden" name="appointment_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <label>Customer account (optional)<select name="customer_id"><option value="0">Walk-in / enter contact details</option><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>" <?= (int) ($editing['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= h($customer['name']) ?> · <?= h($customer['email']) ?></option><?php endforeach; ?></select></label>
        <label>Customer name<input name="customer_name" maxlength="120" required value="<?= h($editing['customer_name'] ?? '') ?>"></label>
        <label>Email<input name="customer_email" type="email" maxlength="190" value="<?= h($editing['customer_email'] ?? '') ?>"></label>
        <label>Phone<input name="customer_phone" maxlength="40" value="<?= h($editing['customer_phone'] ?? '') ?>"></label>
        <label>Service<select name="service_id" required><?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>" <?= (int) ($editing['service_id'] ?? 0) === (int) $service['id'] ? 'selected' : '' ?> <?= !$service['is_active'] && (int) ($editing['service_id'] ?? 0) !== (int) $service['id'] ? 'disabled' : '' ?>><?= h($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min<?= $service['is_active'] ? '' : ' · Inactive' ?></option><?php endforeach; ?></select></label>
        <label>Barber<select name="barber_id" required><?php foreach ($staff as $barber): ?><option value="<?= (int) $barber['id'] ?>" <?= (int) ($editing['barber_id'] ?? 0) === (int) $barber['id'] ? 'selected' : '' ?> <?= !$barber['is_active'] && (int) ($editing['barber_id'] ?? 0) !== (int) $barber['id'] ? 'disabled' : '' ?>><?= h($barber['name']) ?><?= $barber['is_active'] ? '' : ' · Inactive' ?></option><?php endforeach; ?></select></label>
        <label>Date<input name="date" type="date" required value="<?= h(isset($editing['starts_at']) ? date('Y-m-d', strtotime($editing['starts_at'])) : '') ?>"></label>
        <label>Start time<input name="time" type="time" step="1800" required value="<?= h(isset($editing['starts_at']) ? date('H:i', strtotime($editing['starts_at'])) : '') ?>"></label>
        <label>Status<select name="status"><?php foreach (['pending' => 'Pending', 'completed' => 'Completed', 'no_show' => 'No-show', 'cancelled' => 'Cancelled'] as $value => $label): ?><option value="<?= h($value) ?>" <?= ($editing['status'] ?? 'pending') === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
        <label class="wide-field">Notes<textarea name="notes" maxlength="1000" rows="2"><?= h($editing['notes'] ?? '') ?></textarea></label>
        <button class="button button-orange" type="submit"><?= $editing ? 'Save appointment changes' : 'Create appointment' ?></button>
    </form><?php endif; ?>
</section>

<section class="panel"><div class="panel-heading"><div><p class="eyebrow">SHOP RECORDS</p><h2>All appointments <span class="count-label"><?= count($records) ?></span></h2></div></div>
    <?php if ($records === []): ?><p class="empty-state">There are no appointment records yet.</p>
    <?php else: ?><div class="table-scroll"><table><thead><tr><th>Date &amp; time</th><th>Customer</th><th>Service</th><th>Barber</th><th>Status</th><th>Manage</th></tr></thead><tbody>
        <?php foreach ($records as $record): ?><tr>
            <td><?= h(date('D, M j, Y · g:i A', strtotime($record['starts_at']))) ?></td><td><?= h($record['customer_name']) ?><small><?= h($record['customer_email'] ?: $record['customer_phone']) ?></small></td>
            <td><?= h($record['service_name']) ?></td><td><?= h($record['barber_name']) ?></td><td><span class="status status-<?= h($record['status']) ?>"><?= h(ucwords(str_replace('_', ' ', $record['status']))) ?></span></td>
            <td><div class="table-actions"><a class="text-button" href="admin_dashboard.php?edit=<?= (int) $record['id'] ?>#appointment-editor">Edit</a><?php if ($record['status'] !== 'cancelled'): ?><form method="post" action="admin_dashboard.php"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_appointment"><input type="hidden" name="appointment_id" value="<?= (int) $record['id'] ?>"><button class="text-button danger-text" type="submit">Cancel</button></form><?php endif; ?></div></td>
        </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php page_end(); ?>