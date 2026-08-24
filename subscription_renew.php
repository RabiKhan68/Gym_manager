<?php

session_start();

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


/*
|--------------------------------------------------------------------------
| CALCULATE MONTHLY END DATE
|--------------------------------------------------------------------------
|
| Examples:
|
| 01 Aug -> 31 Aug
| 24 Aug -> 23 Sep
| 24 Sep -> 23 Oct
| 31 Jan -> 28/29 Feb
|
| The subscription period is based on the actual start date.
|
*/

function calculateMonthlyEndDate($start_date)
{
    try {

        $start =
            new DateTime($start_date);


        $original_day =
            (int)
            $start->format("d");


        /*
        |--------------------------------------------------------------------------
        | Move to first day of next month
        |--------------------------------------------------------------------------
        */

        $next_month =
            new DateTime(
                $start->format("Y-m-01")
            );


        $next_month->modify(
            "+1 month"
        );


        /*
        |--------------------------------------------------------------------------
        | Number of days in target month
        |--------------------------------------------------------------------------
        */

        $days_in_target_month =
            (int)
            $next_month->format("t");


        /*
        |--------------------------------------------------------------------------
        | Preserve starting day where possible
        |--------------------------------------------------------------------------
        */

        $target_day =
            min(
                $original_day,
                $days_in_target_month
            );


        /*
        |--------------------------------------------------------------------------
        | Construct same calendar day in next month
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | End one day before next month's same day
        |--------------------------------------------------------------------------
        */

        $target_date->modify(
            "-1 day"
        );


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
| ERROR
|--------------------------------------------------------------------------
*/

$error = "";


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Only a genuinely active subscription is renewable.
|
*/

$current_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,
        s.created_at,

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
| NO ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Renewal only applies to an existing active subscription.
|
*/

if (!$current_subscription) {

    header(
        "Location: subscription_plans.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET UPCOMING SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
|
| A future scheduled subscription means the owner already has
| a pending plan change or renewal.
|
*/

$scheduled_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,
        s.created_at,

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


$scheduled_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET CURRENT MEMBER COUNT
|--------------------------------------------------------------------------
*/

$total_members = 0;


$sql = "
    SELECT COUNT(*) AS total

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
| CALCULATE RENEWAL DATES
|--------------------------------------------------------------------------
|
| Example:
|
| Current:
| 24 Aug -> 23 Sep
|
| Renewal:
| 24 Sep -> 23 Oct
|
*/

$renewal_start_date = null;

$renewal_end_date = null;


try {

    $renewal_start =
        new DateTime(
            $current_subscription["end_date"]
        );


    $renewal_start->modify(
        "+1 day"
    );


    $renewal_start_date =
        $renewal_start->format(
            "Y-m-d"
        );


    $renewal_end_date =
        calculateMonthlyEndDate(
            $renewal_start_date
        );

}
catch (Exception $e) {

    $error =
        "Unable to calculate the renewal dates.";

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT VALIDATION
|--------------------------------------------------------------------------
|
| Renewal uses the same plan.
|
*/

$limit_error = "";


if (
    $current_subscription["member_limit"] !== null
) {

    $member_limit =
        (int)
        $current_subscription[
            "member_limit"
        ];


    if (
        $total_members >
        $member_limit
    ) {

        $limit_error =
            "Your gym currently has " .
            number_format($total_members) .
            " members, but your " .
            $current_subscription["plan_name"] .
            " plan supports only " .
            number_format($member_limit) .
            " members.";

    }

}


/*
|--------------------------------------------------------------------------
| HANDLE POST
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| This file DOES NOT create a subscription.
|
| It sends the owner to payment_checkout.php.
|
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Submitted Subscription ID
    |--------------------------------------------------------------------------
    */

    $submitted_subscription_id =
        isset($_POST["subscription_id"])
        ? (int)
        $_POST["subscription_id"]
        : 0;


    if (
        $submitted_subscription_id <= 0
    ) {

        $error =
            "Invalid renewal request.";

    }


    elseif (
        $submitted_subscription_id !==
        (int)
        $current_subscription[
            "subscription_id"
        ]
    ) {

        $error =
            "Invalid subscription request.";

    }


    /*
    |--------------------------------------------------------------------------
    | Existing Scheduled Subscription
    |--------------------------------------------------------------------------
    */

    elseif ($scheduled_subscription) {

        $error =
            "You already have a subscription scheduled for " .
            formatDate(
                $scheduled_subscription["start_date"]
            ) .
            ".";

    }


    /*
    |--------------------------------------------------------------------------
    | MEMBER LIMIT
    |--------------------------------------------------------------------------
    */

    elseif ($limit_error !== "") {

        $error =
            $limit_error;

    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        !$renewal_start_date ||
        !$renewal_end_date
    ) {

        $error =
            "Unable to calculate the renewal dates.";

    }


    /*
    |--------------------------------------------------------------------------
    | SEND TO PAYMENT CHECKOUT
    |--------------------------------------------------------------------------
    |
    | DO NOT create gym_owner_subscriptions here.
    |
    | Payment must be completed first.
    |
    */

    else {

        /*
        |--------------------------------------------------------------------------
        | Store Renewal Intent in Session
        |--------------------------------------------------------------------------
        |
        | payment_checkout.php should verify these values against
        | the database again.
        |
        */

        $_SESSION[
            "pending_subscription_plan_id"
        ] =
            (int)
            $current_subscription[
                "subscription_plan_id"
            ];


        $_SESSION[
            "pending_subscription_start_date"
        ] =
            $renewal_start_date;


        $_SESSION[
            "pending_subscription_end_date"
        ] =
            $renewal_end_date;


        $_SESSION[
            "pending_subscription_type"
        ] =
            "renewal";


        $_SESSION[
            "pending_subscription_id"
        ] =
            (int)
            $current_subscription[
                "subscription_id"
            ];


        /*
        |--------------------------------------------------------------------------
        | Redirect to Payment
        |--------------------------------------------------------------------------
        |
        | renewal=1 allows payment_checkout.php to know that this
        | payment is for a renewal rather than a new plan/change.
        |
        */

        header(
            "Location: payment_checkout.php?plan_id=" .
            (int)
            $current_subscription[
                "subscription_plan_id"
            ] .
            "&renewal=1"
        );

        exit();

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
        Renew Subscription
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

            max-width: 850px;

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


        .back:hover {

            opacity: .85;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 14px;

            box-shadow:
                0 4px 18px
                rgba(0,0,0,.06);

        }


        .plan {

            border:
                2px solid #16a34a;

            background: #f0fdf4;

            border-radius: 12px;

            padding: 25px;

            margin-top: 25px;

        }


        .label {

            color: #166534;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan h2 {

            margin: 0 0 12px;

            font-size: 28px;

        }


        .price {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .price span {

            font-size: 14px;

            color: #6b7280;

            font-weight: normal;

        }


        .details {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 12px;

            margin-top: 20px;

        }


        .detail {

            background: white;

            padding: 15px;

            border-radius: 9px;

            border:
                1px solid #e5e7eb;

        }


        .detail-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .detail-value {

            font-weight: bold;

        }


        .notice {

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.6;

        }


        .notice-warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

        }


        .notice-error {

            background: #fee2e2;

            border:
                1px solid #fecaca;

            color: #991b1b;

        }


        .notice-success {

            background: #dcfce7;

            border:
                1px solid #bbf7d0;

            color: #166534;

        }


        .payment-notice {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            color: #1e40af;

            padding: 18px;

            border-radius: 10px;

            margin-top: 20px;

            line-height: 1.6;

        }


        .member-info {

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.6;

        }


        .actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 25px;

        }


        .button {

            display: inline-block;

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            cursor: pointer;

            font-size: 15px;

        }


        .button-primary {

            background: #16a34a;

            color: white;

        }


        .button-cancel {

            background: #e5e7eb;

            color: #374151;

        }


        .button:hover {

            opacity: .85;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .details {

                grid-template-columns: 1fr;

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
                Renew Subscription
            </h1>

            <p>
                Review your renewal before payment.
            </p>

        </div>


        <a
            href="my_subscription.php"
            class="back"
        >
            ← My Subscription
        </a>

    </div>



    <?php if ($error !== ""): ?>


        <!-- ERROR -->

        <div class="card">

            <div class="notice notice-error">

                <strong>
                    Unable to continue
                </strong>

                <br><br>

                <?php

                echo e($error);

                ?>

            </div>


            <div class="actions">

                <a
                    href="my_subscription.php"
                    class="button button-cancel"
                >
                    ← Back to My Subscription
                </a>

            </div>

        </div>


    <?php else: ?>


        <div class="card">


            <h2>
                Review Renewal
            </h2>


            <p>

                Your current subscription will remain
                active until its existing expiry date.

                The renewal will start immediately after
                the current subscription ends.

            </p>



            <!-- RENEWAL PLAN -->

            <div class="plan">


                <div class="label">
                    RENEWING PLAN
                </div>


                <h2>

                    <?php

                    echo e(
                        $current_subscription[
                            "plan_name"
                        ]
                    );

                    ?>

                </h2>


                <div class="price">

                    Rs.

                    <?php

                    echo number_format(
                        (float)
                        $current_subscription[
                            "price"
                        ],
                        2
                    );

                    ?>

                    <span>
                        / month
                    </span>

                </div>


                <div class="details">


                    <div class="detail">

                        <div class="detail-label">
                            Current Subscription Ends
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Renewal Starts
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $renewal_start_date
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Renewal Ends
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $renewal_end_date
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Member Limit
                        </div>

                        <div class="detail-value">

                            <?php

                            if (
                                $current_subscription[
                                    "member_limit"
                                ] !== null
                            ) {

                                echo number_format(
                                    (int)
                                    $current_subscription[
                                        "member_limit"
                                    ]
                                );

                            }
                            else {

                                echo "Unlimited";

                            }

                            ?>

                        </div>

                    </div>


                </div>


            </div>



            <!-- CURRENT SUBSCRIPTION NOTICE -->

            <div class="notice notice-warning">

                <strong>
                    Your current subscription will not be
                    changed immediately.
                </strong>

                <br><br>

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

                plan remains active until

                <strong>

                    <?php

                    echo formatDate(
                        $current_subscription[
                            "end_date"
                        ]
                    );

                    ?>

                </strong>.

                <br><br>

                The renewed subscription will start on

                <strong>

                    <?php

                    echo formatDate(
                        $renewal_start_date
                    );

                    ?>

                </strong>

                after successful payment.

                <br><br>

                You will not lose any remaining time from
                your current subscription.

            </div>



            <!-- MEMBER USAGE -->

            <div class="member-info">

                <strong>
                    Member Usage
                </strong>

                <br><br>

                Your gym currently has

                <strong>

                    <?php

                    echo number_format(
                        $total_members
                    );

                    ?>

                </strong>

                members.

                <br><br>


                <?php if (
                    $current_subscription[
                        "member_limit"
                    ] !== null
                ): ?>


                    The renewed plan allows

                    <strong>

                        <?php

                        echo number_format(
                            (int)
                            $current_subscription[
                                "member_limit"
                            ]
                        );

                        ?>

                    </strong>

                    members.


                <?php else: ?>


                    The renewed plan allows

                    <strong>
                        unlimited members
                    </strong>.


                <?php endif; ?>


            </div>



            <!-- MEMBER LIMIT ERROR -->

            <?php if ($limit_error !== ""): ?>


                <div class="notice notice-error">

                    <strong>
                        Renewal cannot be completed.
                    </strong>

                    <br><br>

                    <?php

                    echo e(
                        $limit_error
                    );

                    ?>

                    <br><br>

                    Please reduce your gym's member count
                    before renewing this plan.

                </div>


            <!-- EXISTING SCHEDULE -->

            <?php elseif ($scheduled_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        A subscription is already scheduled.
                    </strong>

                    <br><br>

                    You already have

                    <strong>

                        <?php

                        echo e(
                            $scheduled_subscription[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    scheduled to start on

                    <strong>

                        <?php

                        echo formatDate(
                            $scheduled_subscription[
                                "start_date"
                            ]
                        );

                        ?>

                    </strong>.

                    <br><br>

                    You cannot create another renewal while
                    a future subscription is already
                    scheduled.

                </div>


            <!-- PAYMENT -->

            <?php else: ?>


                <div class="payment-notice">

                    <strong>
                        Payment required
                    </strong>

                    <br><br>

                    Your renewal has not been created yet.

                    You will be taken to the payment checkout
                    to complete payment for:

                    <br><br>

                    <strong>

                        <?php

                        echo e(
                            $current_subscription[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    —

                    <strong>

                        Rs.

                        <?php

                        echo number_format(
                            (float)
                            $current_subscription[
                                "price"
                            ],
                            2
                        );

                        ?>

                    </strong>

                    <br><br>

                    The renewal will only be created after
                    the payment is successfully verified.

                </div>



                <!-- CONFIRM FORM -->

                <form
                    method="POST"
                    action="subscription_renew.php"
                    onsubmit="return confirmRenewal();"
                >

                    <input
                        type="hidden"
                        name="subscription_id"
                        value="<?php

                        echo (int)
                            $current_subscription[
                                "subscription_id"
                            ];

                        ?>"
                    >


                    <div class="actions">


                        <button
                            type="submit"
                            class="button button-primary"
                        >
                            Proceed to Payment
                        </button>


                        <a
                            href="my_subscription.php"
                            class="button button-cancel"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>



<script>

function confirmRenewal()
{
    return confirm(
        "Continue to payment for your " +

        <?php
        echo json_encode(
            $current_subscription["plan_name"]
        );
        ?> +

        " renewal?\n\n" +

        "Amount: Rs. " +

        <?php
        echo json_encode(
            number_format(
                (float)
                $current_subscription["price"],
                2
            )
        );
        ?> +

        "\n\nCurrent subscription ends: " +

        <?php
        echo json_encode(
            formatDate(
                $current_subscription["end_date"]
            )
        );
        ?> +

        "\nRenewal starts: " +

        <?php
        echo json_encode(
            formatDate(
                $renewal_start_date
            )
        );
        ?> +

        "\n\nThe renewal will only be created " +
        "after successful payment."
    );
}

</script>


</body>

</html>