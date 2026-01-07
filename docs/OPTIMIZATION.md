# Image Optimization Pipeline

This project implements a multi-tier image strategy to ensure high performance in the gallery (grid view) and high quality in the detail view, while preserving the original files.

## Architecture

1.  **Original**: Stored in `uploads/original/`. Used for:
    -   Backup/Archive.
    -   Full resolution download.
    -   Fallback if optimization fails.

2.  **Optimized**: Stored in `optimized/`.
    -   Max Dimensions: Configurable (default 1920px).
    -   Format: WEBP (preferred) or JPG.
    -   Quality: Configurable (default 82%).
    -   Used in: Detail view (`view.php`).

3.  **Thumbnails**: Stored in `uploads/thumbs/`.
    -   Max Dimensions: Configurable (default 480px).
    -   Format: WEBP (preferred) or JPG.
    -   Used in: Gallery Grid (`index.php`), Video lists.

## Configuration

Settings can be managed in the Admin Interface (`/admin/settings.php`) under "Optimizacija Slik".

-   **Thumbnail Velikost**: Max dimension for grid images.
-   **Optimized Velikost**: Max dimension for view images.
-   **Quality**: JPEG/WEBP compression levels.

## Processing Logic

The logic is handled by `App\Media::generateResized`.
-   **Library**: Uses `Imagick` if available (best quality/performance).
-   **Fallback**: Uses `GD` if Imagick is missing.
    -   Handles EXIF Rotation automatically.
    -   Checks `memory_limit` to prevent crashes with large files.
-   **Video**: Generates thumbnails using `ffmpeg` (if available), otherwise uses a placeholder. Video files are NOT transcoded (only thumbs).

## Backfill Script

If you have existing images uploaded before this pipeline was implemented (or if you change dimensions settings), you can regenerate thumbnails and optimized images using the CLI tool.

**Usage:**
```bash
docker exec -it web php tools/backfill_optimize.php
```

This script:
1.  Scans all posts in the database.
2.  Checks if `thumb_path` or `optimized_path` is missing or if the file is missing.
3.  Regenerates them using current Admin Settings.
4.  Updates the database.
