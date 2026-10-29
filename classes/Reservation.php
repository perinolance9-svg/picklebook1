<?php
/**
 * Reservation: the main entity of Pickle Book.
 * All CRUD + search live here and use PDO prepared statements.
 * add / update / delete each run inside a database TRANSACTION.
 */
class Reservation
{
    public const OPEN_HOUR  = 6;   // first bookable hour (06:00)
    public const CLOSE_HOUR = 22;  // courts close at 22:00
    public const STATUSES   = ['Booked', 'Completed', 'Cancelled'];

    /** @param int $actorId   logged-in user performing the action
     *  @param bool $isAdmin  admins see and manage every reservation; players only their own */
    public function __construct(private PDO $db, private int $actorId, private bool $isAdmin = false) {}

    /* ------------------------------------------------------------------ READ */

    public function getCourts(): array
    {
        return $this->db->query('SELECT id, name, surface, hourly_rate FROM courts WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    /** Fetch all reservations visible to the current user. */
    public function getReservations(string $status = ''): array
    {
        return $this->run($this->baseSelect() . $this->scope($p) . $this->statusFilter($status, $p) . $this->order(), $p);
    }

    /** Fetch a single reservation (null if missing or not yours). */
    public function getReservation(int $id): ?array
    {
        $p = [':id' => $id];
        $rows = $this->run($this->baseSelect() . ' AND r.id = :id' . $this->scope($p) . ' LIMIT 1', $p);
        return $rows[0] ?? null;
    }

    /** Search the keyword across reference, player, court, contact, date, status and notes. */
    public function searchReservations(string $keyword, string $status = ''): array
    {
        $kw = '%' . addcslashes(trim($keyword), '%_\\') . '%';
        $p = [];
        foreach (range(1, 8) as $i) { $p[":k$i"] = $kw; }   // one placeholder per use (native prepares)
        $sql = $this->baseSelect() . "
            AND (r.reference_no LIKE :k1 OR r.player_name LIKE :k2 OR c.name LIKE :k3
              OR r.contact_number LIKE :k4 OR r.reservation_date LIKE :k5
              OR DATE_FORMAT(r.reservation_date, '%M %e, %Y') LIKE :k6
              OR r.status LIKE :k7 OR r.notes LIKE :k8)"
            . $this->scope($p) . $this->statusFilter($status, $p) . $this->order();
        return $this->run($sql, $p);
    }

    /** Dashboard numbers for the current user's scope. */
    public function getSummary(): array
    {
        $p = [];
        $sql = "SELECT
                  SUM(r.status = 'Booked' AND r.reservation_date >= CURDATE()) AS upcoming,
                  SUM(r.reservation_date = CURDATE() AND r.status <> 'Cancelled') AS today,
                  COALESCE(SUM(CASE WHEN r.status <> 'Cancelled' THEN r.total_fee END), 0) AS revenue,
                  COUNT(*) AS total
                FROM reservations r WHERE 1=1" . $this->scope($p);
        $row = $this->run($sql, $p)[0] ?? [];
        return ['upcoming' => (int)($row['upcoming'] ?? 0), 'today' => (int)($row['today'] ?? 0),
                'revenue' => (float)($row['revenue'] ?? 0), 'total' => (int)($row['total'] ?? 0)];
    }

    /* ----------------------------------------------------- CREATE (TRANSACTION 1) */

    public function addReservation(array $data): int
    {
        $c = $this->clean($data, null);
        try {
            $this->db->beginTransaction();
            $rate = $this->lockCourt($c['court_id']);                 // serialises bookings per court
            $this->assertSlotFree($c, 0);                              // no double-booking
            $stmt = $this->db->prepare(
                'INSERT INTO reservations (user_id, court_id, player_name, contact_number, reservation_date,
                        start_time, end_time, players_count, total_fee, status, notes)
                 VALUES (:u, :court, :pn, :cn, :d, :s, :e, :pc, :fee, "Booked", :notes)');
            $stmt->execute([':u' => $this->actorId, ':court' => $c['court_id'], ':pn' => $c['player_name'],
                ':cn' => $c['contact_number'], ':d' => $c['reservation_date'], ':s' => $c['start_time'],
                ':e' => $c['end_time'], ':pc' => $c['players_count'], ':fee' => $rate * $c['duration'],
                ':notes' => $c['notes']]);
            $id = (int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE reservations SET reference_no = :ref WHERE id = :id')
                     ->execute([':ref' => sprintf('PB-%06d', $id), ':id' => $id]);
            $this->log($id, 'CREATED', "{$c['reservation_date']} {$c['start_time']}-{$c['end_time']}");
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            throw $this->fail($e, 'Could not save the reservation.');
        }
    }

    /* ----------------------------------------------------- UPDATE (TRANSACTION 2) */

    public function updateReservation(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();
            $old = $this->lockReservation($id);
            $c = $this->clean($data, $old['reservation_date']);
            $rate = $this->lockCourt($c['court_id']);
            if ($c['status'] !== 'Cancelled') {
                $this->assertSlotFree($c, $id);                        // ignore this reservation itself
            }
            $this->db->prepare(
                'UPDATE reservations SET court_id=:court, player_name=:pn, contact_number=:cn, reservation_date=:d,
                        start_time=:s, end_time=:e, players_count=:pc, total_fee=:fee, status=:st, notes=:notes
                 WHERE id=:id')
                ->execute([':court' => $c['court_id'], ':pn' => $c['player_name'], ':cn' => $c['contact_number'],
                    ':d' => $c['reservation_date'], ':s' => $c['start_time'], ':e' => $c['end_time'],
                    ':pc' => $c['players_count'], ':fee' => $rate * $c['duration'], ':st' => $c['status'],
                    ':notes' => $c['notes'], ':id' => $id]);
            $this->log($id, 'UPDATED', "{$c['reservation_date']} {$c['start_time']}-{$c['end_time']} ({$c['status']})");
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            throw $this->fail($e, 'Could not update the reservation.');
        }
    }

    /* ----------------------------------------------------- DELETE (TRANSACTION 3) */

    public function deleteReservation(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            $old = $this->lockReservation($id);
            $this->log($id, 'DELETED', "{$old['reference_no']} {$old['player_name']} {$old['reservation_date']}");
            $this->db->prepare('DELETE FROM reservations WHERE id = :id')->execute([':id' => $id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            throw $this->fail($e, 'Could not delete the reservation.');
        }
    }

    /* ---------------------------------------------------------------- helpers */

    private function baseSelect(): string
    {
        return 'SELECT r.*, c.name AS court_name, c.surface FROM reservations r
                JOIN courts c ON c.id = r.court_id WHERE 1=1';
    }

    private function order(): string { return ' ORDER BY r.reservation_date DESC, r.start_time DESC'; }

    /** Players only see their own rows; admins see everything. */
    private function scope(?array &$p): string
    {
        $p ??= [];
        if ($this->isAdmin) { return ''; }
        $p[':scope_uid'] = $this->actorId;
        return ' AND r.user_id = :scope_uid';
    }

    private function statusFilter(string $status, array &$p): string
    {
        if (!in_array($status, self::STATUSES, true)) { return ''; }
        $p[':status_f'] = $status;
        return ' AND r.status = :status_f';
    }

    private function run(string $sql, array $params): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Reservation query failed: ' . $e->getMessage());
            throw new ReservationException('Could not load reservations. Please try again.');
        }
    }

    /** Row-lock a reservation the user is allowed to touch. */
    private function lockReservation(int $id): array
    {
        $p = [':id' => $id];
        $stmt = $this->db->prepare('SELECT * FROM reservations r WHERE r.id = :id' . $this->scope($p) . ' FOR UPDATE');
        $stmt->execute($p);
        $row = $stmt->fetch();
        if (!$row) { throw new ReservationException('Reservation not found.'); }
        return $row;
    }

    /** Lock the court row so two people cannot book the same slot at once. Returns hourly rate. */
    private function lockCourt(int $courtId): float
    {
        $stmt = $this->db->prepare('SELECT hourly_rate FROM courts WHERE id = :id AND is_active = 1 FOR UPDATE');
        $stmt->execute([':id' => $courtId]);
        $rate = $stmt->fetchColumn();
        if ($rate === false) { throw new ReservationException('Please choose an available court.'); }
        return (float)$rate;
    }

    private function assertSlotFree(array $c, int $ignoreId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE court_id = :court AND reservation_date = :d AND status <> 'Cancelled' AND id <> :ignore
               AND start_time < :e AND end_time > :s");
        $stmt->execute([':court' => $c['court_id'], ':d' => $c['reservation_date'], ':ignore' => $ignoreId,
                        ':s' => $c['start_time'], ':e' => $c['end_time']]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new ReservationException('That court is already booked for the selected time. Pick another time or court.');
        }
    }

    private function log(int $reservationId, string $action, string $details): void
    {
        $this->db->prepare('INSERT INTO reservation_logs (reservation_id, user_id, action, details) VALUES (:r,:u,:a,:d)')
                 ->execute([':r' => $reservationId, ':u' => $this->actorId, ':a' => $action, ':d' => mb_substr($details, 0, 255)]);
    }

    /** Roll back and convert any failure into a safe, user-facing exception. */
    private function fail(Throwable $e, string $fallback): ReservationException
    {
        if ($this->db->inTransaction()) { $this->db->rollBack(); }
        if ($e instanceof ReservationException) { return $e; }
        error_log('Reservation error: ' . $e->getMessage());
        return new ReservationException($fallback . ' Please try again.');
    }

    /** Validate and normalise form input. $originalDate is set when editing. */
    private function clean(array $d, ?string $originalDate): array
    {
        $out = [
            'court_id'         => (int)($d['court_id'] ?? 0),
            'player_name'      => trim((string)($d['player_name'] ?? '')),
            'contact_number'   => trim((string)($d['contact_number'] ?? '')),
            'reservation_date' => trim((string)($d['reservation_date'] ?? '')),
            'duration'         => (int)($d['duration'] ?? 0),
            'players_count'    => (int)($d['players_count'] ?? 0),
            'status'           => $originalDate === null ? 'Booked' : (string)($d['status'] ?? 'Booked'),
            'notes'            => ($n = trim((string)($d['notes'] ?? ''))) === '' ? null : $n,
        ];
        $hour = (int)substr((string)($d['start_time'] ?? ''), 0, 2);

        if ($out['court_id'] < 1) { throw new ReservationException('Please choose a court.'); }
        if ($out['player_name'] === '' || mb_strlen($out['player_name']) > 100) {
            throw new ReservationException('Enter the player name (up to 100 characters).');
        }
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $out['contact_number'])) {
            throw new ReservationException('Enter a valid contact number.');
        }
        $date = DateTime::createFromFormat('Y-m-d', $out['reservation_date']);
        if (!$date || $date->format('Y-m-d') !== $out['reservation_date']) {
            throw new ReservationException('Choose a valid date.');
        }
        $isPast = $out['reservation_date'] < date('Y-m-d');
        if ($out['status'] === 'Booked' && $isPast && $out['reservation_date'] !== $originalDate) {
            throw new ReservationException('Choose today or a future date.');
        }
        if ($out['duration'] < 1 || $out['duration'] > 3) { throw new ReservationException('Duration must be 1 to 3 hours.'); }
        if ($hour < self::OPEN_HOUR || $hour + $out['duration'] > self::CLOSE_HOUR) {
            throw new ReservationException(sprintf('Courts are open %02d:00 to %02d:00.', self::OPEN_HOUR, self::CLOSE_HOUR));
        }
        if ($out['players_count'] < 1 || $out['players_count'] > 4) { throw new ReservationException('A court fits 1 to 4 players.'); }
        if (!in_array($out['status'], self::STATUSES, true)) { throw new ReservationException('Choose a valid status.'); }

        $out['start_time'] = sprintf('%02d:00:00', $hour);
        $out['end_time']   = sprintf('%02d:00:00', $hour + $out['duration']);
        return $out;
    }
}
