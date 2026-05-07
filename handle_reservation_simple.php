<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function safeInput(string $key): string {
    return trim(filter_input(INPUT_POST, $key, FILTER_SANITIZE_STRING) ?? '');
}

$firstName = safeInput('first_name');
$lastName = safeInput('last_name');
$email = trim($_POST['email'] ?? '');
$phone = safeInput('phone');
$checkIn = safeInput('check_in');
$checkOut = safeInput('check_out');
$propertyType = safeInput('property_type');
$guests = safeInput('number_of_guests');
$specialRequests = trim(filter_input(INPUT_POST, 'special_requests', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

$errors = [];

if ($firstName === '') {
    $errors[] = 'First name is required.';
}
if ($lastName === '') {
    $errors[] = 'Last name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if ($phone === '') {
    $errors[] = 'Phone number is required.';
}
if ($checkIn === '') {
    $errors[] = 'Check-in date is required.';
}
if ($checkOut === '') {
    $errors[] = 'Check-out date is required.';
}
if ($propertyType === '') {
    $errors[] = 'Room type is required.';
}
if ($guests === '' || !ctype_digit($guests) || (int)$guests < 1) {
    $errors[] = 'Number of guests must be at least 1.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$adminEmail = 'reservationssidaihomes@gmail.com';
$emailSubject = "New Reservation Request from {$firstName} {$lastName}";

$emailBody = "A new reservation request has been submitted.\n\n" .
    "First Name: {$firstName}\n" .
    "Last Name: {$lastName}\n" .
    "Email: {$email}\n" .
    "Phone: {$phone}\n" .
    "Check-in Date: {$checkIn}\n" .
    "Check-out Date: {$checkOut}\n" .
    "Room Type: {$propertyType}\n" .
    "Number of Guests: {$guests}\n" .
    "Special Requests: " . ($specialRequests ?: 'None') . "\n";

$headers = [];
$headers[] = 'From: "Sidai Homes Reservations" <' . $adminEmail . '>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = mail($adminEmail, $emailSubject, $emailBody, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send reservation notification at this time. Please try again later.']);
    exit;
}

$confirmationSubject = 'Your Sidai Homes Reservation Request';
$confirmationBody = "Dear {$firstName},\n\n" .
    "Thank you for submitting your reservation request with Sidai Furnished Homes. We have received your details and will review them shortly.\n\n" .
    "Reservation details:\n" .
    "Check-in: {$checkIn}\n" .
    "Check-out: {$checkOut}\n" .
    "Room Type: {$propertyType}\n" .
    "Number of Guests: {$guests}\n\n" .
    "If you have any questions, please reply to this message.\n\n" .
    "Best regards,\n" .
    "Sidai Furnished Homes Reservations Team\n";

$confirmationHeaders = [];
$confirmationHeaders[] = 'From: "Sidai Homes Reservations" <' . $adminEmail . '>';
$confirmationHeaders[] = 'Reply-To: ' . $adminEmail;
$confirmationHeaders[] = 'MIME-Version: 1.0';
$confirmationHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
mail($email, $confirmationSubject, $confirmationBody, implode("\r\n", $confirmationHeaders));

echo json_encode(['success' => true, 'message' => 'Your reservation request was submitted successfully. The admin has been notified.']);
