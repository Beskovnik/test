<?php
require __DIR__ . '/app/Bootstrap.php';
// We should regenerate ID to avoid session fixation, then destroy
session_regenerate_id(true);
session_destroy();
header('Location: /login.php');
exit;
