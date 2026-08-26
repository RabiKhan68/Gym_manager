<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| OWNER LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../add_member.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION CHECK
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/check_subscription.php";


/*
|--------------------------------------------------------------------------
| GET OWNER'S GYM
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        gym_id,
        gym_name

    FROM gyms

    WHERE owner_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $owner_id
);

if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to find your gym."
    );

}

$result = $stmt->get_result();

$gym = $result->fetch_assoc();

$stmt->close();


if (!$gym) {

    die(
        "Gym not found."
    );

}


$gym_id = (int) $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| GET CURRENT SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$member_limit = null;

$sql = "
    SELECT
        s.subscription_id,
        sp.member_limit

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    AND s.status = 'active'

    AND s.start_date <= ?

    AND s.end_date >= ?

    ORDER BY
        s.end_date DESC,
        s.subscription_id DESC

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Unable to check subscription limit: " .
        htmlspecialchars($conn->error)
    );

}

$today = date("Y-m-d");

$stmt->bind_param(
    "iss",
    $owner_id,
    $today,
    $today
);

if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to verify subscription."
    );

}

$result = $stmt->get_result();

$subscription = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION SAFETY CHECK
|--------------------------------------------------------------------------
*/

if (!$subscription) {

    header(
        "Location: ../my_subscription.php?subscription=required"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT
|--------------------------------------------------------------------------
*/

if ($subscription["member_limit"] !== null) {

    $member_limit =
        (int) $subscription["member_limit"];

}


/*
|--------------------------------------------------------------------------
| COUNT CURRENT MEMBERS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(*) AS total

    FROM members

    WHERE gym_id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Unable to check member count: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $gym_id
);

if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to check current members."
    );

}

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$stmt->close();


$current_members =
    (int) (
        $row["total"] ?? 0
    );


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT ENFORCEMENT
|--------------------------------------------------------------------------
*/

if (
    $member_limit !== null
    &&
    $current_members >= $member_limit
) {

    $_SESSION["add_member_error"] =
        "Your current subscription allows a maximum of " .
        number_format($member_limit) .
        " members. Your gym currently has " .
        number_format($current_members) .
        " members. Please upgrade your subscription to add more members.";

    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$name =
    trim(
        $_POST["name"] ?? ""
    );


$phone =
    trim(
        $_POST["phone"] ?? ""
    );


$email =
    trim(
        $_POST["email"] ?? ""
    );


$joining_date =
    trim(
        $_POST["joining_date"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| NAME VALIDATION
|--------------------------------------------------------------------------
*/

if ($name === "") {

    $_SESSION["add_member_error"] =
        "Member name is required.";

    header(
        "Location: ../add_member.php"
    );

    exit();

}


if (mb_strlen($name) > 100) {

    $_SESSION["add_member_error"] =
        "Member name cannot exceed 100 characters.";

    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| PHONE VALIDATION
|--------------------------------------------------------------------------
|
| Phone remains optional because your current Add Member
| form does not mark it as required.
|
|--------------------------------------------------------------------------
*/

if (
    $phone !== ""
    &&
    !preg_match(
        '/^[0-9+\-\s()]{7,20}$/',
        $phone
    )
) {

    $_SESSION["add_member_error"] =
        "Please enter a valid phone number.";

    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| EMAIL VALIDATION
|--------------------------------------------------------------------------
|
| Email is optional.
|
|--------------------------------------------------------------------------
*/

if ($email !== "") {

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $_SESSION["add_member_error"] =
            "Please enter a valid email address.";

        header(
            "Location: ../add_member.php"
        );

        exit();

    }

    if (mb_strlen($email) > 150) {

        $_SESSION["add_member_error"] =
            "Email address cannot exceed 150 characters.";

        header(
            "Location: ../add_member.php"
        );

        exit();

    }

}


/*
|--------------------------------------------------------------------------
| CONVERT OPTIONAL VALUES TO NULL
|--------------------------------------------------------------------------
*/

$email_value =
    ($email !== "")
        ? $email
        : null;


$joining_date_value =
    ($joining_date !== "")
        ? $joining_date
        : null;


/*
|--------------------------------------------------------------------------
| VALIDATE JOINING DATE ONLY IF PROVIDED
|--------------------------------------------------------------------------
*/

if ($joining_date_value !== null) {

    $date_object =
        DateTime::createFromFormat(
            "Y-m-d",
            $joining_date_value
        );

    $date_errors =
        DateTime::getLastErrors();


    if (
        $date_object === false
        ||
        (
            $date_errors !== false
            &&
            (
                $date_errors["warning_count"] > 0
                ||
                $date_errors["error_count"] > 0
            )
        )
    ) {

        $_SESSION["add_member_error"] =
            "Please enter a valid joining date.";

        header(
            "Location: ../add_member.php"
        );

        exit();

    }


    /*
    | Make sure the date exactly matches Y-m-d.
    */

    if (
        $date_object->format("Y-m-d")
        !==
        $joining_date_value
    ) {

        $_SESSION["add_member_error"] =
            "Please enter a valid joining date.";

        header(
            "Location: ../add_member.php"
        );

        exit();

    }

}


/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {


    /*
    |--------------------------------------------------------------------------
    | GET NEXT MEMBER NUMBER
    |--------------------------------------------------------------------------
    |
    | Each gym has its own numbering:
    |
    | Gym 6 → 1,2,3...
    | Gym 7 → 1,2,3...
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            COALESCE(
                MAX(member_number),
                0
            ) AS highest_number

        FROM members

        WHERE gym_id = ?

        FOR UPDATE
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to determine the next member number."
        );

    }


    $stmt->bind_param(
        "i",
        $gym_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception(
            "Unable to determine the next member number."
        );

    }


    $result =
        $stmt->get_result();


    $number_row =
        $result->fetch_assoc();


    $stmt->close();


    $highest_number =
        (int) (
            $number_row["highest_number"]
            ?? 0
        );


    $member_number =
        $highest_number + 1;


    /*
    |--------------------------------------------------------------------------
    | INSERT MEMBER
    |--------------------------------------------------------------------------
    */

    $sql = "
        INSERT INTO members
        (
            gym_id,
            member_number,
            name,
            phone,
            email,
            joining_date
        )

        VALUES
        (?, ?, ?, ?, ?, ?)
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Database error while preparing member insertion."
        );

    }


    /*
    | mysqli bind_param does not accept NULL by reference
    | reliably in all situations, so use variables containing
    | either the actual value or NULL.
    */

    $phone_value =
        ($phone !== "")
            ? $phone
            : null;


    $stmt->bind_param(
        "iissss",
        $gym_id,
        $member_number,
        $name,
        $phone_value,
        $email_value,
        $joining_date_value
    );


    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            "Unable to add member: " .
            $error
        );

    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $conn->close();


    header(
        "Location: ../members.php"
    );

    exit();


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | HANDLE DUPLICATE MEMBER NUMBER
    |--------------------------------------------------------------------------
    */

    $error_message =
        $e->getMessage();


    if (
        stripos(
            $error_message,
            "unique_gym_member_number"
        ) !== false
    ) {

        $error_message =
            "A member number conflict occurred. " .
            "Please try adding the member again.";

    }


    /*
    |--------------------------------------------------------------------------
    | SAVE ERROR
    |--------------------------------------------------------------------------
    */

    $_SESSION["add_member_error"] =
        $error_message;


    $conn->close();


    header(
        "Location: ../add_member.php"
    );

    exit();

}

?>