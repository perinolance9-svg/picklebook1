# Pickle Book: Pickleball Court Reservation System

Midterm project in Information Management 2 (PHP Data Objects).
Main entity: **Reservation**. Built with PHP 8, OOP, PDO prepared statements and MySQL.

## Requirements checklist

| Requirement | Where |
|---|---|
| Database table matching my ERD | `database.sql` (`reservations`, plus `users`, `courts`, `reservation_logs`) |
| Class named after main entity with CRUD + search | `classes/Reservation.php` |
| `addReservation()`, `getReservations()`, `updateReservation($id)`, `deleteReservation($id)`, `searchReservations($keyword)` | `classes/Reservation.php` |
| PDO prepared statements everywhere | every query in `classes/` |
| Error handling | `try/catch` with `PDOException`, `ReservationException`, rollback, `error_log` |
| **Sessions** | `includes/bootstrap.php` (secure session, 30-min timeout, flash messages, CSRF), `login.php`, `logout.php` |
| **At least 3 transactions** | `addReservation()`, `updateReservation()`, `deleteReservation()` each use `beginTransaction()` / `commit()` / `rollBack()` |

## Run locally (XAMPP / Laragon)

1. Copy this folder to `htdocs/pickle-book`.
2. Start Apache and MySQL, open phpMyAdmin, and import `database.sql`.
3. If your MySQL has a password, edit `config/database.php`.
4. Open `http://localhost/pickle-book/`.

Demo admin: `admin@picklebook.test` / `Admin@123` (change it). Players can create their own account.

## Snippets (using the class)

```php
$svc = new Reservation(Database::connect(), $userId, $isAdmin);

// Add
$id = $svc->addReservation([
    'court_id' => 1, 'player_name' => 'Ana Cruz', 'contact_number' => '0917 123 4567',
    'reservation_date' => '2026-11-05', 'start_time' => '17:00', 'duration' => 2, 'players_count' => 4,
]);

// Fetch all
$rows = $svc->getReservations();

// Update
$svc->updateReservation($id, ['court_id' => 2, /* ...same fields..., */ 'status' => 'Booked']);

// Delete
$svc->deleteReservation($id);

// Search
$rows = $svc->searchReservations('Court 2');
```

## Explanation

**Adding a record.** The player fills in the Book a court form (`reservation_form.php`). The form posts the data with a CSRF token. The page checks the token and passes `$_POST` to `addReservation()`. The class validates every field, then opens a transaction. It locks the chosen court row, checks that no active reservation overlaps the requested time, inserts the reservation with a prepared `INSERT`, creates the reference number (PB-000123), and writes an audit log row. If everything works the transaction is committed. If anything fails, it is rolled back so no half-saved booking exists.

**Fetching records.** `reservations.php` calls `getReservations()`. It runs one prepared `SELECT` that joins `reservations` with `courts`, so the court name is shown instead of an ID. Players only get their own rows, admins get all of them. The rows come back as an array and the page loops over them to build the table.

**Updating a record.** The Edit button opens the same form filled with the saved values. On save, `updateReservation($id, $data)` starts a transaction and locks the reservation row (this also confirms it belongs to the user). It validates the new data, re-checks court availability while ignoring this reservation itself, runs a prepared `UPDATE`, logs the change, and commits.

**Deleting a record.** The Delete button posts the ID (after a confirm prompt) to `reservation_delete.php`. `deleteReservation($id)` starts a transaction, locks the row, writes a DELETED entry to `reservation_logs`, runs `DELETE FROM reservations WHERE id = :id`, and commits. The row is permanently removed from the table, which frees that court time for other players. The log keeps a record of who deleted what.

**Searching records.** The search bar sends `?q=keyword` to `reservations.php`, which calls `searchReservations($keyword)`. The keyword is wrapped in `%...%` and checked with `LIKE` against reference number, player name, court name, contact number, date (both `2026-11-05` and `November 5, 2026` formats), status and notes, joined by `OR`. Matching rows are returned and shown in the same table. A status dropdown narrows the results further.

## Deploy and submit

1. **Free hosting.** Create an account on InfinityFree (or 000webhost / any PHP + MySQL host). Create a MySQL database, then import `database.sql` in phpMyAdmin (remove the first two lines `CREATE DATABASE` and `USE` if the host gives you a fixed database name).
2. Put the host's DB host, name, user and password in `config/database.php`.
3. Upload all files into the `htdocs` folder using the host's file manager or FTP.
4. Open your site URL and test: register, book, edit, search, delete, log out.
5. **GitHub.** Create a new repository named `pickle-book`, then:

```bash
git init
git add .
git commit -m "Pickle Book midterm: PHP PDO reservation system"
git branch -M main
git remote add origin https://github.com/<your-username>/pickle-book.git
git push -u origin main
```

6. Make the repository **Public**, or go to Settings, Collaborators and invite **@carljoshy**.
7. Submit: the **deployed link** and the **repository link**.

> Tip: never commit real passwords. If your host DB password is in `config/database.php`, keep the repository public with placeholder values and edit the real values only on the server.
