<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$keyword = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$rows = []; $summary = ['upcoming' => 0, 'today' => 0, 'revenue' => 0, 'total' => 0];
try {
    $svc = reservations();
    $summary = $svc->getSummary();
    $rows = $keyword !== '' ? $svc->searchReservations($keyword, $status) : $svc->getReservations($status);
} catch (ReservationException $ex) {
    flash('error', $ex->getMessage());
}
$pageTitle = 'Reservations';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= is_admin() ? 'All reservations' : 'My reservations' ?></h1>
    <p class="muted"><?= $summary['today'] ?> on today &middot; <?= $summary['upcoming'] ?> upcoming &middot; <?= money($summary['revenue']) ?> in court fees</p>
  </div>
  <a class="btn btn-primary" href="reservation_form.php">Book a court</a>
</div>

<form class="toolbar" method="get" role="search">
  <input type="search" name="q" value="<?= e($keyword) ?>" placeholder="Search name, court, reference, date or status" aria-label="Search reservations">
  <select name="status" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (Reservation::STATUSES as $s): ?>
      <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-dark" type="submit">Search</button>
  <?php if ($keyword !== '' || $status !== ''): ?><a class="btn btn-ghost" href="reservations.php">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="card empty">
    <h2><?= ($keyword !== '' || $status !== '') ? 'No reservations match your search' : 'No reservations yet' ?></h2>
    <p class="muted"><?= ($keyword !== '' || $status !== '') ? 'Try a different keyword or clear the filters.' : 'Book your first court and it will show up here.' ?></p>
    <a class="btn btn-primary" href="reservation_form.php">Book a court</a>
  </div>
<?php else: ?>
<p class="muted result-count"><?= count($rows) ?> reservation<?= count($rows) === 1 ? '' : 's' ?><?= $keyword !== '' ? ' for &ldquo;' . e($keyword) . '&rdquo;' : '' ?></p>
<div class="table-wrap">
<table class="table">
  <thead><tr>
    <th>Reference</th><th>Player</th><th>Court</th><th>Date</th><th>Time</th><th>Fee</th><th>Status</th><th><span class="sr">Actions</span></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td data-label="Reference"><strong><?= e($r['reference_no']) ?></strong></td>
      <td data-label="Player"><?= e($r['player_name']) ?><small class="sub"><?= e($r['contact_number']) ?> &middot; <?= (int)$r['players_count'] ?> player<?= $r['players_count'] > 1 ? 's' : '' ?></small></td>
      <td data-label="Court"><?= e($r['court_name']) ?></td>
      <td data-label="Date"><?= e(date('D, M j, Y', strtotime($r['reservation_date']))) ?></td>
      <td data-label="Time"><?= e(date('g:i A', strtotime($r['start_time']))) ?> &ndash; <?= e(date('g:i A', strtotime($r['end_time']))) ?></td>
      <td data-label="Fee"><?= money((float)$r['total_fee']) ?></td>
      <td data-label="Status"><span class="tag tag-<?= strtolower(e($r['status'])) ?>"><?= e($r['status']) ?></span></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="reservation_form.php?id=<?= (int)$r['id'] ?>">Edit</a>
        <form method="post" action="reservation_delete.php" onsubmit="return confirm('Delete reservation <?= e($r['reference_no']) ?>? This cannot be undone.');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
