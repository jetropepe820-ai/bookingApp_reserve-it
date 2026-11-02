<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) return false;

$routes = [
    ['#^/api/v1/auth/login/?$#', 'api.v1.auth.login'],
    ['#^/api/v1/auth/register/?$#', 'api.v1.auth.register'],
    ['#^/api/v1/auth/logout/?$#', 'api.v1.auth.logout'],
    ['#^/api/v1/courts/?$#', 'api.v1.courts'],
    ['#^/api/v1/courts/(\d+)/availability/?$#', 'api.v1.court.availability', 'court_id'],
    ['#^/api/v1/bookings/?$#', 'api.v1.bookings'],
    ['#^/api/v1/bookings/(\d+)/cancel/?$#', 'api.v1.booking.cancel', 'booking_id'],
    ['#^/api/v1/bookings/(\d+)/?$#', 'api.v1.booking.item', 'booking_id'],
    ['#^/api/v1/admin/courts/?$#', 'api.v1.admin.courts'],
    ['#^/api/v1/admin/courts/(\d+)/deactivate/?$#', 'api.v1.admin.court.deactivate', 'court_id'],
    ['#^/api/v1/admin/courts/(\d+)/?$#', 'api.v1.admin.court.update', 'court_id'],
    ['#^/api/v1/admin/maintenance/?$#', 'api.v1.admin.maintenance.add'],
    ['#^/api/v1/admin/maintenance/(\d+)/?$#', 'api.v1.admin.maintenance.delete', 'block_id'],
];
foreach ($routes as $rule) {
    if (preg_match($rule[0], $path, $matches)) {
        $_GET['r'] = $rule[1];
        if (isset($rule[2])) $_GET[$rule[2]] = $matches[1];
        break;
    }
}
require __DIR__ . '/index.php';
