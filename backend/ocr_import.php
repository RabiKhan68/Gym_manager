<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");

    exit();

}


$owner_id =
    (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| CHECK POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !isset($_POST["members"])
) {

    header(
        "Location: ../smart_member_import.php"
    );

    exit();

}


$members =
    $_POST["members"];


if (!is_array($members)) {

    die("Invalid member data.");

}


/*
|--------------------------------------------------------------------------
| GET OWNER'S GYM
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT gym_id

    FROM gyms

    WHERE owner_id = ?

    LIMIT 1

";


$stmt =
    $conn->prepare($sql);


$stmt->bind_param(
    "i",
    $owner_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$gym =
    $result->fetch_assoc();


if (!$gym) {

    die(
        "Gym not found."
    );

}


$gym_id =
    (int) $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| PREPARE INSERT
|--------------------------------------------------------------------------
*/

$sql = "

    INSERT INTO members
    (
        gym_id,
        name,
        phone,
        email,
        joining_date,
        status
    )

    VALUES
    (
        ?,
        ?,
        ?,
        NULL,
        ?,
        'active'
    )

";


$insert =
    $conn->prepare($sql);


if (!$insert) {

    die(
        "Could not prepare member insert."
    );

}


$imported = 0;

$skipped = 0;

$errors = [];


/*
|--------------------------------------------------------------------------
| DUPLICATE CHECK STATEMENT
|--------------------------------------------------------------------------
*/

$duplicate_sql = "

    SELECT member_id

    FROM members

    WHERE gym_id = ?

    AND phone = ?

    LIMIT 1

";


$duplicate_stmt =
    $conn->prepare(
        $duplicate_sql
    );


/*
|--------------------------------------------------------------------------
| IMPORT MEMBERS
|--------------------------------------------------------------------------
*/

foreach ($members as $index => $member) {


    $name =
        trim(
            $member["name"] ?? ""
        );


    $phone =
        trim(
            $member["phone"] ?? ""
        );


    $joining_date =
        trim(
            $member["joining_date"] ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | NAME VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($name === "") {

        $errors[] =
            "Row " .
            ($index + 1) .
            ": name is empty.";

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $joining_date
        )
    ) {

        $joining_date =
            date("Y-m-d");

    }


    /*
    |--------------------------------------------------------------------------
    | PHONE CLEANING
    |--------------------------------------------------------------------------
    */

    $phone =
        preg_replace(
            '/\D/',
            "",
            $phone
        );


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE CHECK
    |--------------------------------------------------------------------------
    */

    if ($phone !== "") {


        $duplicate_stmt->bind_param(
            "is",
            $gym_id,
            $phone
        );


        $duplicate_stmt->execute();


        $duplicate_result =
            $duplicate_stmt->get_result();


        if (
            $duplicate_result->num_rows > 0
        ) {

            $skipped++;

            continue;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | INSERT
    |--------------------------------------------------------------------------
    */

    $insert->bind_param(
        "isss",
        $gym_id,
        $name,
        $phone,
        $joining_date
    );


    if (
        $insert->execute()
    ) {

        $imported++;

    } else {

        $errors[] =
            "Could not import " .
            htmlspecialchars($name);

    }

}


/*
|--------------------------------------------------------------------------
| CLEAN OCR SESSION
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["ocr_members"],
    $_SESSION["ocr_text"],
    $_SESSION["ocr_original"],
    $_SESSION["ocr_processed"]
);


/*
|--------------------------------------------------------------------------
| RESULT
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        OCR Import Complete
    </title>

    <link
        rel="stylesheet"
        href="../css/smart_member_import.css"
    >

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            ✅ Import Complete
        </h1>


        <div class="info-box">

            <strong>
                <?php echo $imported; ?>
                members imported successfully.
            </strong>

        </div>


        <?php if ($skipped > 0): ?>

            <div class="warning-box">

                <strong>
                    <?php echo $skipped; ?>
                    duplicate member(s) skipped.
                </strong>

                <p>
                    Existing members were not added again.
                </p>

            </div>

        <?php endif; ?>


        <?php if (!empty($errors)): ?>

            <div class="warning-box">

                <strong>
                    Some records could not be imported.
                </strong>

                <ul>

                    <?php foreach (
                        $errors as $error
                    ): ?>

                        <li>
                            <?php
                            echo $error;
                            ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <div class="actions">


            <a
                href="../members.php"
                class="back"
            >

                ← View Members

            </a>


            <a
                href="../smart_member_import.php"
                class="back"
            >

                📷 Import More

            </a>


        </div>


    </div>


</div>


</body>

</html>