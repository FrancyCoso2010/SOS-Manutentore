<?php

declare(strict_types=1);

/**
 * Registro delle attività: chi ha fatto cosa e quando.
 */
final class Activity
{
    public static function log(string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void
    {
        $user = Auth::user();

        Database::pdo()
            ->prepare(
                'INSERT INTO activity_log (user_id, username, action, entity_type, entity_id, details, created_at)
                 VALUES (:user_id, :username, :action, :entity_type, :entity_id, :details, :created_at)'
            )
            ->execute([
                'user_id'     => $user !== null ? (int) $user['id'] : null,
                'username'    => $user !== null ? (string) $user['username'] : '',
                'action'      => text_cut($action, 60),
                'entity_type' => text_cut($entityType, 40),
                'entity_id'   => $entityId,
                'details'     => text_cut($details, 2000),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 300, ?int $userId = null, string $action = ''): array
    {
        $where = [];
        $params = [];

        if ($userId !== null && $userId > 0) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($action !== '') {
            $where[] = 'action LIKE :action';
            $params['action'] = '%' . $action . '%';
        }

        $sql = 'SELECT * FROM activity_log';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit));

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}
