## 2024-05-23 - Database Query in Loop (N+1)
**Learning:** `index.php` executes a correlated subquery for each post to count likes: `(SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count`. While not technically a loop in PHP, it forces the database to execute a subquery for every row in the result set. For large datasets, this is an N+1 equivalent in SQL performance.
**Action:** Replace the correlated subquery with a `LEFT JOIN` and `GROUP BY`, or cache the like count in the `posts` table (denormalization) if read-heavy.

## 2024-05-23 - Unoptimized Image Thumbnails
**Learning:** `thumb.php` is called for every image. While `loading="lazy"` helps, `thumb.php` likely generates thumbnails on the fly if they don't exist.
**Action:** Check `thumb.php` implementation.
