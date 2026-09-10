<?php
require_once __DIR__ . '/../includes/init.php';
require_role('customer');
$uid = $_SESSION['user_id'];

// ---- CREATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';
    $pickup = trim($_POST['pickup_location'] ?? '');

    if ($vehicle_id && $start && $end && $pickup && $start <= $end) {
        $stmt = $conn->prepare('INSERT INTO bookings (customer_id, vehicle_id, start_date, end_date, pickup_location, status) VALUES (?, ?, ?, ?, ?, "pending")');
        $stmt->bind_param('iisss', $uid, $vehicle_id, $start, $end, $pickup);
        $stmt->execute();
        $stmt->close();
        flash('ok', 'Booking request submitted. Waiting for manager approval.');
    } else {
        flash('err', 'Please fill all fields correctly (end date must not be before start date).');
    }
    redirect('bookings.php');
}

// ---- UPDATE (only while pending) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $start = $_POST['start_date'] ?? '';
    $end   = $_POST['end_date'] ?? '';
    $pickup = trim($_POST['pickup_location'] ?? '');

    $stmt = $conn->prepare('UPDATE bookings SET start_date=?, end_date=?, pickup_location=? WHERE id=? AND customer_id=? AND status="pending"');
    $stmt->bind_param('sssii', $start, $end, $pickup, $id, $uid);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Booking updated.');
    redirect('bookings.php');
}

// ---- DELETE / CANCEL (only while pending) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM bookings WHERE id=? AND customer_id=? AND status="pending"');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $stmt->close();
    flash('ok', 'Booking cancelled.');
    redirect('bookings.php');
}

$vehicles = $conn->query("SELECT id, vehicle_name, vehicle_type, daily_rate FROM vehicles WHERE status='available' ORDER BY vehicle_name");
$bookings = $conn->query("SELECT b.*, v.vehicle_name, v.vehicle_type, v.daily_rate FROM bookings b JOIN vehicles v ON v.id=b.vehicle_id WHERE b.customer_id=$uid ORDER BY b.created_at DESC");

$page_title = 'Bookings';
$active = 'bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Booking &amp; Reservation Management</h2>
            <p class="desc">Request vehicles, track approval status, and manage upcoming rentals.</p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openModal('newBookingModal')">+ New Booking</button>
    </div>


