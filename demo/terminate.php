<?php
/** destroy session */
session_start();
$_SESSION = [];
session_destroy();

/** go to main page */
header('Location: index.php');