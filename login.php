<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect('reservations.php'); }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    try {
        $user = (new User(Database::connect()))->login($email, $_POST['password'] ?? '');
        if ($user) {
            login_user($user);
            flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect('reservations.php');
        }
        $error = 'Email or password is incorrect.';
    } catch (ReservationException $ex) {
        $error = $ex->getMessage();
    }
}
$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
  <div class="auth-pitch">
    <h1>Book a court.<br>Show up. Play.</h1>
    <p>Reserve one of four pickleball courts in under a minute. No double bookings, no phone calls.</p>
    <svg class="court-art" viewBox="0 0 300 160" aria-hidden="true">
      <rect x="2" y="2" width="296" height="156" rx="4" fill="none" stroke="currentColor" stroke-width="3"/>
      <line x1="150" y1="2" x2="150" y2="158" stroke="currentColor" stroke-width="3" stroke-dasharray="4 5"/>
      <line x1="62" y1="2" x2="62" y2="158" stroke="currentColor" stroke-width="2"/>
      <line x1="238" y1="2" x2="238" y2="158" stroke="currentColor" stroke-width="2"/>
      <line x1="62" y1="80" x2="238" y2="80" stroke="currentColor" stroke-width="2"/>
    </svg>
  </div>
  <form class="card auth-card" method="post" novalidate>
    <h2>Log in</h2>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
    <p class="muted center">New here? <a href="register.php">Create an account</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
