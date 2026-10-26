<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

function postValue(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function cleanText(string $value): string
{
    $value = strip_tags($value);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
}

function parseDate(string $value): ?DateTimeImmutable
{
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date->format('Y-m-d') === $value ? $date : null;
}

$firstName = cleanText(postValue('first_name'));
$lastName = cleanText(postValue('last_name'));
$email = postValue('email');
$phone = cleanText(postValue('phone'));
$requestType = postValue('request_type');
$checkIn = postValue('check_in');
$checkOut = postValue('check_out');
$propertyType = postValue('property_type');
$guests = postValue('number_of_guests');
$specialRequests = cleanText(postValue('special_requests'));

$requestTypes = [
    'stay' => 'Apartment stay',
    'food' => 'Food options',
    'airport-pickup' => 'Airport pickup',
    'general' => 'General enquiry',
];
$allowedPropertyTypes = ['Studio', '1 Bedroom', 'Not sure yet'];
$errors = [];

if ($firstName === '' || strlen($firstName) > 80) {
    $errors[] = 'Please enter a valid first name.';
}
if ($lastName === '' || strlen($lastName) > 80) {
    $errors[] = 'Please enter a valid last name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = 'Please enter a valid email address.';
}
if ($phone === '' || strlen($phone) > 40) {
    $errors[] = 'Please enter a valid phone number.';
}
if (!isset($requestTypes[$requestType])) {
    $errors[] = 'Please select what you are enquiring about.';
}
if ($specialRequests !== '' && strlen($specialRequests) > 3000) {
    $errors[] = 'Please keep additional details under 3,000 characters.';
}

$checkInDate = $checkIn === '' ? null : parseDate($checkIn);
$checkOutDate = $checkOut === '' ? null : parseDate($checkOut);
if (($checkIn !== '' && $checkInDate === null) || ($checkOut !== '' && $checkOutDate === null)) {
    $errors[] = 'Please enter valid check-in and check-out dates.';
}
if ($requestType === 'stay') {
    if ($checkInDate === null || $checkOutDate === null) {
        $errors[] = 'Check-in and check-out dates are required for an apartment enquiry.';
    } else {
        if ($checkInDate < new DateTimeImmutable('today')) {
            $errors[] = 'Check-in cannot be in the past.';
        }
        if ($checkOutDate <= $checkInDate) {
            $errors[] = 'Check-out must be after check-in.';
        }
    }
    if (!in_array($propertyType, $allowedPropertyTypes, true)) {
        $errors[] = 'Please select an apartment type.';
    }
    if ($guests === '' || !ctype_digit($guests) || (int)$guests < 1) {
        $errors[] = 'Please enter at least one guest.';
    }
} elseif ($propertyType !== '' && !in_array($propertyType, $allowedPropertyTypes, true)) {
    $errors[] = 'Please select a valid apartment type.';
}
if ($guests !== '' && (!ctype_digit($guests) || (int)$guests < 1)) {
    $errors[] = 'Please enter a valid number of guests.';
}

if ($errors !== []) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$adminEmail = 'reservationssidaihomes@gmail.com';
$emailSubject = 'New Sidai Homes enquiry';
$emailBody = implode("\n", [
    'A new enquiry has been submitted.',
    '',
    'Enquiry type: ' . $requestTypes[$requestType],
    'First name: ' . $firstName,
    'Last name: ' . $lastName,
    'Email: ' . $email,
    'Phone: ' . $phone,
    'Check-in: ' . ($checkIn ?: 'Not provided'),
    'Check-out: ' . ($checkOut ?: 'Not provided'),
    'Apartment type: ' . ($propertyType ?: 'Not provided'),
    'Number of guests: ' . ($guests ?: 'Not provided'),
    'Additional details: ' . ($specialRequests ?: 'None'),
]);

$headers = [
    'From: "Sidai Homes Enquiries" <' . $adminEmail . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];

if (!mail($adminEmail, $emailSubject, $emailBody, implode("\r\n", $headers))) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send your enquiry right now. Please try again later.']);
    exit;
}

$confirmationBody = "Hello {$firstName},\n\n" .
    "Thank you for contacting Sidai Furnished Homes. We have received your enquiry and will be in touch to discuss availability and next steps.\n\n" .
    "Please note that this enquiry is not a confirmed reservation.\n\n" .
    "Sidai Furnished Homes";
$confirmationHeaders = [
    'From: "Sidai Homes Enquiries" <' . $adminEmail . '>',
    'Reply-To: ' . $adminEmail,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
];
mail($email, 'We received your Sidai Homes enquiry', $confirmationBody, implode("\r\n", $confirmationHeaders));

echo json_encode(['success' => true, 'message' => 'Your enquiry was sent successfully.']);
