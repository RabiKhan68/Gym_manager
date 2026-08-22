<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../make_payment.php");
    exit();
}

$owner_id = $_SESSION["owner_id"];

$membership_id = intval($_POST["membership_id"]);
$payment_month = $_POST["payment_month"];
$payment_method = $_POST["payment_method"];


/*
    Convert YYYY-MM into YYYY-MM-01
*/

$payment_for_month = $payment_month . "-01";


/*
    Make sure the membership belongs
    to the logged-in owner's gym.
*/

$sql = "SELECT
            mm.membership_id,
            mm.member_id,
            mp.price

        FROM member_memberships mm

        JOIN members m
            ON mm.member_id = m.member_id

        JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE mm.membership_id = ?
        AND g.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $membership_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$membership = $result->fetch_assoc();

if (!$membership) {

    die("Invalid membership.");

}


$member_id = $membership["member_id"];
$amount = $membership["price"];


/*
    Check whether this month
    has already been paid.
*/

$sql = "SELECT payment_id

        FROM payments

        WHERE membership_id = ?

        AND payment_for_month = ?

        AND payment_status = 'paid'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $membership_id,
    $payment_for_month
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    die("This month has already been paid.");

}


/*
    Insert payment.
*/

$payment_status = "paid";

$transaction_reference = null;

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

if ($stmt->execute()) {

    header("Location: ../payments.php");
    exit();

} else {

    echo "Error: " . $stmt->error;

}

$stmt->close();
$conn->close();

?>