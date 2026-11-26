<?php
declare(strict_types=1);
require_once __DIR__ . '/src/helpers.php';
$user = require_role('customer');
require_post();
verify_csrf();

$appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
if (!$appointmentId || $appointmentId < 1) {
    flash('error', 'That appointment could not be found.');
    redirect('customer_dashboard.php');
}

$cancel = db()->prepare(
    "UPDATE appointments SET status = 'cancelled'
    WHERE id = ? AND customer_id = ? AND status = 'pending' AND starts_at > ?"
);
$cancel->execute([$appointmentId, $user['id'], date('Y-m-d H:i:s')]);
flash($cancel->rowCount() === 1 ? 'success' : 'error', $cancel->rowCount() === 1
    ? 'Your appointment was cancelled.'
    : 'That appointment could not be cancelled. It may have already started or changed.');
redirect('customer_dashboard.php');