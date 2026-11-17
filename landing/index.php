<?php

/**
 * mensalc.uz: landing page. Old system links are sent to crm.mensalc.uz.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$appPrefixes = ['/login', '/home', '/dashboard', '/students', '/groups', '/lessons', '/schedule', '/teachers', '/cashboxes', '/reports', '/leads', '/admin', '/portal', '/settings', '/payments', '/tma', '/telegram', '/api', '/mensa.apk'];

foreach ($appPrefixes as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
        header('Location: https://crm.mensalc.uz'.($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

if ($path !== '/' && $path !== '/index.php') {
    http_response_code(404);
}

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__.'/index.html');
