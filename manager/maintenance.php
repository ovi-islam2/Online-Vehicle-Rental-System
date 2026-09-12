<?php
require_once __DIR__ . '/../includes/init.php';
require_role('manager');
$uid = $_SESSION['user_id'];

// ---- CREATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $service_type = trim($_POST['service_type'] ?? '');
    $date = $_POST['scheduled_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($vehicle_id && $service_type !== '' && $date) {
        $stmt = $conn->prepare('INSERT INTO maintenance (manager_id, vehicle_id, service_type, scheduled_date, notes, status) VALUES (?, ?, ?, ?, ?, "scheduled")');
        $stmt->bind_param('iisss', $uid, $vehicle_id, $service_type, $date, $notes);
        $stmt->execute();
        $stmt->close();
        flash('ok', 'Maintenance scheduled.');
    } else {
        flash('err', 'Please fill in vehicle, service type and date.');
    }
    redirect('maintenance.php');
}

// ---- UPDATE (progress status / details) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $service_type = trim($_POST['service_type'] ?? '');
    $date = $_POST['scheduled_date'] ?? '';
    $status = $_POST['status'] ?? 'scheduled';
    $notes = trim($_POST['notes'] ?? '');
    $stmt = $conn->prepare('UPDATE maintenance SET service_type=?, scheduled_date=?, status=?, notes=? WHERE id=?');
    $stmt->bind_param('ssssi', $service_type, $date, $status, $notes, $id);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Maintenance log updated.');
    redirect('maintenance.php');
}

// ---- DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM maintenance WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Maintenance log deleted.');
    redirect('maintenance.php');
}

$vehicles = $conn->query('SELECT id, vehicle_name FROM vehicles ORDER BY vehicle_name');
$vehicles_list = [];
while ($v = $vehicles->fetch_assoc()) $vehicles_list[] = $v;

$logs = $conn->query("
    SELECT m.*, v.vehicle_name FROM maintenance m JOIN vehicles v ON v.id=m.vehicle_id
    ORDER BY m.scheduled_date DESC
");

$page_title = 'Maintenance Scheduler';
$active = 'maintenance';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Maintenance &amp; Servicing Scheduler</h2>
            <p class="desc">Schedule vehicle servicing/repairs and track progress across the fleet.</p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('addMaintModal')">+ Schedule Service</button>
    </div>
    <div class="table-wrap">
    <?php if ($logs->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#128295;</div>No maintenance logs yet.</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Vehicle</th><th>Service Type</th><th>Scheduled Date</th><th>Status</th><th>Notes</th><th>Actions</th></tr>
            <?php while ($m = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?= h($m['vehicle_name']) ?></td>
                    <td><?= h($m['service_type']) ?></td>
                    <td><?= h($m['scheduled_date']) ?></td>
                    <td>
                        <?php $map = ['scheduled'=>'badge-warn','in_progress'=>'badge-info','completed'=>'badge-ok']; ?>
                        <span class="badge <?= $map[$m['status']] ?>"><?= h(str_replace('_',' ',$m['status'])) ?></span>
                    </td>
                    <td class="small text-muted"><?= h($m['notes']) ?></td>
                    <td class="row-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditMaint(<?= (int)$m['id'] ?>, "<?= h(addslashes($m['service_type'])) ?>", "<?= h($m['scheduled_date']) ?>", "<?= h($m['status']) ?>", "<?= h(addslashes($m['notes'])) ?>")'>Update</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this maintenance log?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- Add Maintenance Modal -->
<div class="modal-overlay" id="addMaintModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('addMaintModal')">Close &times;</span>
        <h3>Schedule Servicing</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label>Vehicle</label>
                <select name="vehicle_id" required>
                    <?php foreach ($vehicles_list as $v): ?><option value="<?= (int)$v['id'] ?>"><?= h($v['vehicle_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Service Type</label><input type="text" name="service_type" placeholder="e.g. Oil change, Brake inspection" required></div>
            <div class="field"><label>Scheduled Date</label><input type="date" name="scheduled_date" required></div>
            <div class="field"><label>Notes</label><textarea name="notes" placeholder="Optional notes"></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Schedule</button></div>
        </form>
    </div>
</div>

<!-- Edit Maintenance Modal -->
<div class="modal-overlay" id="editMaintModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('editMaintModal')">Close &times;</span>
        <h3>Update Maintenance Log</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_m_id">
            <div class="field"><label>Service Type</label><input type="text" name="service_type" id="edit_m_type" required></div>
            <div class="grid-2">
                <div class="field"><label>Scheduled Date</label><input type="date" name="scheduled_date" id="edit_m_date" required></div>
                <div class="field">
                    <label>Status</label>
                    <select name="status" id="edit_m_status">
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <div class="field"><label>Notes</label><textarea name="notes" id="edit_m_notes"></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEditMaint(id, type, date, status, notes) {
    document.getElementById('edit_m_id').value = id;
    document.getElementById('edit_m_type').value = type;
    document.getElementById('edit_m_date').value = date;
    document.getElementById('edit_m_status').value = status;
    document.getElementById('edit_m_notes').value = notes;
    openModal('editMaintModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
