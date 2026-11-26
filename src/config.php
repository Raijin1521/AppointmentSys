<?php
declare(strict_types=1);

// Match this timezone to the barbershop's local timezone.
const APP_TIMEZONE = 'UTC';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'barber_shop';
const DB_USER = 'root';
const DB_PASS = '';

date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_name('cornerchair_session');
	session_set_cookie_params([
		'httponly' => true,
		'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
		'samesite' => 'Lax',
	]);
	session_start();
}

function db(): PDO
{
	static $connection = null;

	if ($connection instanceof PDO) {
		return $connection;
	}

	$connection = new PDO(
		'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
		DB_USER,
		DB_PASS,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]
	);

	return $connection;
}
