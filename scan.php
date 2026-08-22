<?php

require_once "backend/db.php";


// Check whether token exists
if (!isset($_GET["token"]) || empty($_GET["token"])) {
    die("Invalid QR code.");
}

$token = $_GET["token"];


/*
|--------------------------------------------------------------------------
| Find QR token
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            token_id,
            gym_id,
            expires_at
        FROM attendance_qr_tokens
        WHERE token = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $token
);

$stmt->execute();

$result = $stmt->get_result();

$qr = $result->fetch_assoc();


if (!$qr) {
    die("Invalid QR code.");
}


/*
|--------------------------------------------------------------------------
| Check expiration
|--------------------------------------------------------------------------
*/

if (strtotime($qr["expires_at"]) < time()) {

    die("
        <h2>QR Code Expired</h2>
        <p>Please scan the new QR code displayed at the gym.</p>
    ");

}


$gym_id = $qr["gym_id"];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mark Attendance</title>

</head>

<body>

<h1>
    Gym Attendance
</h1>

<p>
    Enter your registered phone number
    to mark your attendance.
</p>


<form
    action="backend/mark_attendance.php"
    method="POST"
>

    <input
        type="hidden"
        name="token"
        value="<?php echo htmlspecialchars($token); ?>"
    >


    <label>
        Phone Number:
    </label>

    <br>

    <input
        type="text"
        name="phone"
        placeholder="03001234567"
        required
    >

    <br><br>


    <button type="submit">
        Mark My Attendance
    </button>

</form>

</body>

</html>