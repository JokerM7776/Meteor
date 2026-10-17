<?php
declare(strict_types=1);
return static function (\Core\Database $db): void {
    foreach ($db->all('SELECT id, username FROM users') as $user) {
        $db->query('UPDATE users SET username = ? WHERE id = ?', [mb_strtolower($user['username']), $user['id']]);
    }
};
