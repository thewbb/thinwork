<?php
ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);

require "../vendor/autoload.php";


include __DIR__.'/../app/config/Di.php';

$app = new \thewbb\thinwork\Application($di);
$app->run();