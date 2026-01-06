## 2024-05-20 - Stored XSS in Comments
**Vulnerability:** Found a potential Stored XSS in comments where usernames were not strictly validated during registration, allowing special characters. Although `api/comment_list.php` sanitized the comment body, it relied on database persistence for the author name.
**Learning:** Even if one output point is sanitized (comment body), other related data (username) might not be, especially if validation at the source (registration) is lax.
**Prevention:** Always validate input strictly at the point of entry (registration) and sanitize ALL outputs at the point of display (comment author).
