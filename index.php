<?php
require __DIR__ . '/includes/bootstrap.php';
redirect(is_logged_in() ? 'reservations.php' : 'login.php');
