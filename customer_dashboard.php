<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
$user = require_role('customer');

$query = db()->prepare(
    'SELECT a.id, a.customer_name, a.customer_phone, a.starts_at, a.ends_at, a.status, a.notes,
            s.name AS service_name, s.price, u.name AS barber_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN users u ON u.id = a.barber_id
     WHERE a.customer_id = ? ORDER BY a.starts_at DESC'
);
$query->execute([$user['id']]);
$appointments = $query->fetchAll();

page_start('My appointments');
?>
<section class="dashboard-heading"><div><p class="eyebrow">CUSTOMER ACCOUNT</p><h1>Good to see you, <em><?= h($user['name']) ?>.</em></h1><p class="muted">Your appointments, all in one place.</p></div><a class="button button-orange" href="book_appointment.php">Book a chair <span aria-hidden="true">↗</span></a></section>
<section class="panel"><div class="panel-heading"><div><p class="eyebrow">YOUR BOOKINGS</p><h2>Appointments</h2></div></div>
    <?php if ($appointments === []): ?><p class="empty-state">No appointments yet. Book your first visit when you're ready.</p>
    <?php else: ?>
        <div class="table-scroll"><table><thead><tr><th>Date &amp; time</th><th>Service</th><th>Barber</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><?= h(date('D, M j, Y · g:i A', strtotime($appointment['starts_at']))) ?></td>
                <td><?= h($appointment['service_name']) ?><small>$<?= h(number_format((float) $appointment['price'], 2)) ?></small></td>
                <td><?= h($appointment['barber_name']) ?></td>
                <td><span class="status status-<?= h($appointment['status']) ?>"><?= h(ucwords(str_replace('_', ' ', $appointment['status']))) ?></span></td>
                <td><?php if ($appointment['status'] === 'pending' && strtotime($appointment['starts_at']) > time()): ?>
                    <form method="post" action="cancel_booking.php" onsubmit="return confirm('Cancel this appointment?')">
                        <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                        <button class="text-button danger-text" type="submit">Cancel</button>
                    </form>
                <?php else: ?><span class="muted">—</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php page_end(); ?>