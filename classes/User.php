<?php
class User
{
    public function __construct(private PDO $db) {}

    public function register(string $name, string $email, string $password): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ReservationException('Enter your name and a valid email address.');
        }
        if (strlen($password) < 8) {
            throw new ReservationException('Password must be at least 8 characters.');
        }
        try {
            $stmt = $this->db->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (:n, :e, :p)');
            $stmt->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT)]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ReservationException('That email is already registered. Try logging in.');
            }
            error_log($e->getMessage());
            throw new ReservationException('Could not create your account. Please try again.');
        }
    }

    public function login(string $email, string $password): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => strtolower(trim($email))]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new ReservationException('Login is unavailable right now. Please try again.');
        }
        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            return $user;
        }
        return null;
    }
}
