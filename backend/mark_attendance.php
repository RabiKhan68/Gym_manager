<?php

date_default_timezone_set("Asia/Karachi");

require_once "db.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}


$token = trim($_POST["token"]);
$phone = trim($_POST["phone"]);


/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

if (empty($token) || empty($phone)) {

    die("Phone number and QR token are required.");

}


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
| Check QR expiration
|--------------------------------------------------------------------------
*/

if (strtotime($qr["expires_at"]) < time()) {

    die("
        <h2>QR Code Expired</h2>

        <p>
            Please scan the latest QR code
            displayed at the gym.
        </p>
    ");

}


$gym_id = $qr["gym_id"];


/*
|--------------------------------------------------------------------------
| Find member
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            member_id,
            name,
            phone
        FROM members
        WHERE gym_id = ?
        AND phone = ?
        AND status = 'active'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $gym_id,
    $phone
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {

    die("
        <h2>Member Not Found</h2>

        <p>
            This phone number is not registered
            with this gym.
        </p>
    ");

}


$member_id = $member["member_id"];


/*
|--------------------------------------------------------------------------
| Check today's attendance
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");


$sql = "SELECT attendance_id
        FROM attendance
        WHERE member_id = ?
        AND attendance_date = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $member_id,
    $today
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    die("
        <h2>Already Marked</h2>

        <p>
            Hello " .
            htmlspecialchars($member["name"]) .
            "!
        </p>

        <p>
            Your attendance has already
            been marked today.
        </p>
    ");

}


/*
|--------------------------------------------------------------------------
| Record attendance
|--------------------------------------------------------------------------
*/

$current_time = date("H:i:s");


$sql = "INSERT INTO attendance
        (
            member_id,
            attendance_date,
            attendance_time
        )

        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $member_id,
    $today,
    $current_time
);


if ($stmt->execute()) {

    ?>

    <!DOCTYPE html>

    <html>

    <head>

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Attendance Marked</title>

    </head>

    <body>

        <h1>
            ✅ Attendance Marked
        </h1>

        <h2>
            Welcome,
            <?php
            echo htmlspecialchars(
                $member["name"]
            );
            ?>!
        </h2>

        <p>
            Date:
            <?php echo $today; ?>
        </p>

        <p>
            Time:
            <?php echo $current_time; ?>
        </p>

        <p>
            Your attendance has been
            successfully recorded.
        </p>

    </body>

    </html>

    <?php

} else {

    echo "Error recording attendance: "
         . $stmt->error;

}


$stmt->close();

$conn->close();

?>