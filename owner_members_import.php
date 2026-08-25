<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| LOAD PHPSPREADSHEET
|--------------------------------------------------------------------------
*/

$autoload = __DIR__ . "/vendor/autoload.php";

if (!file_exists($autoload)) {

    die(
        "PhpSpreadsheet is not installed. " .
        "Run: composer require phpoffice/phpspreadsheet"
    );

}

require_once $autoload;


use PhpOffice\PhpSpreadsheet\IOFactory;


/*
|--------------------------------------------------------------------------
| TIMEZONE
|--------------------------------------------------------------------------
*/

date_default_timezone_set("Asia/Karachi");


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| CHECK OWNER LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");

    exit();

}


$owner_id =
    (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION CHECK
|--------------------------------------------------------------------------
|
| This protects the import page itself.
|
| IMPORTANT:
|
| The actual import confirmation below performs another
| subscription check. This prevents a bypass through the
| POST request or an expired/deleted subscription between
| preview and confirmation.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/backend/check_subscription.php";


/*
|--------------------------------------------------------------------------
| FIND OWNER'S GYM
|--------------------------------------------------------------------------
*/

$gym_sql = "
    SELECT
        gym_id,
        gym_name

    FROM gyms

    WHERE owner_id = ?

    ORDER BY gym_id ASC

    LIMIT 1
";


$stmt =
    $conn->prepare($gym_sql);


if (!$stmt) {

    die(
        "Database error: " .
        e($conn->error)
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


$gym_result =
    $stmt->get_result();


if (
    $gym_result->num_rows === 0
) {

    $stmt->close();

    die(
        "No gym is associated with your account. " .
        "Please create your gym first."
    );

}


$gym =
    $gym_result->fetch_assoc();


$stmt->close();


$gym_id =
    (int) $gym["gym_id"];


$gym_name =
    $gym["gym_name"];


/*
|--------------------------------------------------------------------------
| FUNCTION: GET CURRENT SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Returns:
|
| [
|     subscription_id,
|     plan_name,
|     member_limit
| ]
|
| or null.
|
|--------------------------------------------------------------------------
*/

function get_current_owner_subscription(
    $conn,
    $owner_id
) {

    $today =
        date("Y-m-d");


    $sql = "
        SELECT

            s.subscription_id,

            s.subscription_plan_id,

            s.start_date,

            s.end_date,

            s.status,

            sp.plan_name,

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


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        return null;

    }


    $stmt->bind_param(
        "iss",
        $owner_id,
        $today,
        $today
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return null;

    }


    $result =
        $stmt->get_result();


    $subscription =
        $result->fetch_assoc();


    $stmt->close();


    return $subscription ?: null;

}


/*
|--------------------------------------------------------------------------
| FUNCTION: COUNT CURRENT MEMBERS
|--------------------------------------------------------------------------
*/

function get_current_member_count(
    $conn,
    $gym_id
) {

    $sql = "
        SELECT
            COUNT(*) AS total

        FROM members

        WHERE gym_id = ?
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        return null;

    }


    $stmt->bind_param(
        "i",
        $gym_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return null;

    }


    $result =
        $stmt->get_result();


    $row =
        $result->fetch_assoc();


    $stmt->close();


    return (int) (
        $row["total"] ?? 0
    );

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";

$preview_rows = [];

$import_results = [];

$show_preview = false;


/*
|--------------------------------------------------------------------------
| CURRENT SUBSCRIPTION
|--------------------------------------------------------------------------
|
| We load it now so the page can display the owner's
| current member limit.
|
|--------------------------------------------------------------------------
*/

$current_subscription =
    get_current_owner_subscription(
        $conn,
        $owner_id
    );


/*
|--------------------------------------------------------------------------
| SAFETY CHECK
|--------------------------------------------------------------------------
*/

if (!$current_subscription) {

    header(
        "Location: my_subscription.php?subscription=required"
    );

    exit();

}


$member_limit =
    $current_subscription["member_limit"] !== null
    ? (int) $current_subscription["member_limit"]
    : null;


$current_member_count =
    get_current_member_count(
        $conn,
        $gym_id
    );


if ($current_member_count === null) {

    die(
        "Unable to determine current member count."
    );

}


/*
|--------------------------------------------------------------------------
| DOWNLOAD TEMPLATE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["download_template"]) &&
    $_GET["download_template"] === "1"
) {

    header(
        "Content-Type: text/csv; charset=UTF-8"
    );

    header(
        'Content-Disposition: attachment; filename="members_import_template.csv"'
    );

    header(
        "Pragma: no-cache"
    );

    header(
        "Expires: 0"
    );


    $output =
        fopen(
            "php://output",
            "w"
        );


    fputcsv(
        $output,
        [
            "name",
            "phone",
            "email",
            "joining_date",
            "status"
        ]
    );


    fputcsv(
        $output,
        [
            "Ali Khan",
            "03001234567",
            "ali@example.com",
            date("Y-m-d"),
            "active"
        ]
    );


    fputcsv(
        $output,
        [
            "Ahmed Raza",
            "03111234567",
            "ahmed@example.com",
            date("Y-m-d"),
            "active"
        ]
    );


    fclose($output);

    exit();

}


/*
|--------------------------------------------------------------------------
| PROCESS EXCEL FILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_FILES["members_file"]) &&
    !isset($_POST["confirm_import"])
) {

    $file =
        $_FILES["members_file"];


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT SUBSCRIPTION RECHECK
    |--------------------------------------------------------------------------
    */

    $current_subscription =
        get_current_owner_subscription(
            $conn,
            $owner_id
        );


    if (!$current_subscription) {

        header(
            "Location: my_subscription.php?subscription=required"
        );

        exit();

    }


    $member_limit =
        $current_subscription["member_limit"] !== null
        ? (int) $current_subscription["member_limit"]
        : null;


    $current_member_count =
        get_current_member_count(
            $conn,
            $gym_id
        );


    if ($current_member_count === null) {

        $error =
            "Unable to determine your current member count.";

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK WHETHER LIMIT IS ALREADY REACHED
    |--------------------------------------------------------------------------
    */

    elseif (
        $member_limit !== null &&
        $current_member_count >= $member_limit
    ) {

        $error =
            "Your current subscription allows a maximum of " .
            number_format($member_limit) .
            " members. Your gym already has " .
            number_format($current_member_count) .
            " members. Please upgrade your subscription before importing more members.";

    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD ERROR
    |--------------------------------------------------------------------------
    */

    elseif (
        $file["error"] !== UPLOAD_ERR_OK
    ) {

        $error =
            "There was a problem uploading the file.";

    }


    /*
    |--------------------------------------------------------------------------
    | FILE SIZE
    |--------------------------------------------------------------------------
    */

    elseif (
        $file["size"] <= 0
    ) {

        $error =
            "The uploaded file is empty.";

    }

    elseif (
        $file["size"] >
        10 * 1024 * 1024
    ) {

        $error =
            "File size cannot exceed 10 MB.";

    }


    /*
    |--------------------------------------------------------------------------
    | READ FILE
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            $spreadsheet =
                IOFactory::load(
                    $file["tmp_name"]
                );


            $worksheet =
                $spreadsheet->getActiveSheet();


            $rows =
                $worksheet->toArray(
                    null,
                    true,
                    true,
                    true
                );


            if (
                count($rows) < 2
            ) {

                throw new Exception(
                    "The file does not contain any member records."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | READ HEADER
            |--------------------------------------------------------------------------
            */

            $header_row =
                array_shift($rows);


            $headers = [];


            foreach (
                $header_row as $column => $header
            ) {

                $header_name =
                    strtolower(
                        trim(
                            (string) $header
                        )
                    );


                $headers[$column] =
                    $header_name;

            }


            /*
            |--------------------------------------------------------------------------
            | REQUIRED COLUMNS
            |--------------------------------------------------------------------------
            */

            $required_columns = [

                "name",
                "phone",
                "email",
                "joining_date",
                "status"

            ];


            foreach (
                $required_columns
                as $required
            ) {

                if (
                    !in_array(
                        $required,
                        $headers,
                        true
                    )
                ) {

                    throw new Exception(
                        "Missing required column: " .
                        $required
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | MAP HEADER POSITIONS
            |--------------------------------------------------------------------------
            */

            $column_map = [];


            foreach (
                $headers as $column => $name
            ) {

                $column_map[$name] =
                    $column;

            }


            /*
            |--------------------------------------------------------------------------
            | PROCESS ROWS
            |--------------------------------------------------------------------------
            */

            $row_number = 1;


            foreach (
                $rows as $row
            ) {

                $row_number++;


                /*
                |--------------------------------------------------------------------------
                | IGNORE COMPLETELY EMPTY ROWS
                |--------------------------------------------------------------------------
                */

                $all_empty = true;


                foreach (
                    $required_columns
                    as $column
                ) {

                    $value =
                        trim(
                            (string)
                            (
                                $row[
                                    $column_map[
                                        $column
                                    ]
                                ] ?? ""
                            )
                        );


                    if (
                        $value !== ""
                    ) {

                        $all_empty = false;

                        break;

                    }

                }


                if ($all_empty) {

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | GET VALUES
                |--------------------------------------------------------------------------
                */

                $name =
                    trim(
                        (string)
                        (
                            $row[
                                $column_map["name"]
                            ] ?? ""
                        )
                    );


                $phone =
                    trim(
                        (string)
                        (
                            $row[
                                $column_map["phone"]
                            ] ?? ""
                        )
                    );


                $email =
                    trim(
                        (string)
                        (
                            $row[
                                $column_map["email"]
                            ] ?? ""
                        )
                    );


                $joining_date =
                    trim(
                        (string)
                        (
                            $row[
                                $column_map[
                                    "joining_date"
                                ]
                            ] ?? ""
                        )
                    );


                $status =
                    strtolower(
                        trim(
                            (string)
                            (
                                $row[
                                    $column_map["status"]
                                ] ?? "active"
                            )
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | VALIDATION
                |--------------------------------------------------------------------------
                */

                $row_errors = [];


                /*
                |--------------------------------------------------------------------------
                | NAME
                |--------------------------------------------------------------------------
                */

                if (
                    $name === ""
                ) {

                    $row_errors[] =
                        "Name is required.";

                }
                elseif (
                    mb_strlen($name) > 100
                ) {

                    $row_errors[] =
                        "Name cannot exceed 100 characters.";

                }


                /*
                |--------------------------------------------------------------------------
                | PHONE
                |--------------------------------------------------------------------------
                */

                if (
                    $phone !== "" &&
                    !preg_match(
                        '/^[0-9+\-\s()]{7,20}$/',
                        $phone
                    )
                ) {

                    $row_errors[] =
                        "Invalid phone number.";

                }


                /*
                |--------------------------------------------------------------------------
                | EMAIL
                |--------------------------------------------------------------------------
                */

                if (
                    $email !== "" &&
                    !filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {

                    $row_errors[] =
                        "Invalid email address.";

                }


                /*
                |--------------------------------------------------------------------------
                | DATE
                |--------------------------------------------------------------------------
                */

                $normalized_date = "";


                if (
                    $joining_date === ""
                ) {

                    $row_errors[] =
                        "Joining date is required.";

                }
                else {

                    /*
                    |--------------------------------------------------------------------------
                    | EXCEL DATE NUMBER
                    |--------------------------------------------------------------------------
                    */

                    if (
                        is_numeric(
                            $joining_date
                        )
                    ) {

                        try {

                            $date_object =
                                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                                    $joining_date
                                );


                            $normalized_date =
                                $date_object->format(
                                    "Y-m-d"
                                );

                        }
                        catch (
                            Exception $date_exception
                        ) {

                            $normalized_date = "";

                        }

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | NORMAL DATE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $normalized_date === ""
                    ) {

                        $timestamp =
                            strtotime(
                                $joining_date
                            );


                        if (
                            $timestamp !== false
                        ) {

                            $normalized_date =
                                date(
                                    "Y-m-d",
                                    $timestamp
                                );

                        }

                    }


                    if (
                        $normalized_date === ""
                    ) {

                        $row_errors[] =
                            "Invalid joining date.";

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                if (
                    $status === ""
                ) {

                    $status =
                        "active";

                }


                if (
                    !in_array(
                        $status,
                        [
                            "active",
                            "inactive"
                        ],
                        true
                    )
                ) {

                    $row_errors[] =
                        "Status must be active or inactive.";

                }


                /*
                |--------------------------------------------------------------------------
                | DUPLICATES INSIDE EXCEL FILE
                |--------------------------------------------------------------------------
                */

                foreach (
                    $preview_rows as $previous
                ) {

                    if (
                        $phone !== "" &&
                        $previous["phone"] !== "" &&
                        $phone ===
                        $previous["phone"]
                    ) {

                        $row_errors[] =
                            "Duplicate phone number in this file.";

                        break;

                    }


                    if (
                        $email !== "" &&
                        $previous["email"] !== "" &&
                        strtolower($email) ===
                        strtolower(
                            $previous["email"]
                        )
                    ) {

                        $row_errors[] =
                            "Duplicate email address in this file.";

                        break;

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | CHECK EXISTING MEMBER
                |--------------------------------------------------------------------------
                */

                if (
                    count($row_errors) === 0 &&
                    (
                        $phone !== "" ||
                        $email !== ""
                    )
                ) {

                    $duplicate_sql = "
                        SELECT
                            member_id

                        FROM members

                        WHERE gym_id = ?

                        AND (

                            (
                                ? <> ''
                                AND phone = ?
                            )

                            OR

                            (
                                ? <> ''
                                AND email = ?
                            )

                        )

                        LIMIT 1
                    ";


                    $duplicate_stmt =
                        $conn->prepare(
                            $duplicate_sql
                        );


                    if (
                        $duplicate_stmt
                    ) {

                        $duplicate_stmt->bind_param(
                            "issss",
                            $gym_id,
                            $phone,
                            $phone,
                            $email,
                            $email
                        );


                        $duplicate_stmt->execute();


                        $duplicate_result =
                            $duplicate_stmt->get_result();


                        if (
                            $duplicate_result->num_rows > 0
                        ) {

                            $existing =
                                $duplicate_result->fetch_assoc();


                            $row_errors[] =
                                "Member already exists in your gym (Member ID: " .
                                $existing["member_id"] .
                                ").";

                        }


                        $duplicate_stmt->close();

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | ADD PREVIEW ROW
                |--------------------------------------------------------------------------
                */

                $preview_rows[] = [

                    "row_number" =>
                        $row_number,

                    "name" =>
                        $name,

                    "phone" =>
                        $phone,

                    "email" =>
                        $email,

                    "joining_date" =>
                        $normalized_date,

                    "status" =>
                        $status,

                    "errors" =>
                        $row_errors

                ];

            }


            if (
                count($preview_rows) === 0
            ) {

                throw new Exception(
                    "No valid member rows were found in the file."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | MEMBER LIMIT CHECK FOR PREVIEW
            |--------------------------------------------------------------------------
            |
            | Only rows that are actually valid can potentially
            | become new members.
            |
            |--------------------------------------------------------------------------
            */

            $preview_valid_count = 0;


            foreach (
                $preview_rows as $preview_row
            ) {

                if (
                    empty(
                        $preview_row["errors"]
                    )
                ) {

                    $preview_valid_count++;

                }

            }


            if (
                $member_limit !== null
            ) {

                $available_slots =
                    $member_limit -
                    $current_member_count;


                if (
                    $available_slots <= 0
                ) {

                    throw new Exception(
                        "Your subscription member limit has already been reached. " .
                        "Your plan allows " .
                        number_format($member_limit) .
                        " members and your gym currently has " .
                        number_format($current_member_count) .
                        "."
                    );

                }


                if (
                    $preview_valid_count >
                    $available_slots
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | IMPORTANT
                    |--------------------------------------------------------------------------
                    |
                    | We do NOT allow a partial import.
                    |
                    | Example:
                    |
                    | Limit = 20
                    | Current = 18
                    | Valid Excel rows = 5
                    |
                    | Importing 2 and silently skipping 3 would be confusing.
                    |
                    | Instead, we require the owner to reduce the file
                    | to the available 2 slots or upgrade.
                    |
                    |--------------------------------------------------------------------------
                    */

                    $overflow =
                        $preview_valid_count -
                        $available_slots;


                    throw new Exception(
                        "Your current subscription allows only " .
                        number_format($available_slots) .
                        " more member(s). " .
                        "This file contains " .
                        number_format($preview_valid_count) .
                        " valid new member(s). " .
                        "Please remove " .
                        number_format($overflow) .
                        " member(s) from the file or upgrade your subscription."
                    );

                }

            }


            $show_preview = true;


            /*
            |--------------------------------------------------------------------------
            | SAVE PREVIEW TO SESSION
            |--------------------------------------------------------------------------
            */

            $_SESSION[
                "member_import_preview"
            ] = [

                "owner_id" =>
                    $owner_id,

                "gym_id" =>
                    $gym_id,

                "rows" =>
                    $preview_rows

            ];

        }
        catch (
            Throwable $exception
        ) {

            $error =
                "Unable to read the file: " .
                $exception->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| CONFIRM IMPORT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["confirm_import"])
) {

    /*
    |--------------------------------------------------------------------------
    | RECHECK SUBSCRIPTION
    |--------------------------------------------------------------------------
    |
    | This is extremely important.
    |
    | The owner may have uploaded the file while subscribed,
    | but the subscription could have expired before pressing
    | Confirm Import.
    |
    |--------------------------------------------------------------------------
    */

    $current_subscription =
        get_current_owner_subscription(
            $conn,
            $owner_id
        );


    if (!$current_subscription) {

        unset(
            $_SESSION[
                "member_import_preview"
            ]
        );


        header(
            "Location: my_subscription.php?subscription=required"
        );

        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT MEMBER COUNT AGAIN
    |--------------------------------------------------------------------------
    */

    $current_member_count =
        get_current_member_count(
            $conn,
            $gym_id
        );


    if (
        $current_member_count === null
    ) {

        $error =
            "Unable to determine the current member count.";

    }
    else {

        $member_limit =
            $current_subscription["member_limit"] !== null
            ? (int)
              $current_subscription["member_limit"]
            : null;


        /*
        |--------------------------------------------------------------------------
        | LOAD STORED PREVIEW
        |--------------------------------------------------------------------------
        */

        $stored_preview =
            $_SESSION[
                "member_import_preview"
            ] ?? null;


        if (
            !$stored_preview
        ) {

            $error =
                "Import session expired. Please upload the file again.";

        }
        elseif (
            (int)
            $stored_preview["owner_id"]
            !==
            $owner_id
        ) {

            $error =
                "Security validation failed.";

        }
        elseif (
            (int)
            $stored_preview["gym_id"]
            !==
            $gym_id
        ) {

            $error =
                "Gym validation failed.";

        }
        else {

            $rows =
                $stored_preview["rows"];


            $valid_rows = [];


            foreach (
                $rows as $row
            ) {

                if (
                    empty(
                        $row["errors"]
                    )
                ) {

                    $valid_rows[] =
                        $row;

                }

            }


            if (
                count($valid_rows) === 0
            ) {

                $error =
                    "There are no valid rows to import.";

                $show_preview = true;

                $preview_rows =
                    $rows;

            }
            else {

                /*
                |--------------------------------------------------------------------------
                | FINAL MEMBER LIMIT CHECK
                |--------------------------------------------------------------------------
                */

                if (
                    $member_limit !== null
                ) {

                    $available_slots =
                        $member_limit -
                        $current_member_count;


                    if (
                        $available_slots <= 0
                    ) {

                        $error =
                            "Your subscription member limit has been reached. " .
                            "Please upgrade your subscription to import more members.";

                        $show_preview = true;

                        $preview_rows =
                            $rows;

                    }
                    elseif (
                        count($valid_rows) >
                        $available_slots
                    ) {

                        $error =
                            "Your subscription only allows " .
                            number_format($available_slots) .
                            " more member(s), but this import contains " .
                            number_format(count($valid_rows)) .
                            " valid member(s). " .
                            "Please reduce the import file or upgrade your subscription.";

                        $show_preview = true;

                        $preview_rows =
                            $rows;

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | CONTINUE ONLY IF LIMIT IS OK
                |--------------------------------------------------------------------------
                */

                if (
                    $error === ""
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | START TRANSACTION
                    |--------------------------------------------------------------------------
                    */

                    $conn->begin_transaction();


                    try {

                        /*
                        |--------------------------------------------------------------------------
                        | FINAL SUBSCRIPTION CHECK INSIDE TRANSACTION
                        |--------------------------------------------------------------------------
                        |
                        | This protects the actual insertion operation.
                        |
                        |--------------------------------------------------------------------------
                        */

                        $final_subscription =
                            get_current_owner_subscription(
                                $conn,
                                $owner_id
                            );


                        if (
                            !$final_subscription
                        ) {

                            throw new Exception(
                                "Your subscription is no longer active. " .
                                "Please purchase or renew a subscription."
                            );

                        }


                        $final_member_limit =
                            $final_subscription[
                                "member_limit"
                            ] !== null
                            ? (int)
                              $final_subscription[
                                  "member_limit"
                              ]
                            : null;


                        /*
                        |--------------------------------------------------------------------------
                        | FINAL MEMBER COUNT
                        |--------------------------------------------------------------------------
                        */

                        $final_member_count =
                            get_current_member_count(
                                $conn,
                                $gym_id
                            );


                        if (
                            $final_member_count === null
                        ) {

                            throw new Exception(
                                "Unable to verify the current member count."
                            );

                        }


                        if (
                            $final_member_limit !== null
                        ) {

                            $final_available_slots =
                                $final_member_limit -
                                $final_member_count;


                            if (
                                count($valid_rows) >
                                $final_available_slots
                            ) {

                                throw new Exception(
                                    "The subscription member limit has been reached or there are not enough member slots available. " .
                                    "Your plan allows " .
                                    number_format(
                                        $final_member_limit
                                    ) .
                                    " members and your gym currently has " .
                                    number_format(
                                        $final_member_count
                                    ) .
                                    "."
                                );

                            }

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PREPARE INSERT
                        |--------------------------------------------------------------------------
                        */

                        $insert_sql = "
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
                            (?, ?, ?, ?, ?, ?)
                        ";


                        $insert_stmt =
                            $conn->prepare(
                                $insert_sql
                            );


                        if (
                            !$insert_stmt
                        ) {

                            throw new Exception(
                                $conn->error
                            );

                        }


                        $imported_count = 0;

                        $failed_count = 0;


                        /*
                        |--------------------------------------------------------------------------
                        | INSERT VALID ROWS
                        |--------------------------------------------------------------------------
                        */

                        foreach (
                            $valid_rows as $row
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | FINAL DUPLICATE CHECK
                            |--------------------------------------------------------------------------
                            |
                            | Someone could have added the same member
                            | after the preview was generated.
                            |
                            |--------------------------------------------------------------------------
                            */

                            $check_sql = "
                                SELECT
                                    member_id

                                FROM members

                                WHERE gym_id = ?

                                AND (

                                    (
                                        ? <> ''
                                        AND phone = ?
                                    )

                                    OR

                                    (
                                        ? <> ''
                                        AND email = ?
                                    )

                                )

                                LIMIT 1
                            ";


                            $check_stmt =
                                $conn->prepare(
                                    $check_sql
                                );


                            if (
                                !$check_stmt
                            ) {

                                throw new Exception(
                                    $conn->error
                                );

                            }


                            $check_stmt->bind_param(
                                "issss",
                                $gym_id,
                                $row["phone"],
                                $row["phone"],
                                $row["email"],
                                $row["email"]
                            );


                            if (
                                !$check_stmt->execute()
                            ) {

                                $check_stmt->close();

                                throw new Exception(
                                    "Unable to perform duplicate check."
                                );

                            }


                            $check_result =
                                $check_stmt->get_result();


                            $duplicate_exists =
                                $check_result->num_rows > 0;


                            $check_stmt->close();


                            if (
                                $duplicate_exists
                            ) {

                                $failed_count++;

                                continue;

                            }


                            /*
                            |--------------------------------------------------------------------------
                            | INSERT MEMBER
                            |--------------------------------------------------------------------------
                            */

                            $insert_stmt->bind_param(
                                "isssss",
                                $gym_id,
                                $row["name"],
                                $row["phone"],
                                $row["email"],
                                $row["joining_date"],
                                $row["status"]
                            );


                            if (
                                $insert_stmt->execute()
                            ) {

                                $imported_count++;

                            }
                            else {

                                throw new Exception(
                                    $insert_stmt->error
                                );

                            }

                        }


                        $insert_stmt->close();


                        /*
                        |--------------------------------------------------------------------------
                        | COMMIT
                        |--------------------------------------------------------------------------
                        */

                        $conn->commit();


                        /*
                        |--------------------------------------------------------------------------
                        | CLEAR PREVIEW
                        |--------------------------------------------------------------------------
                        */

                        unset(
                            $_SESSION[
                                "member_import_preview"
                            ]
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | SUCCESS MESSAGE
                        |--------------------------------------------------------------------------
                        */

                        $success =
                            $imported_count .
                            " member(s) imported successfully.";


                        if (
                            $failed_count > 0
                        ) {

                            $success .=
                                " " .
                                $failed_count .
                                " member(s) were skipped because they already existed.";

                        }

                    }
                    catch (
                        Throwable $exception
                    ) {

                        $conn->rollback();


                        $error =
                            "Import failed. No members were added. " .
                            $exception->getMessage();


                        $show_preview = true;

                        $preview_rows =
                            $rows;

                    }

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$preview_total =
    count($preview_rows);


$preview_valid = 0;


$preview_invalid = 0;


foreach (
    $preview_rows as $row
) {

    if (
        empty(
            $row["errors"]
        )
    ) {

        $preview_valid++;

    }
    else {

        $preview_invalid++;

    }

}

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
        Import Members
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #1f2937;

        }


        .container {

            max-width: 1400px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 28px;

        }


        .header p {

            margin: 6px 0 0;

            color: #6b7280;

        }


        .back {

            background: #111827;

            color: white;

            text-decoration: none;

            padding: 10px 18px;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, .06);

            margin-bottom: 25px;

        }


        .card h2 {

            margin-top: 0;

        }


        .gym-info {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            color: #1e40af;

            padding: 14px 16px;

            border-radius: 9px;

            margin-bottom: 20px;

        }


        .subscription-info {

            background: #f0fdf4;

            border:
                1px solid #bbf7d0;

            color: #166534;

            padding: 14px 16px;

            border-radius: 9px;

            margin-bottom: 20px;

        }


        .subscription-warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

            padding: 14px 16px;

            border-radius: 9px;

            margin-bottom: 20px;

        }


        .instructions {

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            padding: 18px;

            border-radius: 9px;

            margin-bottom: 20px;

            line-height: 1.6;

        }


        .instructions ul {

            margin-bottom: 0;

        }


        .template-button {

            display: inline-block;

            background: #2563eb;

            color: white;

            padding: 11px 17px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .template-button:hover {

            opacity: .85;

        }


        input[type="file"] {

            width: 100%;

            padding: 12px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            background: white;

            margin-bottom: 15px;

        }


        .button {

            border: none;

            border-radius: 8px;

            padding: 12px 20px;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            display: inline-block;

        }


        .button-blue {

            background: #2563eb;

            color: white;

        }


        .button-green {

            background: #16a34a;

            color: white;

        }


        .button-gray {

            background: #e5e7eb;

            color: #111827;

        }


        .button:hover {

            opacity: .85;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;

            padding: 15px;

            border-radius: 9px;

            margin-bottom: 20px;

        }


        .success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #bbf7d0;

            padding: 15px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-weight: bold;

        }


        .summary {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 20px;

        }


        .summary-box {

            padding: 18px;

            border-radius: 10px;

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

        }


        .summary-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 7px;

        }


        .summary-number {

            font-size: 25px;

            font-weight: bold;

        }


        .valid {

            color: #166534;

        }


        .invalid {

            color: #991b1b;

        }


        .table-wrapper {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 950px;

        }


        th,
        td {

            padding: 12px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

            vertical-align: top;

        }


        th {

            background: #f8fafc;

            white-space: nowrap;

        }


        .row-valid {

            background: #f0fdf4;

        }


        .row-invalid {

            background: #fef2f2;

        }


        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .badge-active {

            background: #dcfce7;

            color: #166534;

        }


        .badge-inactive {

            background: #e5e7eb;

            color: #374151;

        }


        .badge-valid {

            background: #dcfce7;

            color: #166534;

        }


        .badge-invalid {

            background: #fee2e2;

            color: #991b1b;

        }


        .errors {

            color: #991b1b;

            font-size: 13px;

            line-height: 1.5;

        }


        .actions {

            display: flex;

            gap: 10px;

            margin-top: 20px;

        }


        .note {

            color: #6b7280;

            font-size: 13px;

            margin-top: 8px;

        }


        @media (
            max-width: 700px
        ) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .summary {

                grid-template-columns:
                    1fr;

            }


            .card {

                padding: 20px;

            }


            .actions {

                flex-direction: column;

            }


            .button {

                text-align: center;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Import Members
            </h1>

            <p>
                Add multiple gym members using an Excel file.
            </p>

        </div>


        <a
            href="members.php"
            class="back"
        >

            ← Members

        </a>

    </div>



    <!-- MESSAGES -->

    <?php if ($error !== ""): ?>

        <div class="error">

            <?php echo e($error); ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="success">

            <?php echo e($success); ?>

        </div>

    <?php endif; ?>



    <!-- GYM -->

    <div class="card">

        <div class="gym-info">

            <strong>
                Importing members for:
            </strong>

            <?php echo e($gym_name); ?>

            <br>

            <small>
                Gym ID:
                <?php echo $gym_id; ?>
            </small>

        </div>


        <!-- SUBSCRIPTION INFORMATION -->

        <?php if ($member_limit !== null): ?>

            <div class="subscription-info">

                <strong>
                    Current subscription:
                </strong>

                <?php
                echo e(
                    $current_subscription[
                        "plan_name"
                    ]
                );
                ?>

                <br>

                Member limit:

                <strong>

                    <?php
                    echo number_format(
                        $member_limit
                    );
                    ?>

                </strong>

                members

                <br>

                Current members:

                <strong>

                    <?php
                    echo number_format(
                        $current_member_count
                    );
                    ?>

                </strong>


                <?php

                $available_slots =
                    $member_limit -
                    $current_member_count;

                ?>


                <br>

                Available slots:

                <strong>

                    <?php
                    echo number_format(
                        max(
                            0,
                            $available_slots
                        )
                    );
                    ?>

                </strong>

            </div>

        <?php else: ?>

            <div class="subscription-info">

                <strong>
                    Current subscription:
                </strong>

                <?php
                echo e(
                    $current_subscription[
                        "plan_name"
                    ]
                );
                ?>

                <br>

                ✓ Unlimited members

            </div>

        <?php endif; ?>


        <h2>
            Upload Member Excel File
        </h2>


        <div class="instructions">

            <strong>
                Before uploading:
            </strong>

            <ul>

                <li>
                    Download the template below.
                </li>

                <li>
                    Keep the column names unchanged.
                </li>

                <li>
                    Do not add a <strong>gym_id</strong> column.
                    The system automatically assigns members to your gym.
                </li>

                <li>
                    <strong>name</strong> is required.
                </li>

                <li>
                    Phone and email are optional.
                </li>

                <li>
                    Joining date should preferably be
                    <strong>YYYY-MM-DD</strong>.
                </li>

                <li>
                    Status must be
                    <strong>active</strong>
                    or
                    <strong>inactive</strong>.
                </li>

                <?php if ($member_limit !== null): ?>

                    <li>

                        You can import only enough members
                        to stay within your subscription's
                        member limit.

                    </li>

                <?php endif; ?>

            </ul>

        </div>


        <a
            href="?download_template=1"
            class="template-button"
        >

            ↓ Download Excel Template

        </a>


        <?php if (
            $member_limit === null ||
            $current_member_count < $member_limit
        ): ?>


            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="file"
                    name="members_file"
                    accept=".xlsx,.xls,.csv"
                    required
                >


                <div class="note">

                    Maximum file size: 10 MB.

                </div>


                <br>


                <button
                    type="submit"
                    class="button button-blue"
                >

                    Upload & Preview

                </button>

            </form>


        <?php else: ?>


            <div class="subscription-warning">

                <strong>
                    Member limit reached.
                </strong>

                <br><br>

                Your gym currently has

                <strong>
                    <?php
                    echo number_format(
                        $current_member_count
                    );
                    ?>
                </strong>

                members.

                Your

                <strong>
                    <?php
                    echo e(
                        $current_subscription[
                            "plan_name"
                        ]
                    );
                    ?>
                </strong>

                subscription allows a maximum of

                <strong>
                    <?php
                    echo number_format(
                        $member_limit
                    );
                    ?>
                </strong>

                members.

                <br><br>

                Please upgrade your subscription
                to import additional members.

                <br><br>

                <a
                    href="my_subscription.php"
                    class="button button-blue"
                >

                    View Subscription Plans

                </a>

            </div>

        <?php endif; ?>

    </div>



    <!-- PREVIEW -->

    <?php if ($show_preview): ?>


        <div class="card">

            <h2>
                Import Preview
            </h2>


            <div class="summary">


                <div class="summary-box">

                    <div class="summary-label">
                        Total Rows
                    </div>

                    <div class="summary-number">

                        <?php
                        echo $preview_total;
                        ?>

                    </div>

                </div>


                <div class="summary-box">

                    <div class="summary-label">
                        Ready to Import
                    </div>

                    <div
                        class="
                        summary-number
                        valid
                        "
                    >

                        <?php
                        echo $preview_valid;
                        ?>

                    </div>

                </div>


                <div class="summary-box">

                    <div class="summary-label">
                        Rows With Errors
                    </div>

                    <div
                        class="
                        summary-number
                        invalid
                        "
                    >

                        <?php
                        echo $preview_invalid;
                        ?>

                    </div>

                </div>


            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Excel Row
                            </th>

                            <th>
                                Name
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Joining Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Validation
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $preview_rows
                        as $row
                    ): ?>


                        <tr
                            class="<?php
                                echo empty(
                                    $row["errors"]
                                )
                                ? "row-valid"
                                : "row-invalid";
                            ?>"
                        >


                            <td>

                                <?php
                                echo (int)
                                    $row[
                                        "row_number"
                                    ];
                                ?>

                            </td>


                            <td>

                                <?php
                                echo e(
                                    $row["name"]
                                );
                                ?>

                            </td>


                            <td>

                                <?php

                                echo e(
                                    $row["phone"]
                                    !== ""
                                    ? $row["phone"]
                                    : "-"
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo e(
                                    $row["email"]
                                    !== ""
                                    ? $row["email"]
                                    : "-"
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo e(
                                    $row[
                                        "joining_date"
                                    ] !== ""
                                    ? $row[
                                        "joining_date"
                                    ]
                                    : "-"
                                );

                                ?>

                            </td>


                            <td>


                                <span
                                    class="
                                    badge
                                    <?php

                                    echo $row[
                                        "status"
                                    ] === "active"

                                    ? "badge-active"

                                    : "badge-inactive";

                                    ?>
                                    "
                                >

                                    <?php

                                    echo e(
                                        ucfirst(
                                            $row[
                                                "status"
                                            ]
                                        )
                                    );

                                    ?>

                                </span>


                            </td>


                            <td>


                                <?php if (
                                    empty(
                                        $row["errors"]
                                    )
                                ): ?>


                                    <span
                                        class="
                                        badge
                                        badge-valid
                                        "
                                    >

                                        ✓ Ready

                                    </span>


                                <?php else: ?>


                                    <span
                                        class="
                                        badge
                                        badge-invalid
                                        "
                                    >

                                        ✕ Error

                                    </span>


                                    <div
                                        class="errors"
                                    >

                                        <?php

                                        echo e(
                                            implode(
                                                " ",
                                                $row[
                                                    "errors"
                                                ]
                                            )
                                        );

                                        ?>

                                    </div>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>



            <?php if (
                $preview_valid > 0
            ): ?>


                <div class="actions">


                    <form
                        method="POST"
                        style="margin:0;"
                    >

                        <button
                            type="submit"
                            name="confirm_import"
                            value="1"
                            class="
                            button
                            button-green
                            "
                            onclick="
                                return confirm(
                                    'Import all valid members into your gym?'
                                );
                            "
                        >

                            ✓ Confirm Import
                            (<?php
                            echo $preview_valid;
                            ?>)

                        </button>

                    </form>


                    <a
                        href="owner_members_import.php"
                        class="
                        button
                        button-gray
                        "
                    >

                        Cancel

                    </a>


                </div>


            <?php else: ?>


                <div class="error">

                    There are no valid rows to import.

                    Please correct the Excel file and
                    upload it again.

                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>