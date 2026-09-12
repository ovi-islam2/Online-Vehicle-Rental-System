<?php
require_once __DIR__ . '/../includes/init.php';
require_role('manager');
$uid = $_SESSION['user_id'];

// ---- CREATE (log a complaint) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $reporter_id = (int)($_POST['reporter_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($reporter_id && $subject !== '' && $description !== '') {
        $stmt = $conn->prepare('INSERT INTO complaints (manager_id, reporter_id, subject, description, status) VALUES (?, ?, ?, ?, "pending")');
        $stmt->bind_param('iiss', $uid, $reporter_id, $subject, $description);
        $stmt->execute();
        $stmt->close();
        flash('ok', 'Complaint logged.');
    } else {
        flash('err', 'Please select a reporter and fill subject/description.');
    }
    redirect('complaints.php');
}

// ---- UPDATE STATUS / details ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $stmt = $conn->prepare('UPDATE complaints SET subject=?, description=?, status=?, manager_id=? WHERE id=?');
    $stmt->bind_param('sssii', $subject, $description, $status, $uid, $id);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Complaint updated.');
    redirect('complaints.php');
}

// ---- DELETE (archive resolved tickets) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM complaints WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Complaint ticket archived.');
    redirect('complaints.php');
}

$reporters = $conn->query("SELECT id, name, role FROM users WHERE role IN ('customer','owner') ORDER BY name");
$reporters_list = [];
while ($r = $reporters->fetch_assoc()) $reporters_list[] = $r;

$filter = $_GET['status'] ?? 'all';
$allowed_filters = ['all', 'pending', 'investigating', 'resolved'];
if (!in_array($filter, $allowed_filters, true)) $filter = 'all';
$where = $filter === 'all' ? '1=1' : "c.status='" . $conn->real_escape_string($filter) . "'";

$complaints = $conn->query("
    SELECT c.*, u.name AS reporter_name, u.role AS reporter_role FROM complaints c
    JOIN users u ON u.id=c.reporter_id
    WHERE $where ORDER BY c.created_at DESC
");

$page_title = 'Complaints';
$active = 'complaints';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Complaint Resolution Center</h2>
            <p class="desc">Log customer/owner complaints and track them through to resolution.</p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('addComplaintModal')">+ Log Complaint</button>
    </div>
    <div class="pill-nav">
        <?php foreach ($allowed_filters as $f): ?>
            <a href="?status=<?= $f ?>" class="<?= $filter === $f ? 'active' : '' ?>"><?= ucfirst($f) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <?php if ($complaints->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#128172;</div>No complaint tickets in this view.</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Reporter</th><th>Subject</th><th>Description</th><th>Status</th><th>Logged</th><th>Actions</th></tr>
            <?php while ($c = $complaints->fetch_assoc()): ?>
                <tr>
                    <td><?= h($c['reporter_name']) ?><br><span class="text-muted small"><?= h(ucfirst($c['reporter_role'])) ?></span></td>
                    <td><?= h($c['subject']) ?></td>
                    <td class="small"><?= h($c['description']) ?></td>
                    <td>
                        <?php $map = ['pending'=>'badge-warn','investigating'=>'badge-info','resolved'=>'badge-ok']; ?>
                        <span class="badge <?= $map[$c['status']] ?>"><?= h($c['status']) ?></span>
                    </td>
                    <td class="small text-muted"><?= h($c['created_at']) ?></td>
                    <td class="row-actions">
                        <button class="btn btn-outline btn-sm" onclick='openEditComplaint(<?= (int)$c['id'] ?>, "<?= h(addslashes($c['subject'])) ?>", "<?= h(addslashes($c['description'])) ?>", "<?= h($c['status']) ?>")'>Update</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Archive/delete this resolved ticket?">Archive</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- Log Complaint Modal -->
<div class="modal-overlay" id="addComplaintModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('addComplaintModal')">Close &times;</span>
        <h3>Log a Complaint</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label>Reporter (Customer / Owner)</label>
                <select name="reporter_id" required>
                    <option value="">Select&hellip;</option>
                    <?php foreach ($reporters_list as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['name']) ?> (<?= h(ucfirst($r['role'])) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Subject</label><input type="text" name="subject" required></div>
            <div class="field"><label>Description</label><textarea name="description" required></textarea></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Log Complaint</button></div>
        </form>
    </div>
</div>

<!-- Edit Complaint Modal -->
<div class="modal-overlay" id="editComplaintModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('editComplaintModal')">Close &times;</span>
        <h3>Update Complaint</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_c_id">
            <div class="field"><label>Subject</label><input type="text" name="subject" id="edit_c_subject" required></div>
            <div class="field"><label>Description</label><textarea name="description" id="edit_c_description" required></textarea></div>
            <div class="field">
                <label>Status</label>
                <select name="status" id="edit_c_status">
                    <option value="pending">Pending</option>
                    <option value="investigating">Investigating</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-block">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
function openEditComplaint(id, subject, description, status) {
    document.getElementById('edit_c_id').value = id;
    document.getElementById('edit_c_subject').value = subject;
    document.getElementById('edit_c_description').value = description;
    document.getElementById('edit_c_status').value = status;
    openModal('editComplaintModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
