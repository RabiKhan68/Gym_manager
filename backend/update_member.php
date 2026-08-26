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

    header("Location: ../members.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$member_id =
    (int) (
        $_POST["member_id"] ?? 0
    );


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
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($member_id <= 0) {

    die("Invalid member.");

}


if ($name === "") {

    die("Member name is required.");

}


if (mb_strlen($name) > 100) {

    die(
        "Member name cannot exceed 100 characters."
    );

}


/*
|--------------------------------------------------------------------------
| PHONE VALIDATION
|--------------------------------------------------------------------------
|
| Phone is optional.
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

    die(
        "Please enter a valid phone number."
    );

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

        die(
            "Please enter a valid email address."
        );

    }


    if (mb_strlen($email) > 150) {

        die(
            "Email address cannot exceed 150 characters."
        );

    }

}


/*
|--------------------------------------------------------------------------
| JOINING DATE VALIDATION
|--------------------------------------------------------------------------
|
| Joining date is optional.
|
|--------------------------------------------------------------------------
*/

if ($joining_date !== "") {

    $date_object =
        DateTime::createFromFormat(
            "Y-m-d",
            $joining_date
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

        die(
            "Please enter a valid joining date."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Ensure exact Y-m-d format
    |--------------------------------------------------------------------------
    */

    if (
        $date_object->format("Y-m-d")
        !==
        $joining_date
    ) {

        die(
            "Please enter a valid joining date."
        );

    }

}


/*
|--------------------------------------------------------------------------
| CONVERT OPTIONAL VALUES TO NULL
|--------------------------------------------------------------------------
*/

$phone_value =
    ($phone !== "")
        ? $phone
        : null;


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
| UPDATE MEMBER
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| member_number is NOT updated.
|
| The owner cannot change the gym-specific
| member number.
|
|--------------------------------------------------------------------------
*/

$sql = "
    UPDATE members m

    INNER JOIN gyms g
        ON m.gym_id = g.gym_id

    SET
        m.name = ?,
        m.phone = ?,
        m.email = ?,
        m.joining_date = ?

    WHERE m.member_id = ?

    AND g.owner_id = ?
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "ssssii",
    $name,
    $phone_value,
    $email_value,
    $joining_date_value,
    $member_id,
    $owner_id
);


/*
|--------------------------------------------------------------------------
| EXECUTE UPDATE
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    $stmt->close();

    $conn->close();


    header(
        "Location: ../members.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| UPDATE FAILED
|--------------------------------------------------------------------------
*/

$error =
    $stmt->error;


$stmt->close();

$conn->close();


die(
    "Error updating member: " .
    htmlspecialchars($error)
);

?>