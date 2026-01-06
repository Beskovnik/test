<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M001_InitialSchema
{
    public function up(PDO $pdo): void
    {
        // Users
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE,
                pass_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "user",
                active INTEGER NOT NULL DEFAULT 1,
                created_at INTEGER NOT NULL
            )'
        );

        // Posts
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL, -- Made nullable to match old bootstrap
                title TEXT,
                description TEXT,
                created_at INTEGER NOT NULL,
                visibility TEXT DEFAULT "public",
                share_token TEXT,
                type TEXT NOT NULL,
                file_path TEXT NOT NULL,
                thumb_path TEXT,
                preview_path TEXT,
                mime TEXT,
                size_bytes INTEGER,
                width INTEGER,
                height INTEGER,
                views INTEGER DEFAULT 0,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
            )'
        );

        // Comments
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                author_name TEXT NULL, -- Added to match old bootstrap
                body TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT "visible",
                FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
            )'
        );

        // Likes
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS likes (
                id INTEGER PRIMARY KEY AUTOINCREMENT, -- Added ID for consistency
                user_id INTEGER NOT NULL,
                post_id INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                UNIQUE(user_id, post_id),
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
            )'
        );

        // Settings
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )'
        );

        // Audit Log
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_user_id INTEGER,
                action TEXT,
                meta TEXT,
                created_at INTEGER
            )'
        );
    }
}
