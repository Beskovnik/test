<?php
require __DIR__ . '/app/Bootstrap.php';
session_destroy();
header('Location: /login.php');
