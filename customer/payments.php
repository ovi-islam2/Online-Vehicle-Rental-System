<?php
require_once __DIR__ . '/../includes/init.php';
require_role('customer');
$uid = $_SESSION['user_id'];

// ---- CREATE (submit a payment transaction for an approved booking) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'Card';

    $chk = $conn->prepare("SELECT id FROM bookings WHERE id=? AND customer_id=? AND status='approved'");
    $chk->bind_param('ii', $booking_id, $uid);
    $chk->execute();
    $valid = $chk->get_result()->fetch_assoc();
    $chk->close();
    
    if ($valid && $amount > 0) {
        $stmt = $conn->prepare('INSERT INTO payments (booking_id, customer_id, amount, payment_method, status) VALUES (?, ?, ?, ?, "due")');
        $stmt->bind_param('iids', $booking_id, $uid, $amount, $method);
        $stmt->execute();
        $stmt->close();
        flash('ok', 'Payment record submitted. Complete it to finalize the transaction.');
    } else {
        flash('err', 'Invalid booking or amount.');
    }
    redirect('payments.php');
}

// ---- UPDATE (correct transaction details while still due) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'Card';
    $stmt = $conn->prepare('UPDATE payments SET amount=?, payment_method=? WHERE id=? AND customer_id=? AND status="due"');
    $stmt->bind_param('dsii', $amount, $method, $id, $uid);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Payment details updated.');
    redirect('payments.php');
}

// ---- MARK AS PAID ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('UPDATE payments SET status="paid", transaction_date=NOW() WHERE id=? AND customer_id=? AND status="due"');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Payment completed. Receipt generated below.');
    redirect('payments.php');
}

// ---- DELETE (only while due/draft) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM payments WHERE id=? AND customer_id=? AND status="due"');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Draft payment record deleted.');
    redirect('payments.php');
}

// Approved bookings without a payment yet (eligible for a new payment record)
$eligible = $conn->query("
    SELECT b.id, b.start_date, b.end_date, v.vehicle_name, v.daily_rate,
           DATEDIFF(b.end_date, b.start_date) + 1 AS days
    FROM bookings b
    JOIN vehicles v ON v.id = b.vehicle_id
    WHERE b.customer_id = $uid AND b.status = 'approved'
      AND b.id NOT IN (SELECT booking_id FROM payments WHERE customer_id = $uid)
    ORDER BY b.start_date
");

$payments = $conn->query("
    SELECT p.*, b.start_date, b.end_date, v.vehicle_name
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    JOIN vehicles v ON v.id = b.vehicle_id
    WHERE p.customer_id = $uid
    ORDER BY p.transaction_date DESC
");

$page_title = 'Payments';
$active = 'payments';
require __DIR__ . '/../includes/header.php';
?>

<?php if ($eligible->num_rows > 0): ?>
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Approved Bookings Awaiting Payment</h2>
            <p class="desc">Submit a payment transaction for these confirmed bookings.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <tr><th>Vehicle</th><th>Dates</th><th>Days</th><th>Suggested Amount</th><th>Action</th></tr>
            <?php while ($e = $eligible->fetch_assoc()):
                $suggested = $e['days'] * $e['daily_rate']; ?>
                <tr>
                    <td><?= h($e['vehicle_name']) ?></td>
                    <td><?= h($e['start_date']) ?> &rarr; <?= h($e['end_date']) ?></td>
                    <td><?= (int)$e['days'] ?></td>
                    <td><?= h(format_money($suggested)) ?></td>
                    <td><button class="btn btn-primary btn-sm" onclick='openPay(<?= (int)$e['id'] ?>, <?= (float)$suggested ?>)'>Submit Payment</button></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Payment &amp; Ledger Management</h2>
            <p class="desc">Your transaction history, receipts, and amounts due.</p>
        </div>
    </div>
    <div class="table-wrap">
    <?php if ($payments->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#128179;</div>No payment records yet.</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Vehicle</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            <?php while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?= h($p['vehicle_name']) ?><br><span class="text-muted small"><?= h($p['start_date']) ?> &rarr; <?= h($p['end_date']) ?></span></td>
                    <td><?= h(format_money($p['amount'])) ?></td>
                    <td><?= h($p['payment_method']) ?></td>
                    <td><span class="badge <?= $p['status']==='paid' ? 'badge-ok' : 'badge-warn' ?>"><?= h($p['status']) ?></span></td>
                    <td><?= h($p['transaction_date']) ?></td>
                    <td class="row-actions">
                        <?php if ($p['status'] === 'due'): ?>
                            <button class="btn btn-outline btn-sm" onclick='openEditPayment(<?= (int)$p['id'] ?>, <?= (float)$p['amount'] ?>, "<?= h($p['payment_method']) ?>")'>Edit</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm" data-confirm="Confirm this payment of <?= h(format_money($p['amount'])) ?>?">Pay Now</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this draft payment record?">Delete</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" onclick="alert('Receipt #<?= (int)$p['id'] ?>\n<?= h(addslashes($p['vehicle_name'])) ?>\nAmount: <?= h(format_money($p['amount'])) ?>\nMethod: <?= h($p['payment_method']) ?>\nPaid on: <?= h($p['transaction_date']) ?>')">View Receipt</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- Submit Payment Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('payModal')">Close &times;</span>
        <h3>Submit Payment</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="booking_id" id="pay_booking_id">
            <div class="field"><label>Amount (BDT)</label><input type="number" step="0.01" name="amount" id="pay_amount" required></div>
            <div class="field">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option>Card</option><option>Mobile Banking</option><option>Bank Transfer</option><option>Cash</option>
                </select>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Continue</button></div>
        </form>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal-overlay" id="editPaymentModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('editPaymentModal')">Close &times;</span>
        <h3>Edit Payment Details</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_pay_id">
            <div class="field"><label>Amount (BDT)</label><input type="number" step="0.01" name="amount" id="edit_pay_amount" required></div>
            <div class="field">
                <label>Payment Method</label>
                <select name="payment_method" id="edit_pay_method">
                    <option>Card</option><option>Mobile Banking</option><option>Bank Transfer</option><option>Cash</option>
                </select>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openPay(bookingId, suggested) {
    document.getElementById('pay_booking_id').value = bookingId;
    document.getElementById('pay_amount').value = suggested.toFixed(2);
    openModal('payModal');
}
function openEditPayment(id, amount, method) {
    document.getElementById('edit_pay_id').value = id;
    document.getElementById('edit_pay_amount').value = amount;
    document.getElementById('edit_pay_method').value = method;
    openModal('editPaymentModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
