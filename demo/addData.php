<?php
require __DIR__ . '/bootstrap.php';
include __DIR__."/../vendor/autoload.php";
include __DIR__."/WordStringGenerator.php";

// start/access the session
session_start();

// get the tab manager instance
$tabManager = new Seba1rx\TabManager\TabManager();

// use a word generator to create a random string
$generator = new WordStringGenerator();
$value = $generator->generate(3);

// create keys using time
$key = time();

// Set tab-specific session data
$tabManager->set($key, $value);

// return the new item
header('Content-Type: application/json; charset=utf-8');
echo json_encode($_SESSION);