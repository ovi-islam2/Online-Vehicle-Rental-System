<?php
require_once __DIR__ . '/../includes/init.php';
require_role('manager');

$pending = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending'")->fetch_assoc()['c'];
$in_progress_maint = $conn->query("SELECT COUNT(*) c FROM maintenance WHERE status IN ('scheduled','in_progress')")->fetch_assoc()['c'];
$open_complaints = $conn->query("SELECT COUNT(*) c FROM complaints WHERE status IN ('pending','investigating')")->fetch_assoc()['c'];
$approved_today = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='approved' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'];

$recent = $conn->query("
    SELECT b.*, v.vehicle_name, u.name AS customer_name FROM bookings b
    JOIN vehicles v ON v.id=b.vehicle_id JOIN users u ON u.id=b.customer_id
    WHERE b.status='pending' ORDER BY b.created_at ASC LIMIT 5
");

$page_title = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="stats-row">
    <div class="stat-card"><div class="num"><?= $pending ?></div><div class="lbl">Pending Approvals</div></div>
    <div class="stat-card"><div class="num"><?= $in_progress_maint ?></div><div class="lbl">Vehicles in Maintenance</div></div>
    <div class="stat-card"><div class="num"><?= $open_complaints ?></div><div class="lbl">Open Complaints</div></div>
    <div class="stat-card"><div class="num"><?= $approved_today ?></div><div class="lbl">Approved Today</div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Awaiting Your Review</h2>
            <p class="desc">Oldest pending booking requests across the platform.</p>
        </div>
        <a href="approvals.php" class="btn btn-primary btn-sm">Review All</a>
    </div>
    <div class="table-wrap">
    <?php if ($recent->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#9989;</div>No pending bookings right now. Nice and clear!</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Customer</th><th>Vehicle</th><th>Dates</th><th>Requested</th></tr>
            <?php while ($b = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?= h($b['customer_name']) ?></td>
                    <td><?= h($b['vehicle_name']) ?></td>
                    <td><?= h($b['start_date']) ?> &rarr; <?= h($b['end_date']) ?></td>
                    <td><?= h($b['created_at']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
