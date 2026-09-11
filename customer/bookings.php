   <div class="table-wrap">
    <?php if ($bookings->num_rows === 0): ?>
        <div class="empty-state"><div class="glyph">&#128663;</div>No bookings yet. Create your first request above.</div>
    <?php else: ?>
        <table class="data">
            <tr><th>Vehicle</th><th>Start</th><th>End</th><th>Pickup Location</th><th>Rate/day</th><th>Status</th><th>Actions</th></tr>
            <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><?= h($b['vehicle_name']) ?><br><span class="text-muted small"><?= h($b['vehicle_type']) ?></span></td>
                    <td><?= h($b['start_date']) ?></td>
                    <td><?= h($b['end_date']) ?></td>
                    <td><?= h($b['pickup_location']) ?></td>
                    <td><?= h(format_money($b['daily_rate'])) ?></td>
                    <td>
                        <?php $map = ['pending'=>'badge-warn','approved'=>'badge-ok','denied'=>'badge-danger','completed'=>'badge-info','cancelled'=>'badge-muted']; ?>
                        <span class="badge <?= $map[$b['status']] ?>"><?= h($b['status']) ?></span>
                    </td>
                    <td class="row-actions">
                        <?php if ($b['status'] === 'pending'): ?>
                            <button class="btn btn-outline btn-sm" onclick='openEditBooking(<?= (int)$b['id'] ?>, "<?= h($b['start_date']) ?>", "<?= h($b['end_date']) ?>", "<?= h(addslashes($b['pickup_location'])) ?>")'>Edit</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Cancel this booking request?">Cancel</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- New Booking Modal -->
<div class="modal-overlay" id="newBookingModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('newBookingModal')">Close &times;</span>
        <h3>New Booking Request</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="field">
                <label for="vehicle_id">Vehicle</label>
                <select name="vehicle_id" id="vehicle_id" required>
                    <option value="">Select a vehicle&hellip;</option>
                    <?php while ($v = $vehicles->fetch_assoc()): ?>
                        <option value="<?= (int)$v['id'] ?>"><?= h($v['vehicle_name']) ?> (<?= h($v['vehicle_type']) ?>) &mdash; <?= h(format_money($v['daily_rate'])) ?>/day</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="field"><label for="start_date">Start Date</label><input type="date" name="start_date" id="start_date" required></div>
                <div class="field"><label for="end_date">End Date</label><input type="date" name="end_date" id="end_date" required></div>
            </div>
            <div class="field">
                <label for="pickup_location">Pick-up Location</label>
                <input type="text" name="pickup_location" id="pickup_location" placeholder="e.g. Gulshan-1 Circle, Dhaka" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Booking Modal -->
<div class="modal-overlay" id="editBookingModal">
    <div class="modal-box">
        <span class="modal-close" onclick="closeModal('editBookingModal')">Close &times;</span>
        <h3>Update Booking</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid-2">
                <div class="field"><label>Start Date</label><input type="date" name="start_date" id="edit_start_date" required></div>
                <div class="field"><label>End Date</label><input type="date" name="end_date" id="edit_end_date" required></div>
            </div>
            <div class="field">
                <label>Pick-up Location</label>
                <input type="text" name="pickup_location" id="edit_pickup_location" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditBooking(id, start, end, pickup) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_start_date').value = start;
    document.getElementById('edit_end_date').value = end;
    document.getElementById('edit_pickup_location').value = pickup;
    openModal('editBookingModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
