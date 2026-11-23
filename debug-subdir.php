<?php

require __DIR__.'/vendor/autoload.php';

// Broken Apache setup (rewrite into public/)
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/INHS SMART-DTR/login';
$_SERVER['SCRIPT_NAME'] = '/INHS SMART-DTR/public/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = '';
$_SERVER['QUERY_STRING'] = '';

require __DIR__.'/index.php';
