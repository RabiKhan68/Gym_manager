<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");
    exit();

}


$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Only allow POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../assign_membership.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get submitted data
|--------------------------------------------------------------------------
*/

$member_id = isset($_POST["member_id"])
    ? (int) $_POST["member_id"]
    : 0;

$plan_id = isset($_POST["plan_id"])
    ? (int) $_POST["plan_id"]
    : 0;

$start_date = $_POST["start_date"] ?? "";


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $member_id <= 0 ||
    $plan_id <= 0 ||
    empty($start_date)
) {

    die("Please provide all required information.");

}


/*
|--------------------------------------------------------------------------
| Validate date format
|--------------------------------------------------------------------------
*/

$date = DateTime::createFromFormat(
    "Y-m-d",
    $start_date
);


if (
    !$date ||
    $date->format("Y-m-d") !== $start_date
) {

    die("Invalid start date.");

}


/*
|--------------------------------------------------------------------------
| Verify member belongs to logged-in owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.member_id,
            m.gym_id
        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE m.member_id = ?
        AND g.owner_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {

    die("Invalid member.");

}


$gym_id = $member["gym_id"];


/*
|--------------------------------------------------------------------------
| Verify plan belongs to same gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            plan_id,
            plan_name,
            price,
            duration_months

        FROM membership_plans

        WHERE plan_id = ?
        AND gym_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $plan_id,
    $gym_id
);

$stmt->execute();

$result = $stmt->get_result();

$plan = $result->fetch_assoc();


if (!$plan) {

    die("Invalid membership plan.");

}


/*
|--------------------------------------------------------------------------
| Make sure duration is valid
|--------------------------------------------------------------------------
*/

$duration_months =
    (int) $plan["duration_months"];


if ($duration_months <= 0) {

    die("Invalid membership plan duration.");

}


/*
|--------------------------------------------------------------------------
| Check for overlapping membership
|--------------------------------------------------------------------------
|
| This is better than only checking:
|
| status = active
|
| because the owner might assign a membership
| starting on a date that overlaps an existing one.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            membership_id

        FROM member_memberships

        WHERE member_id = ?

        AND start_date <= ?

        AND end_date >= ?

        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $member_id,
    $start_date,
    $start_date
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    die(
        "This member already has a membership "
        . "that overlaps the selected start date."
    );

}


/*
|--------------------------------------------------------------------------
| Calculate expiry date
|--------------------------------------------------------------------------
|
| Example:
|
| Start:  2026-08-21
| Plan:   1 month
|
| End:    2026-09-20
|
| Example:
|
| Start:  2026-08-21
| Plan:   3 months
|
| End:    2026-11-20
|
|--------------------------------------------------------------------------
*/

$end = new DateTime($start_date);

$end->modify(
    "+" . $duration_months . " months"
);

$end->modify("-1 day");

$end_date = $end->format("Y-m-d");


/*
|--------------------------------------------------------------------------
| Insert membership
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO member_memberships
        (
            member_id,
            plan_id,
            start_date,
            end_date,
            status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'active'
        )";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iiss",
    $member_id,
    $plan_id,
    $start_date,
    $end_date
);


if (!$stmt->execute()) {

    die(
        "Unable to assign membership: "
        . $stmt->error
    );

}


$stmt->close();
$conn->close();


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

header(
    "Location: ../members.php?membership=success"
);

exit();

?>