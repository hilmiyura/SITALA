<?php
// Router for PHP's built-in server, emulating the .htaccess rewrite rules:
// serve real files/dirs as-is, otherwise dispatch everything to index.php.
$path = urldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
$file = __DIR__ . $path;

if ($path !== '/' && (is_file($file) || is_link($file))) {
	return false;
}

require __DIR__ . '/index.php';
