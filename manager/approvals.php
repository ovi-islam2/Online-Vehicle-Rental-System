<?php
require_once __DIR__ . '/../includes/init.php';
require_role('manager');
$uid = $_SESSION['user_id'];

// ---- UPDATE STATUS (Approve / Deny / Complete) — generates a voucher on approval ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowed = ['approved', 'denied', 'completed'];
    if (in_array($status, $allowed, true)) {
        if ($status === 'approved') {
            $voucher = generate_voucher_code();
            $stmt = $conn->prepare('UPDATE bookings SET status=?, manager_id=?, voucher_code=? WHERE id=?');
            $stmt->bind_param('sisi', $status, $uid, $voucher, $id);
        } else {
            $stmt = $conn->prepare('UPDATE bookings SET status=?, manager_id=? WHERE id=?');
            $stmt->bind_param('sii', $status, $uid, $id);
        }
        $stmt->execute();
        $stmt->close();
        flash('ok', 'Booking marked as ' . $status . '.');
    }
    redirect('approvals.php');
}

// ---- DELETE (remove invalid / fraudulent booking entries) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM bookings WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Booking entry removed.');
    redirect('approvals.php');
}

$filter = $_GET['status'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'denied', 'completed', 'cancelled', 'all'];
if (!in_array($filter, $allowed_filters, true)) $filter = 'pending';

$where = $filter === 'all' ? '1=1' : "b.status='" . $conn->real_escape_string($filter) . "'";
$bookings = $conn->query("
    SELECT b.*, v.vehicle_name, v.vehicle_type, u.name AS customer_name, u.email AS customer_email
    FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id JOIN users u ON u.id=b.customer_id
    WHERE $where ORDER BY b.created_at DESC
");

$page_title = 'Booking Approvals';
$active = 'approvals';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Booking Approval &amp; Verification</h2>
            <p class="desc">Review incoming requests, approve or deny, and generate rental vouchers.</p>
        </div>
    </div>
    <div class="pill-nav">
        <?php foreach ($allowed_filters as $f): ?>
            <a href="?status=<?= $f ?>" class="<?= $filter === $f ? 'active' : '' ?>"><?= ucfirst($f) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
    <?php if ($bookings->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#128203;</div>No bookings in this view.</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Customer</th><th>Vehicle</th><th>Dates</th><th>Pickup</th><th>Status</th><th>Voucher</th><th>Actions</th></tr>
            <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><?= h($b['customer_name']) ?><br><span class="text-muted small"><?= h($b['customer_email']) ?></span></td>
                    <td><?= h($b['vehicle_name']) ?> <span class="text-muted small">(<?= h($b['vehicle_type']) ?>)</span></td>
                    <td><?= h($b['start_date']) ?> &rarr; <?= h($b['end_date']) ?></td>
                    <td><?= h($b['pickup_location']) ?></td>
                    <td>
                        <?php $map = ['pending'=>'badge-warn','approved'=>'badge-ok','denied'=>'badge-danger','completed'=>'badge-info','cancelled'=>'badge-muted']; ?>
                        <span class="badge <?= $map[$b['status']] ?>"><?= h($b['status']) ?></span>
                    </td>
                    <td><?= $b['voucher_code'] ? '<code>' . h($b['voucher_code']) . '</code>' : '<span class="text-muted small">&mdash;</span>' ?></td>
                    <td class="row-actions">
                        <?php if ($b['status'] === 'pending'): ?>
                            <form method="post" style="display:inline;"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="status" value="approved"><button type="submit" class="btn btn-primary btn-sm">Approve</button></form>
                            <form method="post" style="display:inline;"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="status" value="denied"><button type="submit" class="btn btn-outline btn-sm">Deny</button></form>
                        <?php elseif ($b['status'] === 'approved'): ?>
                            <form method="post" style="display:inline;"><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="status" value="completed"><button type="submit" class="btn btn-dark btn-sm">Mark Completed</button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this booking entry permanently? Use this for invalid or fraudulent requests.">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
