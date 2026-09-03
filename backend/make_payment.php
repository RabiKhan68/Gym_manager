<?php

session_start();

require_once "db.php";
require_once "email.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../make_payment.php");
    exit();
}


$owner_id = intval($_SESSION["owner_id"]);


/*
|--------------------------------------------------------------------------
| Get submitted values safely
|--------------------------------------------------------------------------
*/

$membership_id = isset($_POST["membership_id"])
    ? intval($_POST["membership_id"])
    : 0;

$payment_month = isset($_POST["payment_month"])
    ? trim($_POST["payment_month"])
    : "";

$payment_method = isset($_POST["payment_method"])
    ? trim($_POST["payment_method"])
    : "";


/*
|--------------------------------------------------------------------------
| Validate membership ID
|--------------------------------------------------------------------------
*/

if ($membership_id <= 0) {
    die("Invalid membership.");
}


/*
|--------------------------------------------------------------------------
| Validate payment month
|--------------------------------------------------------------------------
|
| Expected format:
| YYYY-MM
|
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{4}-\d{2}$/', $payment_month)) {
    die("Invalid payment month.");
}


/*
|--------------------------------------------------------------------------
| Validate payment method
|--------------------------------------------------------------------------
*/

$allowed_methods = ["cash", "online"];

if (!in_array($payment_method, $allowed_methods, true)) {
    die("Invalid payment method.");
}


/*
|--------------------------------------------------------------------------
| Convert YYYY-MM into YYYY-MM-01
|--------------------------------------------------------------------------
*/

$payment_for_month = $payment_month . "-01";


/*
|--------------------------------------------------------------------------
| Get membership information
|--------------------------------------------------------------------------
|
| We also retrieve the member's name and email because the
| payment confirmation email will be sent after successful payment.
|
| The JOIN with gyms ensures the membership belongs to the
| logged-in owner's gym.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            mm.membership_id,
            mm.member_id,

            m.name AS member_name,
            m.email AS member_email,

            mp.price

        FROM member_memberships mm

        INNER JOIN members m
            ON mm.member_id = m.member_id

        INNER JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE mm.membership_id = ?
        AND g.owner_id = ?";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}


$stmt->bind_param(
    "ii",
    $membership_id,
    $owner_id
);


$stmt->execute();

$result = $stmt->get_result();

$membership = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Make sure membership exists
|--------------------------------------------------------------------------
*/

if (!$membership) {
    die("Invalid membership.");
}


$member_id = intval($membership["member_id"]);
$amount = floatval($membership["price"]);

$member_name = $membership["member_name"];
$member_email = $membership["member_email"];


/*
|--------------------------------------------------------------------------
| Check whether this month has already been paid
|--------------------------------------------------------------------------
*/

$sql = "SELECT payment_id

        FROM payments

        WHERE membership_id = ?

        AND payment_for_month = ?

        AND payment_status = 'paid'

        LIMIT 1";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}


$stmt->bind_param(
    "is",
    $membership_id,
    $payment_for_month
);


$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    $stmt->close();

    die("This month has already been paid.");

}


$stmt->close();


/*
|--------------------------------------------------------------------------
| Payment information
|--------------------------------------------------------------------------
*/

$payment_status = "paid";

$transaction_reference = null;


/*
|--------------------------------------------------------------------------
| Insert payment
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO payments
        (
            member_id,
            membership_id,
            amount,
            payment_for_month,
            payment_method,
            payment_status,
            transaction_reference
        )

        VALUES (?, ?, ?, ?, ?, ?, ?)";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}


$stmt->bind_param(
    "iidssss",
    $member_id,
    $membership_id,
    $amount,
    $payment_for_month,
    $payment_method,
    $payment_status,
    $transaction_reference
);


/*
|--------------------------------------------------------------------------
| Save payment
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    echo "Error recording payment: " . htmlspecialchars($stmt->error);

    $stmt->close();
    $conn->close();

    exit();

}


/*
|--------------------------------------------------------------------------
| Payment successfully saved
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| The payment is already saved in the database.
|
| If Brevo fails, we DO NOT cancel or delete the payment.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Send payment confirmation email
|--------------------------------------------------------------------------
|
| Only send the email if the member has an email address.
|
|--------------------------------------------------------------------------
*/

if (!empty($member_email)) {

    $subject = "Payment Confirmation - Gym Management System";


    $safe_name = htmlspecialchars(
        $member_name,
        ENT_QUOTES,
        "UTF-8"
    );


    $safe_month = htmlspecialchars(
        $payment_month,
        ENT_QUOTES,
        "UTF-8"
    );


    $safe_method = htmlspecialchars(
        ucfirst($payment_method),
        ENT_QUOTES,
        "UTF-8"
    );


    $htmlContent = "

        <!DOCTYPE html>

        <html>

        <head>

            <meta charset='UTF-8'>

            <title>Payment Confirmation</title>

        </head>

        <body>

            <h2>Payment Received</h2>

            <p>
                Dear {$safe_name},
            </p>

            <p>
                Your membership payment has been
                successfully recorded.
            </p>

            <p>
                <strong>Amount:</strong>
                Rs. " . number_format($amount, 2) . "
            </p>

            <p>
                <strong>Payment For:</strong>
                {$safe_month}
            </p>

            <p>
                <strong>Payment Method:</strong>
                {$safe_method}
            </p>

            <p>
                Thank you for your payment.
            </p>

            <br>

            <p>
                Regards,<br>
                Gym Management System
            </p>

        </body>

        </html>

    ";


    /*
    |--------------------------------------------------------------------------
    | Send email
    |--------------------------------------------------------------------------
    */

    $email_sent = sendEmail(
        $member_email,
        $member_name,
        $subject,
        $htmlContent
    );


    /*
    |--------------------------------------------------------------------------
    | Log email failure
    |--------------------------------------------------------------------------
    |
    | Payment remains successful even if email fails.
    |
    |--------------------------------------------------------------------------
    */

    if (!$email_sent) {

        error_log(
            "Payment confirmation email failed for member ID: "
            . $member_id
        );

    }

}


/*
|--------------------------------------------------------------------------
| Finish
|--------------------------------------------------------------------------
*/

$stmt->close();

$conn->close();


header("Location: ../payments.php");
exit();

?>