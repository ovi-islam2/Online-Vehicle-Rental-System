<?php
require_once __DIR__ . '/../includes/init.php';
require_role('customer');
$uid = $_SESSION['user_id'];

$active_bookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE customer_id=$uid AND status IN ('pending','approved')")->fetch_assoc()['c'];
$completed_bookings = $conn->query("SELECT COUNT(*) c FROM bookings WHERE customer_id=$uid AND status='completed'")->fetch_assoc()['c'];
$total_spent = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE customer_id=$uid AND status='paid'")->fetch_assoc()['s'];
$due_amount = $conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE customer_id=$uid AND status='due'")->fetch_assoc()['s'];

$recent = $conn->query("SELECT b.*, v.vehicle_name, v.vehicle_type FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.customer_id=$uid ORDER BY b.created_at DESC LIMIT 5");

$page_title = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="stats-row">
    <div class="stat-card"><div class="num"><?= $active_bookings ?></div><div class="lbl">Active Bookings</div></div>
    <div class="stat-card"><div class="num"><?= $completed_bookings ?></div><div class="lbl">Completed Rentals</div></div>
    <div class="stat-card"><div class="num"><?= h(format_money($total_spent)) ?></div><div class="lbl">Total Paid</div></div>
    <div class="stat-card"><div class="num"><?= h(format_money($due_amount)) ?></div><div class="lbl">Amount Due</div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Bookings</h2>
            <p class="desc">Your latest rental requests and their status.</p>
        </div>
        <a href="bookings.php" class="btn btn-primary btn-sm">+ New Booking</a>
    </div>
    <div class="table-wrap">
    <?php if ($recent->num_rows === 0): ?>
        <div class="empty-state">
            <div class="glyph">&#128663;</div>
            No bookings yet. <a href="bookings.php">Book your first vehicle</a>.
        </div>
    <?php else: ?>
        <table class="data">
            <tr><th>Vehicle</th><th>Dates</th><th>Pickup</th><th>Status</th></tr>
            <?php while ($b = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?= h($b['vehicle_name']) ?> <span class="text-muted small">(<?= h($b['vehicle_type']) ?>)</span></td>
                    <td><?= h($b['start_date']) ?> &rarr; <?= h($b['end_date']) ?></td>
                    <td><?= h($b['pickup_location']) ?></td>
                    <td>
                        <?php
                        $map = ['pending'=>'badge-warn','approved'=>'badge-ok','denied'=>'badge-danger','completed'=>'badge-info','cancelled'=>'badge-muted'];
                        ?>
                        <span class="badge <?= $map[$b['status']] ?? 'badge-muted' ?>"><?= h($b['status']) ?></span>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
