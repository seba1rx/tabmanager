<?php

include __DIR__."/../vendor/autoload.php";

// start/access the session
session_start();

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
    <script src="app.js"></script>
    <script>
        // Optional: enable automatic tab cleanup
        window.TABMANAGER_AUTO_DESTROY = true;
        window.TABMANAGER_DEBUG = true;
        window.TABMANAGER_DEBUG_UI = true;
    </script>
</head>
<body style="padding: 20px">
    <pre id="session_data">
        <?= $pretty_sessison_data; ?>
    </pre>
    <br>
    <br>
    <p>click on the button to add random data to the tab data</p>
    <br>
    <button type="button" onclick="addData()">Add data</button>
    <button type="button" onclick="reset()">Reset session</button>
</body>
</html>