<?php

session_start();

date_default_timezone_set("Asia/Karachi");

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
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


function formatDate($date)
{
    if (!$date) {
        return "-";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return "-";
    }

    return date(
        "d M Y",
        $timestamp
    );
}


function formatPrice($price)
{
    return number_format(
        (float) $price,
        2
    );
}


/*
|--------------------------------------------------------------------------
| CALCULATE MONTHLY END DATE
|--------------------------------------------------------------------------
|
| Examples:
|
| 23 Aug -> 22 Sep
| 24 Aug -> 23 Sep
| 15 Aug -> 14 Sep
|
| For dates where the next month does not contain the same
| calendar day, the last valid day of the target month is used
| and then one day is subtracted.
|
|--------------------------------------------------------------------------
*/

function calculateMonthlyEndDate($start_date)
{
    try {

        $start =
            new DateTime($start_date);


        $original_day =
            (int)
            $start->format("d");


        $next_month =
            new DateTime(
                $start->format("Y-m-01")
            );


        $next_month->modify("+1 month");


        $days_in_target_month =
            (int)
            $next_month->format("t");


        $target_day =
            min(
                $original_day,
                $days_in_target_month
            );


        $target_date =
            new DateTime(
                sprintf(
                    "%04d-%02d-%02d",
                    (int)
                    $next_month->format("Y"),

                    (int)
                    $next_month->format("m"),

                    $target_day
                )
            );


        $target_date->modify("-1 day");


        return $target_date->format(
            "Y-m-d"
        );

    }
    catch (Exception $e) {

        return null;

    }
}


/*
|--------------------------------------------------------------------------
| GET PLAN ID
|--------------------------------------------------------------------------
*/

$plan_id =
    isset($_GET["plan_id"])
    ? (int) $_GET["plan_id"]
    : 0;


if ($plan_id <= 0) {

    header(
        "Location: my_subscription.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| LOAD SELECTED PLAN
|--------------------------------------------------------------------------
|
| NEVER trust price/member_limit from the browser.
|
| The database is always the source of truth.
|
|--------------------------------------------------------------------------
*/

$selected_plan = null;


$sql = "
    SELECT

        subscription_plan_id,
        plan_name,
        price,
        member_limit

    FROM subscription_plans

    WHERE subscription_plan_id = ?

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $plan_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load the selected subscription plan."
    );

}


$result =
    $stmt->get_result();


$selected_plan =
    $result->fetch_assoc();


$stmt->close();


if (!$selected_plan) {

    die(
        "The selected subscription plan does not exist."
    );

}


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$current_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,

        sp.plan_name,
        sp.price,
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

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "iss",
    $owner_id,
    $today,
    $today
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load current subscription."
    );

}


$result =
    $stmt->get_result();


$current_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET UPCOMING SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Only a future scheduled subscription counts here.
|
|--------------------------------------------------------------------------
*/

$upcoming_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,

        sp.plan_name,
        sp.price,
        sp.member_limit

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    AND s.status = 'scheduled'

    AND s.start_date > ?

    ORDER BY
        s.start_date ASC,
        s.subscription_id ASC

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "is",
    $owner_id,
    $today
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to check scheduled subscription."
    );

}


$result =
    $stmt->get_result();


$upcoming_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| DETERMINE PAYMENT TYPE
|--------------------------------------------------------------------------
|
| NEW
| ---
| No active subscription and no scheduled subscription.
|
| CHANGE
| ------
| Active subscription exists and selected plan is different.
|
| RENEW
| -----
| Active subscription exists and selected plan is the same.
|
|--------------------------------------------------------------------------
*/

$payment_type = "new";


if ($current_subscription) {

    if (
        (int)
        $current_subscription[
            "subscription_plan_id"
        ]
        ===
        $plan_id
    ) {

        $payment_type = "renew";

    }
    else {

        $payment_type = "change";

    }

}


/*
|--------------------------------------------------------------------------
| ERROR / SUCCESS
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| SESSION PAYMENT ERROR
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION["payment_error"]
    )
) {

    $error =
        $_SESSION["payment_error"];


    unset(
        $_SESSION["payment_error"]
    );

}


/*
|--------------------------------------------------------------------------
| PREVENT ANOTHER SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
*/

if (
    $upcoming_subscription
) {

    $error =
        "You already have a subscription scheduled " .
        "to start on " .
        formatDate(
            $upcoming_subscription["start_date"]
        ) .
        ". Please wait until the existing scheduled " .
        "subscription is activated before creating another one.";

}


/*
|--------------------------------------------------------------------------
| MEMBER COUNT
|--------------------------------------------------------------------------
*/

$total_members = 0;


$sql = "
    SELECT
        COUNT(*) AS total

    FROM members m

    INNER JOIN gyms g
        ON m.gym_id = g.gym_id

    WHERE g.owner_id = ?
";


$stmt =
    $conn->prepare($sql);


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
        "Unable to calculate member count."
    );

}


$result =
    $stmt->get_result();


$row =
    $result->fetch_assoc();


$total_members =
    (int)
    ($row["total"] ?? 0);


$stmt->close();


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $error === "" &&
    $selected_plan["member_limit"] !== null
) {

    $member_limit =
        (int)
        $selected_plan["member_limit"];


    if (
        $total_members >
        $member_limit
    ) {

        $error =
            "Your gym currently has " .
            number_format(
                $total_members
            ) .
            " members, but the " .
            $selected_plan["plan_name"] .
            " plan supports only " .
            number_format(
                $member_limit
            ) .
            " members.";

    }

}


/*
|--------------------------------------------------------------------------
| DETERMINE ASSOCIATED CURRENT SUBSCRIPTION ID
|--------------------------------------------------------------------------
|
| For:
|
| NEW
|     NULL
|
| CHANGE
|     Current subscription ID
|
| RENEW
|     Current subscription ID
|
|--------------------------------------------------------------------------
*/

$subscription_id = null;


if ($current_subscription) {

    $subscription_id =
        (int)
        $current_subscription[
            "subscription_id"
        ];

}


/*
|--------------------------------------------------------------------------
| DETERMINE PROPOSED SUBSCRIPTION DATES
|--------------------------------------------------------------------------
|
| These are DISPLAY / PAYMENT metadata only.
|
| The actual subscription record must be created only after
| successful payment verification.
|
|--------------------------------------------------------------------------
*/

$proposed_start_date = null;

$proposed_end_date = null;


if (
    $payment_type === "new"
) {

    /*
    | First subscription starts today.
    */

    $proposed_start_date =
        $today;

}
elseif (
    $payment_type === "renew"
) {

    try {

        $renew_start =
            new DateTime(
                $current_subscription[
                    "end_date"
                ]
            );


        $renew_start->modify(
            "+1 day"
        );


        $proposed_start_date =
            $renew_start->format(
                "Y-m-d"
            );

    }
    catch (Exception $e) {

        $error =
            "Unable to calculate the renewal start date.";

    }

}
elseif (
    $payment_type === "change"
) {

    try {

        $change_start =
            new DateTime(
                $current_subscription[
                    "end_date"
                ]
            );


        $change_start->modify(
            "+1 day"
        );


        $proposed_start_date =
            $change_start->format(
                "Y-m-d"
            );

    }
    catch (Exception $e) {

        $error =
            "Unable to calculate the plan change start date.";

    }

}


if (
    $proposed_start_date !== null
) {

    $proposed_end_date =
        calculateMonthlyEndDate(
            $proposed_start_date
        );


    if (!$proposed_end_date) {

        $error =
            "Unable to calculate the subscription end date.";

    }

}


/*
|--------------------------------------------------------------------------
| PAYMENT VARIABLES
|--------------------------------------------------------------------------
*/

$payment_id = null;

$transaction_reference = null;


/*
|--------------------------------------------------------------------------
| HANDLE CREATE PAYMENT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset(
        $_POST["create_payment"]
    )
) {

    /*
    |--------------------------------------------------------------------------
    | POST PLAN ID MUST MATCH URL PLAN ID
    |--------------------------------------------------------------------------
    */

    $posted_plan_id =
        isset($_POST["plan_id"])
        ? (int)
        $_POST["plan_id"]
        : 0;


    if (
        $posted_plan_id !==
        $plan_id
    ) {

        $error =
            "Invalid subscription plan request.";

    }


    /*
    |--------------------------------------------------------------------------
    | RELOAD PLAN
    |--------------------------------------------------------------------------
    |
    | Never rely on the plan information loaded before the POST.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $sql = "
            SELECT

                subscription_plan_id,
                plan_name,
                price,
                member_limit

            FROM subscription_plans

            WHERE subscription_plan_id = ?

            LIMIT 1
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                "Unable to verify the selected plan.";

        }
        else {

            $stmt->bind_param(
                "i",
                $plan_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error =
                    "Unable to verify the selected plan.";

            }
            else {

                $result =
                    $stmt->get_result();


                $locked_plan =
                    $result->fetch_assoc();


                $stmt->close();


                if (!$locked_plan) {

                    $error =
                        "The selected plan no longer exists.";

                }
                else {

                    $selected_plan =
                        $locked_plan;

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK CURRENT SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $sql = "
            SELECT

                s.subscription_id,
                s.subscription_plan_id,
                s.start_date,
                s.end_date,
                s.status,

                sp.plan_name,
                sp.price,
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

            $error =
                "Unable to verify your current subscription.";

        }
        else {

            $stmt->bind_param(
                "iss",
                $owner_id,
                $today,
                $today
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error =
                    "Unable to verify your current subscription.";

            }
            else {

                $result =
                    $stmt->get_result();


                $locked_current =
                    $result->fetch_assoc();


                $stmt->close();


                $current_subscription =
                    $locked_current;


                /*
                |--------------------------------------------------------------
                | Recalculate payment type.
                |--------------------------------------------------------------
                */

                $payment_type =
                    "new";


                if ($current_subscription) {

                    if (
                        (int)
                        $current_subscription[
                            "subscription_plan_id"
                        ]
                        ===
                        $plan_id
                    ) {

                        $payment_type =
                            "renew";

                    }
                    else {

                        $payment_type =
                            "change";

                    }

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK SCHEDULED SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $sql = "
            SELECT

                s.subscription_id,
                s.subscription_plan_id,
                s.start_date,
                s.end_date,
                s.status

            FROM gym_owner_subscriptions s

            WHERE s.owner_id = ?

            AND s.status = 'scheduled'

            AND s.start_date > ?

            ORDER BY
                s.start_date ASC,
                s.subscription_id ASC

            LIMIT 1

            FOR UPDATE
        ";


        /*
        | This SELECT is outside the transaction.
        | We use it only as a fresh validation.
        */

        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                "Unable to verify scheduled subscriptions.";

        }
        else {

            $stmt->bind_param(
                "is",
                $owner_id,
                $today
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error =
                    "Unable to verify scheduled subscriptions.";

            }
            else {

                $result =
                    $stmt->get_result();


                $fresh_scheduled =
                    $result->fetch_assoc();


                $stmt->close();


                if ($fresh_scheduled) {

                    $error =
                        "You already have a subscription scheduled " .
                        "to start on " .
                        formatDate(
                            $fresh_scheduled[
                                "start_date"
                            ]
                        ) .
                        ".";

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RE-CHECK MEMBER LIMIT
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $selected_plan["member_limit"] !== null
    ) {

        $member_limit =
            (int)
            $selected_plan[
                "member_limit"
            ];


        if (
            $total_members >
            $member_limit
        ) {

            $error =
                "Your gym currently has " .
                number_format(
                    $total_members
                ) .
                " members, but the " .
                $selected_plan[
                    "plan_name"
                ] .
                " plan supports only " .
                number_format(
                    $member_limit
                ) .
                " members.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RECALCULATE DATES
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        if (
            $payment_type === "new"
        ) {

            $proposed_start_date =
                $today;

        }
        elseif (
            $payment_type === "renew" ||
            $payment_type === "change"
        ) {

            if (!$current_subscription) {

                $error =
                    "Your current subscription could not be found.";

            }
            else {

                try {

                    $new_start =
                        new DateTime(
                            $current_subscription[
                                "end_date"
                            ]
                        );


                    $new_start->modify(
                        "+1 day"
                    );


                    $proposed_start_date =
                        $new_start->format(
                            "Y-m-d"
                        );

                }
                catch (Exception $e) {

                    $error =
                        "Unable to calculate the subscription start date.";

                }

            }

        }


        if (
            $error === "" &&
            $proposed_start_date
        ) {

            $proposed_end_date =
                calculateMonthlyEndDate(
                    $proposed_start_date
                );


            if (!$proposed_end_date) {

                $error =
                    "Unable to calculate the subscription end date.";

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT RECORD
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $conn->begin_transaction();


        try {

            /*
            |------------------------------------------------------------------
            | LOCK OWNER'S RELEVANT SUBSCRIPTION STATE
            |------------------------------------------------------------------
            */

            $sql = "
                SELECT

                    subscription_id,
                    subscription_plan_id,
                    start_date,
                    end_date,
                    status

                FROM gym_owner_subscriptions

                WHERE owner_id = ?

                AND status IN (
                    'active',
                    'scheduled'
                )

                ORDER BY
                    start_date ASC,
                    subscription_id ASC

                FOR UPDATE
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    "Unable to lock subscription state."
                );

            }


            $stmt->bind_param(
                "i",
                $owner_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to verify subscription state."
                );

            }


            $result =
                $stmt->get_result();


            $locked_active = null;

            $locked_scheduled = null;


            while (
                $row =
                $result->fetch_assoc()
            ) {

                if (
                    $row["status"] ===
                    "active"
                ) {

                    /*
                    | Only consider an actually current
                    | active subscription.
                    */

                    if (
                        $row["start_date"] <=
                        $today &&
                        $row["end_date"] >=
                        $today
                    ) {

                        if (
                            $locked_active === null
                        ) {

                            $locked_active =
                                $row;

                        }

                    }

                }
                elseif (
                    $row["status"] ===
                    "scheduled"
                ) {

                    if (
                        $row["start_date"] >
                        $today
                    ) {

                        if (
                            $locked_scheduled === null
                        ) {

                            $locked_scheduled =
                                $row;

                        }

                    }

                }

            }


            $stmt->close();


            /*
            |------------------------------------------------------------------
            | No duplicate future subscription.
            |------------------------------------------------------------------
            */

            if (
                $locked_scheduled
            ) {

                throw new Exception(
                    "You already have a subscription scheduled to start on " .
                    formatDate(
                        $locked_scheduled[
                            "start_date"
                        ]
                    ) .
                    "."
                );

            }


            /*
            |------------------------------------------------------------------
            | Recalculate payment type from locked data.
            |------------------------------------------------------------------
            */

            $locked_payment_type =
                "new";


            $locked_subscription_id =
                null;


            if (
                $locked_active
            ) {

                $locked_subscription_id =
                    (int)
                    $locked_active[
                        "subscription_id"
                    ];


                if (
                    (int)
                    $locked_active[
                        "subscription_plan_id"
                    ]
                    ===
                    $plan_id
                ) {

                    $locked_payment_type =
                        "renew";

                }
                else {

                    $locked_payment_type =
                        "change";

                }

            }


            /*
            |------------------------------------------------------------------
            | Make sure the browser's intended operation still matches.
            |------------------------------------------------------------------
            */

            if (
                $locked_payment_type !==
                $payment_type
            ) {

                throw new Exception(
                    "Your subscription status changed. " .
                    "Please reload the payment page and try again."
                );

            }


            $subscription_id =
                $locked_subscription_id;


            /*
            |------------------------------------------------------------------
            | PREVENT DUPLICATE PAYMENT
            |------------------------------------------------------------------
            |
            | Existing pending/submitted payment for the same owner/plan
            | is reused instead of creating another one.
            |
            */

            $sql = "
                SELECT

                    payment_id,
                    transaction_reference,
                    payment_status

                FROM owner_subscription_payments

                WHERE owner_id = ?

                AND subscription_plan_id = ?

                AND payment_status IN (
                    'pending',
                    'submitted'
                )

                ORDER BY
                    payment_id DESC

                LIMIT 1

                FOR UPDATE
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    "Unable to check existing payment."
                );

            }


            $stmt->bind_param(
                "ii",
                $owner_id,
                $plan_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to check existing payment."
                );

            }


            $result =
                $stmt->get_result();


            $existing_payment =
                $result->fetch_assoc();


            $stmt->close();


            /*
            |------------------------------------------------------------------
            | REUSE EXISTING PAYMENT
            |------------------------------------------------------------------
            */

            if (
                $existing_payment
            ) {

                $payment_id =
                    (int)
                    $existing_payment[
                        "payment_id"
                    ];


                $transaction_reference =
                    $existing_payment[
                        "transaction_reference"
                    ];


                $conn->commit();

            }
            else {

                /*
                |--------------------------------------------------------------
                | Generate unique internal reference.
                |--------------------------------------------------------------
                */

                try {

                    $random_part =
                        strtoupper(
                            bin2hex(
                                random_bytes(6)
                            )
                        );

                }
                catch (Exception $e) {

                    $random_part =
                        strtoupper(
                            uniqid()
                        );

                }


                $transaction_reference =
                    "SUBPAY-" .
                    date("YmdHis") .
                    "-" .
                    $random_part;


                /*
                |--------------------------------------------------------------
                | Payment amount comes ONLY from DB.
                |--------------------------------------------------------------
                */

                $amount =
                    (float)
                    $selected_plan[
                        "price"
                    ];


                /*
                |--------------------------------------------------------------
                | Insert payment.
                |--------------------------------------------------------------
                */

                $sql = "
                    INSERT INTO owner_subscription_payments
                    (
                        owner_id,
                        subscription_plan_id,
                        subscription_id,
                        amount,
                        payment_method,
                        payment_status,
                        transaction_reference,
                        created_at
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'jazzcash',
                        'pending',
                        ?,
                        NOW()
                    )
                ";


                $stmt =
                    $conn->prepare($sql);


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare payment record."
                    );

                }


                /*
                | subscription_id can be NULL.
                |
                | bind_param accepts NULL for the integer parameter.
                */

                $stmt->bind_param(
                    "iiids",
                    $owner_id,
                    $plan_id,
                    $subscription_id,
                    $amount,
                    $transaction_reference
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Unable to create payment record."
                    );

                }


                $payment_id =
                    (int)
                    $stmt->insert_id;


                $stmt->close();


                $conn->commit();

            }

        }
        catch (Exception $e) {

            $conn->rollback();


            $payment_id =
                null;


            $transaction_reference =
                null;


            $error =
                $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| JAZZCASH / RAAST QR IMAGE
|--------------------------------------------------------------------------
*/

$qr_image = "";

$plan_name = strtolower(
    trim($selected_plan["plan_name"])
);

if (strpos($plan_name, "basic") !== false) {

    $qr_image = "images/basic-qr.png";

}
elseif (strpos($plan_name, "standard") !== false) {

    $qr_image = "images/pro-qr.jfif";

}
elseif (strpos($plan_name, "premium") !== false) {

    $qr_image = "images/ultra-qr.jfif";

}

/*
|--------------------------------------------------------------------------
| PAYMENT TYPE LABEL
|--------------------------------------------------------------------------
*/

$payment_type_label =
    "New Subscription";


if (
    $payment_type === "change"
) {

    $payment_type_label =
        "Plan Change";

}
elseif (
    $payment_type === "renew"
) {

    $payment_type_label =
        "Subscription Renewal";

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
        Subscription Payment
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

            max-width: 900px;

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

            font-size: 30px;

        }


        .header p {

            margin: 6px 0 0;

            color: #6b7280;

        }


        .back {

            display: inline-block;

            padding: 11px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;

        }


        .card {

            background: white;

            border-radius: 14px;

            padding: 30px;

            box-shadow:
                0 4px 18px
                rgba(0,0,0,.06);

            margin-bottom: 25px;

        }


        .card h2 {

            margin-top: 0;

        }


        .plan-summary {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin-top: 20px;

        }


        .summary-box {

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            padding: 20px;

            border-radius: 10px;

        }


        .label {

            color: #6b7280;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 7px;

        }


        .value {

            font-size: 20px;

            font-weight: bold;

        }


        .price {

            font-size: 30px;

            font-weight: bold;

            color: #111827;

        }


        .payment-box {

            text-align: center;

            border:
                2px solid #2563eb;

            background: #eff6ff;

            border-radius: 14px;

            padding: 30px;

            margin-top: 25px;

        }


        .payment-box h2 {

            margin-top: 0;

        }


        .qr {

            width: 280px;

            height: 280px;

            object-fit: contain;

            background: white;

            padding: 12px;

            border-radius: 12px;

            border:
                1px solid #d1d5db;

            margin: 20px auto;

            display: block;

        }


        .qr-placeholder {

            width: 280px;

            height: 280px;

            margin: 20px auto;

            background: white;

            border:
                2px dashed #9ca3af;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

            color: #6b7280;

            text-align: center;

            line-height: 1.5;

        }


        .amount {

            font-size: 34px;

            font-weight: bold;

            margin: 10px 0;

        }


        .instruction {

            background: white;

            padding: 18px;

            border-radius: 10px;

            margin-top: 20px;

            text-align: left;

            line-height: 1.6;

        }


        .reference-box {

            margin-top: 25px;

            text-align: left;

        }


        .reference-box label {

            display: block;

            font-weight: bold;

            margin-bottom: 8px;

        }


        .reference-box input {

            width: 100%;

            padding: 13px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

        }


        .button {

            display: inline-block;

            padding: 13px 22px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            cursor: pointer;

            font-size: 15px;

        }


        .button-primary {

            background: #2563eb;

            color: white;

        }


        .button-gray {

            background: #e5e7eb;

            color: #374151;

        }


        .actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 25px;

        }


        .notice {

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.5;

        }


        .notice-error {

            background: #fee2e2;

            border:
                1px solid #fecaca;

            color: #991b1b;

        }


        .notice-warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

        }


        .notice-info {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            color: #1e40af;

        }


        .pending {

            display: inline-block;

            background: #fef3c7;

            color: #92400e;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

        }


        .reference {

            display: inline-block;

            padding: 10px 14px;

            background: #f3f4f6;

            border-radius: 8px;

            font-family:
                Consolas,
                monospace;

            word-break: break-all;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .plan-summary {

                grid-template-columns: 1fr;

            }


            .qr,
            .qr-placeholder {

                width: 230px;

                height: 230px;

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
                Subscription Payment
            </h1>

            <p>
                Complete your payment using JazzCash / Raast.
            </p>

        </div>


        <a
            href="my_subscription.php"
            class="back"
        >
            ← My Subscription
        </a>

    </div>



    <!-- ERROR -->

    <?php if (
        $error !== ""
    ): ?>


        <div class="card">

            <div class="notice notice-error">

                <strong>
                    Payment cannot be created
                </strong>

                <br><br>

                <?php
                echo e($error);
                ?>

            </div>


            <div class="actions">

                <a
                    href="my_subscription.php"
                    class="button button-gray"
                >
                    ← Back to My Subscription
                </a>

            </div>

        </div>


    <?php elseif (
        $payment_id === null
    ): ?>


        <!-- PAYMENT REVIEW -->

        <div class="card">

            <h2>
                Review Payment
            </h2>


            <p>

                Please review the subscription information
                before creating your payment request.

            </p>


            <div class="plan-summary">


                <div class="summary-box">

                    <div class="label">
                        PAYMENT TYPE
                    </div>

                    <div class="value">

                        <?php
                        echo e(
                            $payment_type_label
                        );
                        ?>

                    </div>

                </div>


                <div class="summary-box">

                    <div class="label">
                        PLAN
                    </div>

                    <div class="value">

                        <?php
                        echo e(
                            $selected_plan[
                                "plan_name"
                            ]
                        );
                        ?>

                    </div>

                </div>


                <div class="summary-box">

                    <div class="label">
                        AMOUNT
                    </div>

                    <div class="price">

                        Rs.

                        <?php
                        echo formatPrice(
                            $selected_plan[
                                "price"
                            ]
                        );
                        ?>

                    </div>

                </div>


                <div class="summary-box">

                    <div class="label">
                        PAYMENT METHOD
                    </div>

                    <div class="value">

                        JazzCash / Raast QR

                    </div>

                </div>


            </div>



            <?php if (
                $proposed_start_date &&
                $proposed_end_date
            ): ?>


                <div class="notice notice-info">

                    <strong>
                        Proposed subscription period
                    </strong>

                    <br><br>

                    Start:

                    <strong>

                        <?php
                        echo e(
                            formatDate(
                                $proposed_start_date
                            )
                        );
                        ?>

                    </strong>


                    <br>


                    End:

                    <strong>

                        <?php
                        echo e(
                            formatDate(
                                $proposed_end_date
                            )
                        );
                        ?>

                    </strong>

                </div>


            <?php endif; ?>



            <?php if (
                $current_subscription &&
                $payment_type === "change"
            ): ?>


                <div class="notice notice-warning">

                    Your current

                    <strong>

                        <?php
                        echo e(
                            $current_subscription[
                                "plan_name"
                            ]
                        );
                        ?>

                    </strong>

                    subscription remains active until

                    <strong>

                        <?php
                        echo e(
                            formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            )
                        );
                        ?>

                    </strong>.

                    <br><br>

                    The new

                    <strong>

                        <?php
                        echo e(
                            $selected_plan[
                                "plan_name"
                            ]
                        );
                        ?>

                    </strong>

                    plan will start afterward.

                </div>


            <?php elseif (
                $current_subscription &&
                $payment_type === "renew"
            ): ?>


                <div class="notice notice-info">

                    Your current subscription remains active
                    until

                    <strong>

                        <?php
                        echo e(
                            formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            )
                        );
                        ?>

                    </strong>.

                    <br><br>

                    The renewed subscription will begin
                    afterward.

                </div>


            <?php endif; ?>



            <form
                method="POST"
                action="
                payment_checkout.php?plan_id=<?php
                echo $plan_id;
                ?>"
            >

                <input
                    type="hidden"
                    name="plan_id"
                    value="<?php
                    echo $plan_id;
                    ?>"
                >


                <div class="actions">

                    <button
                        type="submit"
                        name="create_payment"
                        value="1"
                        class="
                        button
                        button-primary
                        "
                    >
                        Continue to Payment
                    </button>


                    <a
                        href="my_subscription.php"
                        class="
                        button
                        button-gray
                        "
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>


    <?php else: ?>


        <!-- PAYMENT CREATED -->

        <div class="card">

            <h2>
                Payment Request Created
            </h2>


            <p>

                Your payment request has been created.
                Please pay the exact amount using the
                JazzCash / Raast QR below.

            </p>


            <div class="payment-box">


                <h2>
                    Pay with JazzCash / Raast
                </h2>


                <div class="label">
                    AMOUNT TO PAY
                </div>


                <div class="amount">

                    Rs.

                    <?php
                    echo formatPrice(
                        $selected_plan[
                            "price"
                        ]
                    );
                    ?>

                </div>


                <span class="pending">
                    PAYMENT PENDING
                </span>



                <?php if (
                    file_exists($qr_image)
                ): ?>


                    <img
                        src="<?php
                        echo e($qr_image);
                        ?>"
                        alt="JazzCash Raast Merchant QR"
                        class="qr"
                    >


                <?php else: ?>


                    <div class="qr-placeholder">

                        <div>

                            Your JazzCash / Raast
                            merchant QR will appear here.

                            <br><br>

                            Place your QR image at:

                            <br><br>

                            <strong>
                                images/jazzcash-raast-qr.png
                            </strong>

                        </div>

                    </div>


                <?php endif; ?>



                <div class="instruction">

                    <strong>
                        Payment Instructions
                    </strong>


                    <ol>

                        <li>
                            Open JazzCash or your
                            supported banking app.
                        </li>


                        <li>
                            Scan the merchant
                            Raast QR code.
                        </li>


                        <li>

                            Pay exactly

                            <strong>

                                Rs.

                                <?php
                                echo formatPrice(
                                    $selected_plan[
                                        "price"
                                    ]
                                );
                                ?>

                            </strong>.

                        </li>


                        <li>
                            Complete the payment.
                        </li>


                        <li>
                            Keep the transaction
                            reference number.
                        </li>

                    </ol>

                </div>



                <!-- PAYMENT SUBMISSION -->

                <div class="reference-box">


                    <form
                        method="POST"
                        action="payment_submit.php"
                    >


                        <input
                            type="hidden"
                            name="payment_id"
                            value="<?php
                            echo (int)
                                $payment_id;
                            ?>"
                        >


                        <label
                            for="gateway_transaction_id"
                        >

                            JazzCash / Raast
                            Transaction Reference

                        </label>


                        <input
                            type="text"
                            id="gateway_transaction_id"
                            name="gateway_transaction_id"
                            maxlength="150"
                            required
                            autocomplete="off"
                            placeholder="
                            Enter the transaction/reference number
                            "
                        >


                        <div class="actions">

                            <button
                                type="submit"
                                class="
                                button
                                button-primary
                                "
                            >
                                I Have Completed Payment
                            </button>


                            <a
                                href="my_subscription.php"
                                class="
                                button
                                button-gray
                                "
                            >
                                Cancel
                            </a>

                        </div>


                    </form>


                </div>


            </div>


            <!-- INTERNAL REFERENCE -->

            <div class="notice notice-info">

                <strong>
                    Payment Reference:
                </strong>

                <br><br>

                <span class="reference">

                    <?php
                    echo e(
                        $transaction_reference
                    );
                    ?>

                </span>


                <br><br>

                Keep this reference for your records.

                Your subscription will

                <strong>
                    NOT
                </strong>

                be activated until the payment
                has been verified.

            </div>


        </div>


    <?php endif; ?>


</div>


</body>

</html>