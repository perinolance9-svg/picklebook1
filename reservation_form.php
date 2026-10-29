<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$svc = reservations();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;
$error = '';

try {
    $courts = $svc->getCourts();
    $existing = $editing ? $svc->getReservation($id) : null;
    if ($editing && !$existing) { flash('error', 'Reservation not found.'); redirect('reservations.php'); }
} catch (ReservationException $ex) {
    flash('error', $ex->getMessage()); redirect('reservations.php');
}

// Form values: existing record, or sensible defaults for a new booking
$form = $existing ? [
    'court_id' => $existing['court_id'], 'player_name' => $existing['player_name'],
    'contact_number' => $existing['contact_number'], 'reservation_date' => $existing['reservation_date'],
    'start_time' => substr($existing['start_time'], 0, 5),
    'duration' => (int)substr($existing['end_time'], 0, 2) - (int)substr($existing['start_time'], 0, 2),
    'players_count' => $existing['players_count'], 'status' => $existing['status'], 'notes' => $existing['notes'],
] : [
    'court_id' => '', 'player_name' => current_user()['full_name'], 'contact_number' => '',
    'reservation_date' => date('Y-m-d'), 'start_time' => '17:00', 'duration' => 1,
    'players_count' => 4, 'status' => 'Booked', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = array_merge($form, array_intersect_key($_POST, $form));
    try {
        if ($editing) {
            $svc->updateReservation($id, $_POST);
            flash('success', 'Reservation updated.');
        } else {
            $newId = $svc->addReservation($_POST);
            flash('success', 'Court booked. Your reference is ' . sprintf('PB-%06d', $newId) . '.');
        }
        redirect('reservations.php');
    } catch (ReservationException $ex) {
        $error = $ex->getMessage();
    }
}

$pageTitle = $editing ? 'Edit reservation' : 'Book a court';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= $editing ? 'Edit reservation ' . e($existing['reference_no']) : 'Book a court' ?></h1>
    <p class="muted">Courts are open 6:00 AM to 10:00 PM. Each slot is booked by the hour.</p>
  </div>
  <a class="btn btn-ghost" href="reservations.php">Back to reservations</a>
</div>

<form class="card form" method="post" novalidate>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="grid">
    <label>Player name
      <input type="text" name="player_name" maxlength="100" value="<?= e($form['player_name']) ?>" required>
    </label>
    <label>Contact number
      <input type="tel" name="contact_number" maxlength="20" placeholder="0917 123 4567" value="<?= e($form['contact_number']) ?>" required>
    </label>

    <label>Court
      <select name="court_id" required>
        <option value="">Choose a court</option>
        <?php foreach ($courts as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$form['court_id'] === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?> (<?= e($c['surface']) ?>, <?= money((float)$c['hourly_rate']) ?>/hr)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Date
      <input type="date" name="reservation_date" value="<?= e($form['reservation_date']) ?>" <?= $editing ? '' : 'min="' . date('Y-m-d') . '"' ?> required>
    </label>

    <label>Start time
      <select name="start_time" required>
        <?php for ($h = Reservation::OPEN_HOUR; $h < Reservation::CLOSE_HOUR; $h++):
            $v = sprintf('%02d:00', $h); ?>
          <option value="<?= $v ?>" <?= $form['start_time'] === $v ? 'selected' : '' ?>><?= date('g:i A', strtotime($v)) ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>Duration
      <select name="duration" required>
        <?php foreach ([1, 2, 3] as $d): ?>
          <option value="<?= $d ?>" <?= (int)$form['duration'] === $d ? 'selected' : '' ?>><?= $d ?> hour<?= $d > 1 ? 's' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Number of players
      <select name="players_count" required>
        <?php for ($n = 1; $n <= 4; $n++): ?>
          <option value="<?= $n ?>" <?= (int)$form['players_count'] === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <?php if ($editing): ?>
    <label>Status
      <select name="status">
        <?php foreach (Reservation::STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>

    <label class="span-2">Notes <small>Optional</small>
      <textarea name="notes" rows="3" maxlength="255" placeholder="Paddle rental, birthday group, etc."><?= e($form['notes']) ?></textarea>
    </label>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Book court' ?></button>
    <a class="btn btn-ghost" href="reservations.php">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
