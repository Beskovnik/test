## 2024-05-23 - Database Query in Loop (N+1)
**Learning:** `index.php` executes a correlated subquery for each post to count likes: `(SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count`. While not technically a loop in PHP, it forces the database to execute a subquery for every row in the result set. For large datasets, this is an N+1 equivalent in SQL performance.
**Action:** Replace the correlated subquery with a `LEFT JOIN` and `GROUP BY`, or cache the like count in the `posts` table (denormalization) if read-heavy.

## 2024-05-23 - Unoptimized Image Thumbnails
**Learning:** `thumb.php` is called for every image. While `loading="lazy"` helps, `thumb.php` likely generates thumbnails on the fly if they don't exist.
**Action:** Check `thumb.php` implementation.

## 2024-05-24 - Missing Database Indexes on Foreign Keys
**Learning:** The `likes` and `comments` tables lacked indexes on the `post_id` column. This caused the correlated subqueries in `index.php` (used to count likes/comments per post) to perform full table scans for *every* post in the feed. This turned an O(N) operation into O(N*M) where M is the total number of likes/comments.
**Action:** Added `M006_AddCountIndexes.php` to create `idx_likes_post_id` and `idx_comments_post_id`. Benchmark showed query time dropping from ~16s to ~0.04s for 100 iterations (99.7% improvement).
