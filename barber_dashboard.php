<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
$user = require_role('barber');
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    verify_csrf();
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($_POST['status'] ?? '');

    if ($appointmentId && in_array($status, ['pending', 'completed', 'no_show'], true)) {
        $update = db()->prepare('UPDATE appointments SET status = ? WHERE id = ? AND barber_id = ? AND status <> \'cancelled\'');
        $update->execute([$status, $appointmentId, $user['id']]);
        flash($update->rowCount() === 1 ? 'success' : 'error', $update->rowCount() === 1
            ? 'Appointment status updated.'
            : 'That appointment could not be updated.');
    } else {
        flash('error', 'Choose a valid appointment status.');
    }
    redirect('barber_dashboard.php');
}

$query = db()->prepare(
    'SELECT a.id, a.customer_name, a.customer_phone, a.starts_at, a.ends_at, a.status, a.notes,
            s.name AS service_name
     FROM appointments a JOIN services s ON s.id = a.service_id
     WHERE a.barber_id = ? AND DATE(a.starts_at) = ? AND a.status <> \'cancelled\'
     ORDER BY a.starts_at'
);
$query->execute([$user['id'], $today]);
$appointments = $query->fetchAll();

page_start("Today's schedule");
?>
<section class="dashboard-heading"><div><p class="eyebrow">BARBER WORKSPACE · <?= h(date('l, F j, Y')) ?></p><h1>Your daily <em>chair list.</em></h1><p class="muted">Appointments assigned to your account today.</p></div></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">TODAY</p><h2><?= count($appointments) ?> appointment<?= count($appointments) === 1 ? '' : 's' ?></h2></div></div>
    <?php if ($appointments === []): ?><p class="empty-state">No appointments are scheduled for today.</p>
    <?php else: ?><div class="table-scroll"><table><thead><tr><th>Time</th><th>Customer</th><th>Service</th><th>Phone</th><th>Status</th><th>Update</th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): ?><tr>
            <td><?= h(date('g:i A', strtotime($appointment['starts_at']))) ?>–<?= h(date('g:i A', strtotime($appointment['ends_at']))) ?></td>
            <td><?= h($appointment['customer_name']) ?><?php if ($appointment['notes'] !== ''): ?><small><?= h($appointment['notes']) ?></small><?php endif; ?></td>
            <td><?= h($appointment['service_name']) ?></td><td><?= $appointment['customer_phone'] !== '' ? h($appointment['customer_phone']) : '—' ?></td>
            <td><span class="status status-<?= h($appointment['status']) ?>"><?= h(ucwords(str_replace('_', ' ', $appointment['status']))) ?></span></td>
            <td><form class="inline-form" method="post" action="barber_dashboard.php">
                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                <select name="status" aria-label="Set status for <?= h($appointment['customer_name']) ?>">
                    <?php foreach (['pending' => 'Pending', 'completed' => 'Completed', 'no_show' => 'No-show'] as $value => $label): ?><option value="<?= h($value) ?>" <?= $appointment['status'] === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select><button class="text-button" type="submit">Save</button>
            </form></td>
        </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php page_end(); ?>