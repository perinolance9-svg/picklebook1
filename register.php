<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect('reservations.php'); }

$error = '';
$old = ['full_name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old = ['full_name' => trim($_POST['full_name'] ?? ''), 'email' => trim($_POST['email'] ?? '')];
    try {
        if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
            throw new ReservationException('The two passwords do not match.');
        }
        $users = new User(Database::connect());
        $users->register($old['full_name'], $old['email'], $_POST['password'] ?? '');
        login_user($users->login($old['email'], $_POST['password']));
        flash('success', 'Account created. You can book your first court now.');
        redirect('reservation_form.php');
    } catch (ReservationException $ex) {
        $error = $ex->getMessage();
    }
}
$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="auth auth-single">
  <form class="card auth-card" method="post" novalidate>
    <h2>Create your account</h2>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Full name
      <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" autocomplete="name" required autofocus>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
    </label>
    <label>Password <small>At least 8 characters</small>
      <input type="password" name="password" minlength="8" autocomplete="new-password" required>
    </label>
    <label>Confirm password
      <input type="password" name="confirm" minlength="8" autocomplete="new-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
    <p class="muted center">Already registered? <a href="login.php">Log in</a></p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
