# Debugging Information

## Thumbnail Generation Test
To test the thumbnail generation endpoint, you can use the following URL pattern:
`http://<host>/thumb.php?src=/uploads/<filename>&w=300&h=300&fit=cover`

Example:
`/thumb.php?src=/uploads/example.jpg&w=420&h=420&fit=cover`

## Requirements
The system requires `php-gd` extension for thumbnail generation.
If running in Docker, ensure the Dockerfile includes:
`RUN docker-php-ext-install gd`
or equivalent package installation.

## Permissions
Ensure `/thumbs` directory is writable by the web server user (www-data).
