<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** @var object $router */


// =====================================================
// BASIC ROUTES
// =====================================================

$router->get('/', function () {
    header('Location: /#/', true, 302);
    exit;
});

$router->get('/users', 'UserController::index');

$router->get(
    '/student/profile',
    'StudentController::profile'
)->middleware('StudentMiddleware');


// =====================================================
// AUTHENTICATION ROUTES
// =====================================================

// Normal LavaLust login page
// $router->match(
//     '/login',
//     'AuthController::login',
//     ['GET', 'POST']
// );

// React API login
$router->match(
    '/api/login',
    'AuthController::apiLogin',
    ['POST', 'OPTIONS']
);

// Register
$router->match(
    '/register',
    'AuthController::register',
    ['GET', 'POST']
);

// Logout
$router->get(
    '/logout',
    'AuthController::logout'
);


// =====================================================
// NORMAL PRODUCT ROUTES
// =====================================================

$router->get(
    '/products',
    'ProductController::index'
)->middleware('auth');

$router->get(
    '/api-products',
    'ProductController::apiProducts'
);

$router->match(
    '/products/create',
    'ProductController::create',
    ['GET', 'POST']
)->middleware('auth');

$router->match(
    '/products/edit/{id}',
    'ProductController::edit',
    ['GET', 'POST']
)->middleware('auth');

$router->get(
    '/products/delete/{id}',
    'ProductController::delete'
)->middleware('auth');


// =====================================================
// MIGRATION ROUTES
// =====================================================

$router->get(
    'create-migration/{migration_class}',
    'MigrationController::create_migration'
);

$router->get(
    'migrate',
    'MigrationController::migrate'
);

$router->get(
    'rollback',
    'MigrationController::rollback'
);

$router->get(
    'rollback-all',
    'MigrationController::rollback_all'
);

$router->get(
    'refresh',
    'MigrationController::refresh'
);

$router->get(
    'status',
    'MigrationController::status'
);


// =====================================================
// PRODUCT API ROUTES
// =====================================================

$router->match(
    '/api/products',
    'ProductController::apiIndex',
    ['GET', 'OPTIONS']
);

$router->match(
    '/api/products',
    'ProductController::apiCreate',
    ['POST', 'OPTIONS']
);

$router->match(
    '/api/products/{id}',
    'ProductController::apiUpdate',
    ['PUT', 'OPTIONS']
);

$router->match(
    '/api/products/{id}',
    'ProductController::apiUpdate',
    ['PATCH', 'OPTIONS']
);

$router->match(
    '/api/products/{id}',
    'ProductController::apiDelete',
    ['DELETE', 'OPTIONS']
);


// =====================================================
// CAREASE MODULE
// =====================================================

// Legacy entry point: show the Vue interface.
$router->get('/carease', function () {
    header('Location: /#/', true, 302);
    exit;
});

$router->match(
    '/carease/book/{id}',
    'CarEase::book',
    ['GET', 'POST']
);

$router->get(
    '/carease/my-bookings',
    'CarEase::mine'
);

$router->get(
    '/carease/account',
    'CarEase::account'
);

$router->match(
    '/carease/account/id',
    'CarEase::myId',
    ['GET', 'POST']
);

$router->get(
    '/carease/id/image/{id}',
    'CarEase::idImage'
);

$router->get(
    '/carease/availability/{id}',
    'CarEase::availability'
);

$router->match(
    '/carease/contract/{id}',
    'CarEase::contract',
    ['GET', 'POST']
);

$router->match(
    '/carease/notifications/run',
    'CarEase::notifications',
    ['GET', 'POST']
);


// =====================================================
// CAREASE ADMIN
// =====================================================

// Legacy admin URL: use the Vue admin dashboard.
$router->get('/carease/admin', function () {
    header('Location: /#/admin', true, 302);
    exit;
});

$router->match(
    '/carease/admin/id/{id}',
    'CarEase::verifyId',
    ['POST']
);

$router->match(
    '/carease/admin/car',
    'CarEase::saveCar',
    ['POST']
);

$router->match(
    '/carease/admin/car/delete/{id}',
    'CarEase::removeCar',
    ['POST']
);

$router->match(
    '/carease/admin/booking/{id}',
    'CarEase::status',
    ['POST']
);


// =====================================================
// CAREASE AUTHENTICATION
// =====================================================

// GET opens Vue login; keep legacy POST handler for compatibility.
$router->get('/carease/login', function () {
    header('Location: /#/login', true, 302);
    exit;
});
$router->match('/carease/login', 'CarEase::login', ['POST']);

// GET opens Vue registration; keep legacy POST handler for compatibility.
$router->get('/carease/register', function () {
    header('Location: /#/register', true, 302);
    exit;
});
$router->match('/carease/register', 'CarEase::register', ['POST']);

$router->match(
    '/carease/logout',
    'CarEase::logout',
    ['POST']
);


// =====================================================
// GOOGLE CALENDAR ROUTES
// =====================================================

// Calendar page
$router->get(
    '/carease/calendar',
    'CarEaseCalendar::index'
);

// Calendar connection status
$router->match(
    '/carease/calendar/status',
    'CarEaseCalendar::status',
    ['GET', 'OPTIONS']
);

// Calendar sync
$router->match(
    '/carease/calendar/sync',
    'CarEaseCalendar::sync',
    ['POST', 'OPTIONS']
);

// Sync all bookings
$router->match(
    '/carease/calendar/sync-all',
    'CarEaseCalendar::syncAll',
    ['POST', 'OPTIONS']
);

// Google OAuth page
$router->get(
    '/carease/calendar-oauth',
    'CarEaseCalendarOAuth::index'
);

// Google OAuth connect
$router->get(
    '/carease/calendar-oauth/connect',
    'CarEaseCalendarOAuth::connect'
);

// Additional Google OAuth connection route
$router->get(
    '/carease/calendar/oauth',
    'CarEaseCalendarOAuth::connect'
);

// OAuth callback routes
$router->get(
    '/carease/calendar/callback',
    'CarEaseCalendarOAuth::callback'
);

$router->get(
    '/carease/calendar-oauth/callback',
    'CarEaseCalendarOAuth::callback'
);

// Google OAuth sync
$router->match(
    '/carease/calendar-oauth/sync',
    'CarEaseCalendarOAuth::sync',
    ['POST']
);

// Google Calendar cleanup preview
$router->get(
    '/carease/calendar-oauth/cleanup-preview',
    'CarEaseCalendarOAuth::cleanupPreview'
);

// Delete old Google Calendar events
$router->match(
    '/carease/calendar/cleanup-delete',
    'CarEaseCalendar::cleanupDelete',
    ['POST', 'OPTIONS']
);

// =====================================================
// MAINTENANCE ROUTES
// =====================================================

$router->get(
    '/carease/admin/maintenance',
    'CarEaseMaintenance::index'
);

$router->match(
    '/carease/admin/maintenance/save',
    'CarEaseMaintenance::save',
    ['POST']
);

$router->match(
    '/carease/admin/maintenance/{id}/status',
    'CarEaseMaintenance::update',
    ['POST']
);


// =====================================================
// REPORT ROUTES
// =====================================================

$router->get(
    '/carease/admin/reports',
    'CarEaseReports::index'
);

$router->get(
    '/carease/admin/reports/annual-pdf',
    'CarEaseReports::annualPdf'
);

$router->get(
    '/carease/admin/reports/export',
    'CarEaseReports::export'
);


// =====================================================
// PAYMENT ROUTES
// =====================================================

$router->match(
    '/carease/payment/checkout/{id}',
    'CarEasePayment::checkout',
    ['POST']
);

$router->get(
    '/carease/payment/return/{id}',
    'CarEasePayment::result'
);


// =====================================================
// INSPECTION ROUTES
// =====================================================

$router->match(
    '/carease/admin/inspection/{id}',
    'CarEase::inspection',
    ['GET', 'POST']
);


// =====================================================
// CAREASE VUE API ROUTES
// =====================================================

// Vue session
$router->get(
    '/carease/api/session',
    'CarEaseApi::session'
);

// Vue login
$router->match(
    '/carease/api/login',
    'CarEaseApi::login',
    ['POST']
);

// Vue registration
$router->match(
    '/carease/api/register',
    'CarEaseApi::register',
    ['POST']
);

// EMAIL OTP VERIFICATION

// Verify email OTP
$router->match(
    '/carease/api/verify-email',
    'CarEaseApi::verifyEmail',
    ['POST']
);

// Resend email OTP
$router->match(
    '/carease/api/resend-otp',
    'CarEaseApi::resendOtp',
    ['POST']
);

// Vue logout
$router->match(
    '/carease/api/logout',
    'CarEaseApi::logout',
    ['POST']
);

// Vue client ID verification
$router->get('/carease/api/valid-id', 'CarEaseApi::validIdStatus');
$router->match('/carease/api/valid-id/submit', 'CarEaseApi::submitValidId', ['POST']);

// Available cars
$router->get(
    '/carease/api/cars',
    'CarEaseApi::cars'
);

// Customer bookings
$router->get(
    '/carease/api/my-bookings',
    'CarEaseApi::mine'
);


 // =====================================================
 // CAREASE STAFF MANAGEMENT API
 // =====================================================

 // Admin: View all staff accounts
 $router->get(
     '/carease/api/admin/staff',
     'CarEaseApi::staffAccounts'
 );

 // Admin: Create a new staff account
 $router->match(
     '/carease/api/admin/staff/create',
     'CarEaseApi::createStaff',
     ['POST']
 );


// Vue admin Valid ID management
$router->get('/carease/api/admin/valid-ids', 'CarEaseApi::adminValidIds');
$router->match('/carease/api/admin/valid-ids/{id}/verify', 'CarEaseApi::adminVerifyId', ['POST']);

// Admin bookings
$router->get(
    '/carease/api/admin/bookings',
    'CarEaseApi::adminBookings'
);

// Vehicle inspections (authenticated admin/staff)
$router->get('/carease/api/admin/booking/{id}/inspections', 'CarEaseApi::bookingInspections');
$router->match('/carease/api/admin/booking/{id}/inspections', 'CarEaseApi::saveBookingInspection', ['POST']);

// Admin booking status update
$router->match(
    '/carease/api/admin/booking/{id}/status',
    'CarEaseApi::updateBookingStatus',
    ['POST']
);

// Create booking
$router->match(
    '/carease/api/bookings',
    'CarEaseApi::createBooking',
    ['POST']
);


// =====================================================
// CAREASE VUE RENTAL CONTRACT API
// =====================================================

// View rental contract
$router->get(
    '/carease/api/contracts/{id}',
    'CarEaseApi::getContract'
);

// Customer signs rental contract
$router->match(
    '/carease/api/contracts/{id}/sign',
    'CarEaseApi::signContract',
    ['POST']
);


// =====================================================
// CAREASE VUE EMAIL NOTIFICATIONS API
// =====================================================

// Admin runs return reminders and overdue notices
$router->match(
    '/carease/api/admin/notifications/run',
    'CarEaseApi::runNotifications',
    ['POST']
);


$router->match(
    '/carease/api/payments/{id}/checkout',
    'CarEaseApi::paymentCheckout',
    ['POST']
);

$router->get(
    '/carease/api/payments/{id}/status',
    'CarEaseApi::paymentStatus'
);



$router->get(
    '/carease/api/cars/{id}/reserved-dates',
    'CarEaseAvailability::reservedDates'
);


 // =====================================================
 // CAREASE VUE RETURN VEHICLE API ROUTES
 // =====================================================

 // Client: Request to return a vehicle
 $router->match(
     '/carease/api/returns/{id}/request',
     'CarEaseApi::requestReturn',
     ['POST']
 );

 // Admin: View all return requests
 $router->get(
     '/carease/api/admin/returns',
     'CarEaseApi::adminReturnRequests'
 );

 // Admin: Accept vehicle return
 $router->match(
     '/carease/api/admin/returns/{id}/accept',
     'CarEaseApi::acceptReturn',
     ['POST']
 );


// =====================================================
// CAREASE GOOGLE MAPS API
// =====================================================

// Browser key + map configuration
$router->get(
    '/carease/api/maps/config',
    'CarEaseMaps::config'
);

// Address search suggestions (Places API - New)
$router->get(
    '/carease/api/maps/autocomplete',
    'CarEaseMaps::autocomplete'
);

// Resolve a place id to address + coordinates (Places API - New)
$router->get(
    '/carease/api/maps/place',
    'CarEaseMaps::place'
);

// Directions, distance and travel time (Routes API)
$router->match(
    '/carease/api/maps/directions',
    'CarEaseMaps::directions',
    ['POST']
);



?>