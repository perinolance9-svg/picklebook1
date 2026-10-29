<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('reservations.php'); }
verify_csrf();

try {
    reservations()->deleteReservation((int)($_POST['id'] ?? 0));
    flash('success', 'Reservation deleted.');
} catch (ReservationException $ex) {
    flash('error', $ex->getMessage());
}
redirect('reservations.php');
