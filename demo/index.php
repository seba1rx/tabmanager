<?php

include __DIR__."/../vendor/autoload.php";
include __DIR__."/WordStringGenerator.php";

// start/access the session
session_start();

// get the tab manager instance
$tabManager = new Seba1rx\TabManager\TabManager();

// use a word generator to create a random string
$generator = new WordStringGenerator();
$rand = $generator->generate(3);

// create keys using time
$key = time();

// Set tab-specific session data
$tabManager->set($key, $rand);

// get the session data in a readable way
$pretty_sessison_data = json_encode($_SESSION, JSON_PRETTY_PRINT);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TabManager Demo</title>
    <script src="seba1rx_tabmanagerclient.js"></script>
    <script>
        // Optional: enable automatic tab cleanup
        window.TABMANAGER_AUTO_DESTROY = true;
        window.TABMANAGER_DEBUG = true;
        window.TABMANAGER_DEBUG_UI = true;
    </script>
</head>
<body style="padding: 20px">
    <p>Each time you reload this page a random string will be added to the session tab data</p>
    <br>
    <pre>
        <?= $pretty_sessison_data; ?>
    </pre>
</body>
</html>