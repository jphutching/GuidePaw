<?php

/**
 * Database-backed PHP session handler.
 * Required on Render: the container filesystem is ephemeral — /tmp is wiped on
 * every restart, which kills PHP sessions. Storing sessions in PostgreSQL means
 * they survive container restarts on Render's free tier.
 *
 * Call gpRegisterDbSessionHandler($pdo) BEFORE session_start().
 */

function gpRegisterDbSessionHandler(PDO $pdo): void
{
    gpEnsureSessionTable($pdo);

    $handler = new class($pdo) implements SessionHandlerInterface {
        public function __construct(private readonly PDO $pdo) {}

        public function open(string $savePath, string $sessionName): bool { return true; }
        public function close(): bool { return true; }

        public function read(string $id): string|false
        {
            $stmt = $this->pdo->prepare(
                'SELECT session_data FROM php_sessions WHERE session_id = ? AND last_activity > ?'
            );
            $stmt->execute([$id, time() - (int) ini_get('session.gc_maxlifetime')]);
            return $stmt->fetchColumn() ?: '';
        }

        public function write(string $id, string $data): bool
        {
            $this->pdo->prepare(
                'INSERT INTO php_sessions (session_id, session_data, last_activity)
                 VALUES (?, ?, ?)
                 ON CONFLICT (session_id) DO UPDATE
                   SET session_data = EXCLUDED.session_data,
                       last_activity = EXCLUDED.last_activity'
            )->execute([$id, $data, time()]);
            return true;
        }

        public function destroy(string $id): bool
        {
            $this->pdo->prepare('DELETE FROM php_sessions WHERE session_id = ?')->execute([$id]);
            return true;
        }

        public function gc(int $maxlifetime): int|false
        {
            $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE last_activity < ?');
            $stmt->execute([time() - $maxlifetime]);
            return $stmt->rowCount();
        }
    };

    session_set_save_handler($handler, true);
}

function gpEnsureSessionTable(PDO $pdo): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS php_sessions (
            session_id   VARCHAR(128) PRIMARY KEY,
            session_data TEXT        NOT NULL DEFAULT \'\',
            last_activity BIGINT     NOT NULL
        )'
    );
}
