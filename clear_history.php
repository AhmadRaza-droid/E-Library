<?php
require_once __DIR__ . '/session_init.php';
include 'config.php';

require_post();
require_login();

db_exec($conn, "DELETE FROM recently_viewed WHERE user_id=?", 'i', [(int)$_SESSION['user_id']]);
redirect('profile.php');
