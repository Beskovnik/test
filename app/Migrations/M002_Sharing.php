<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M002_Sharing
{
    public function up(PDO $pdo): void
    {
        // 1. Add owner_user_id to posts and backfill
        // Check if column exists first (idempotency)
        $columns = $pdo->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC);
        $hasOwner = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'owner_user_id') {
                $hasOwner = true;
                break;
            }
        }

        if (!$hasOwner) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN owner_user_id INTEGER');
            $pdo->exec('UPDATE posts SET owner_user_id = user_id');
            // We can't easily add NOT NULL constraint to existing column in SQLite without recreating table,
            // but we can enforce it in application logic.
        }

        // 1.5 Ensure visibility column exists (M001 has it, but just in case of different schema versions)
        $hasVisibility = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'visibility') {
                $hasVisibility = true;
                break;
            }
        }
        if (!$hasVisibility) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN visibility TEXT DEFAULT "private"');
        }

        // 2. Create shares table
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shares (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER NOT NULL,
                token TEXT UNIQUE NOT NULL,
                title TEXT,
                created_at INTEGER NOT NULL, -- Storing as timestamp for consistency
                expires_at INTEGER NULL,
                is_active INTEGER DEFAULT 1,
                views_total INTEGER DEFAULT 0,
                downloads_total INTEGER DEFAULT 0,
                last_view_at INTEGER NULL,
                last_download_at INTEGER NULL,
                FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        // 3. Create share_items table
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS share_items (
                share_id INTEGER NOT NULL,
                media_id INTEGER NOT NULL,
                PRIMARY KEY (share_id, media_id),
                FOREIGN KEY(share_id) REFERENCES shares(id) ON DELETE CASCADE,
                FOREIGN KEY(media_id) REFERENCES posts(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_share_items_share ON share_items(share_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_share_items_media ON share_items(media_id)');

        // 4. Create share_events table (Analytics)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS share_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                share_id INTEGER NOT NULL,
                media_id INTEGER NULL,
                event_type TEXT NOT NULL, -- view, open_item, download
                occurred_at INTEGER NOT NULL,
                ip_hash TEXT NULL,
                ua_hash TEXT NULL,
                referrer TEXT NULL,
                country_code TEXT NULL,
                session_id TEXT NULL,
                FOREIGN KEY(share_id) REFERENCES shares(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_share_events_share_time ON share_events(share_id, occurred_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_share_events_share_type ON share_events(share_id, event_type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_share_events_share_media ON share_events(share_id, media_id)');
    }
}
