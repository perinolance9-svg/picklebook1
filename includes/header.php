<?php
$pageTitle = $pageTitle ?? 'Pickle Book';
$flash = pull_flash();
$u = current_user();
$self = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Pickle Book</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='15' fill='%23D9E94C'/%3E%3Ccircle cx='11' cy='11' r='2' fill='%231D4E6E'/%3E%3Ccircle cx='20' cy='10' r='2' fill='%231D4E6E'/%3E%3Ccircle cx='16' cy='17' r='2' fill='%231D4E6E'/%3E%3Ccircle cx='9' cy='20' r='2' fill='%231D4E6E'/%3E%3Ccircle cx='22' cy='21' r='2' fill='%231D4E6E'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php" aria-label="Pickle Book home">
    <svg width="30" height="30" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="15" fill="#D9E94C"/><g fill="#1D4E6E"><circle cx="11" cy="11" r="2"/><circle cx="20" cy="10" r="2"/><circle cx="16" cy="17" r="2"/><circle cx="9" cy="20" r="2"/><circle cx="22" cy="21" r="2"/></g></svg>
    <span>Pickle Book</span>
  </a>
  <?php if (is_logged_in()): ?>
  <nav class="nav" aria-label="Main">
    <a href="reservations.php" <?= $self === 'reservations.php' ? 'aria-current="page"' : '' ?>>Reservations</a>
    <a href="reservation_form.php" <?= $self === 'reservation_form.php' && empty($_GET['id']) ? 'aria-current="page"' : '' ?>>Book a court</a>
  </nav>
  <div class="who">
    <span class="who-name"><?= e($u['full_name']) ?><?php if (is_admin()): ?> <span class="tag tag-admin">Admin</span><?php endif; ?></span>
    <a class="btn btn-ghost btn-sm" href="logout.php">Log out</a>
  </div>
  <?php endif; ?>
</header>
<main class="wrap">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>
