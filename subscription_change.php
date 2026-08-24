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


/*
|--------------------------------------------------------------------------
| CALCULATE MONTHLY END DATE
|--------------------------------------------------------------------------
|
| The subscription lasts one calendar month from its start date.
|
| Examples:
|
| 24 Aug 2026 -> 23 Sep 2026
| 15 Jan 2027 -> 14 Feb 2027
| 01 Aug 2026 -> 31 Aug 2026
|
| The important point is that the subscription does NOT automatically
| start from the 1st day of a month.
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
        |
        | Example:
        |
        | 24 Aug -> 24 Sep
        |
        | 31 Jan -> 28 Feb / 29 Feb
        |
        */

        $target_day =
            min(
                $original_day,
                $days_in_target_month
            );


        /*
        |--------------------------------------------------------------------------
        | Construct target date
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
| GET PLAN ID
|--------------------------------------------------------------------------
*/

$plan_id =
    isset($_GET["plan_id"])
    ? (int) $_GET["plan_id"]
    : 0;


if ($plan_id <= 0) {

    header(
        "Location: subscription_plans.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET SELECTED PLAN
|--------------------------------------------------------------------------
|
| NEVER trust price/member_limit from the browser.
| Always load the plan from the database.
|
*/

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
        "Unable to load subscription plan."
    );

}


$result =
    $stmt->get_result();


$new_plan =
    $result->fetch_assoc();


$stmt->close();


if (!$new_plan) {

    die(
        "The selected subscription plan does not exist."
    );

}


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| A subscription is current only if:
|
| active
| start_date <= today
| end_date >= today
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
| GET UPCOMING SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
|
| If an owner already has a future scheduled subscription,
| another plan change must not be created.
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
| CALCULATE PROPOSED SUBSCRIPTION DATES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| These dates are only DISPLAYED here.
|
| We DO NOT insert a subscription yet.
|
| The actual subscription will be created only after payment
| has been successfully verified.
|
*/

$new_start_date = null;

$new_end_date = null;


if ($current_subscription) {

    try {

        $new_start =
            new DateTime(
                $current_subscription["end_date"]
            );


        $new_start->modify(
            "+1 day"
        );


        $new_start_date =
            $new_start->format(
                "Y-m-d"
            );

    }
    catch (Exception $e) {

        $error =
            "Unable to calculate the new subscription start date.";

    }

}
else {

    /*
    |--------------------------------------------------------------------------
    | FIRST SUBSCRIPTION
    |--------------------------------------------------------------------------
    |
    | First subscription begins today after successful payment.
    |
    */

    $new_start_date =
        $today;

}


if ($new_start_date !== null) {

    $new_end_date =
        calculateMonthlyEndDate(
            $new_start_date
        );

}


/*
|--------------------------------------------------------------------------
| SAME PLAN CHECK
|--------------------------------------------------------------------------
*/

$is_same_plan = false;


if ($current_subscription) {

    $is_same_plan =
        (
            (int)
            $current_subscription[
                "subscription_plan_id"
            ]
            ===
            $plan_id
        );

}


/*
|--------------------------------------------------------------------------
| ALREADY SCHEDULED CHECK
|--------------------------------------------------------------------------
*/

$is_already_scheduled = false;


if ($scheduled_subscription) {

    $is_already_scheduled =
        (
            (int)
            $scheduled_subscription[
                "subscription_plan_id"
            ]
            ===
            $plan_id
        );

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT VALIDATION
|--------------------------------------------------------------------------
*/

$limit_error = "";


if (
    $new_plan["member_limit"] !== null
) {

    $new_member_limit =
        (int)
        $new_plan["member_limit"];


    if (
        $total_members >
        $new_member_limit
    ) {

        $limit_error =
            "Your gym currently has " .
            number_format($total_members) .
            " members, but the " .
            $new_plan["plan_name"] .
            " plan supports only " .
            number_format($new_member_limit) .
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
| This POST does NOT create a subscription.
|
| It only validates the request and redirects the owner
| to payment_checkout.php.
|
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Plan ID
    |--------------------------------------------------------------------------
    */

    $submitted_plan_id =
        isset($_POST["plan_id"])
        ? (int)
        $_POST["plan_id"]
        : 0;


    if (
        $submitted_plan_id <= 0 ||
        $submitted_plan_id !== $plan_id
    ) {

        $error =
            "Invalid subscription plan.";

    }


    /*
    |--------------------------------------------------------------------------
    | Same Plan
    |--------------------------------------------------------------------------
    */

    elseif ($is_same_plan) {

        $error =
            "You are already using the " .
            $current_subscription["plan_name"] .
            " plan.";

    }


    /*
    |--------------------------------------------------------------------------
    | Existing Scheduled Subscription
    |--------------------------------------------------------------------------
    */

    elseif ($scheduled_subscription) {

        if ($is_already_scheduled) {

            $error =
                "The " .
                $scheduled_subscription["plan_name"] .
                " plan is already scheduled to start on " .
                formatDate(
                    $scheduled_subscription["start_date"]
                ) .
                ".";

        }
        else {

            $error =
                "You already have a subscription change scheduled for " .
                formatDate(
                    $scheduled_subscription["start_date"]
                ) .
                ".";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Member Limit
    |--------------------------------------------------------------------------
    */

    elseif ($limit_error !== "") {

        $error =
            $limit_error;

    }


    /*
    |--------------------------------------------------------------------------
    | Date Validation
    |--------------------------------------------------------------------------
    */

    elseif (
        !$new_start_date ||
        !$new_end_date
    ) {

        $error =
            "Unable to calculate the subscription dates.";

    }


    /*
    |--------------------------------------------------------------------------
    | SEND OWNER TO PAYMENT
    |--------------------------------------------------------------------------
    |
    | NO SUBSCRIPTION IS CREATED HERE.
    |
    */

    else {

        /*
        |--------------------------------------------------------------------------
        | Redirect to Payment Checkout
        |--------------------------------------------------------------------------
        */

        header(
            "Location: payment_checkout.php?plan_id=" .
            $plan_id
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
        Change Subscription
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

            max-width: 950px;

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


        .comparison {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin-top: 25px;

        }


        .plan {

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

        }


        .plan.current {

            background: #f9fafb;

        }


        .plan.new {

            border:
                2px solid #2563eb;

            background: #eff6ff;

        }


        .label {

            color: #6b7280;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan h2 {

            margin: 0 0 12px;

            font-size: 27px;

        }


        .price {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 18px;

        }


        .price span {

            font-size: 14px;

            color: #6b7280;

            font-weight: normal;

        }


        .feature {

            padding: 11px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .feature:last-child {

            border-bottom: none;

        }


        .notice {

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.5;

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

            margin-top: 25px;

            flex-wrap: wrap;

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

            background: #2563eb;

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


            .comparison {

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
                Change Subscription
            </h1>

            <p>
                Review your subscription before payment.
            </p>

        </div>


        <a
            href="subscription_plans.php"
            class="back"
        >
            ← Subscription Plans
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
                    href="subscription_plans.php"
                    class="button button-cancel"
                >
                    ← Back to Plans
                </a>

            </div>

        </div>


    <?php else: ?>


        <div class="card">


            <h2>
                Review Plan
            </h2>


            <?php if ($current_subscription): ?>


                <p>

                    Your current subscription will remain
                    active until its expiry date.

                    The selected plan will begin after the
                    current subscription ends.

                </p>


            <?php else: ?>


                <p>

                    This will be your first subscription.

                    The selected plan will begin today
                    after your payment has been successfully
                    verified.

                </p>


            <?php endif; ?>



            <!-- PLAN COMPARISON -->

            <div class="comparison">


                <!-- CURRENT -->

                <div class="plan current">


                    <div class="label">
                        CURRENT PLAN
                    </div>


                    <?php if ($current_subscription): ?>


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


                        <div class="feature">

                            <strong>
                                Current Members:
                            </strong>

                            <?php

                            echo number_format(
                                $total_members
                            );

                            ?>

                        </div>


                        <div class="feature">

                            <strong>
                                Member Limit:
                            </strong>

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


                        <div class="feature">

                            <strong>
                                Active Until:
                            </strong>

                            <?php

                            echo formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            );

                            ?>

                        </div>


                    <?php else: ?>


                        <h2>
                            No Active Subscription
                        </h2>


                        <div class="feature">

                            This will be your first
                            subscription.

                        </div>


                    <?php endif; ?>


                </div>



                <!-- NEW PLAN -->

                <div class="plan new">


                    <div class="label">
                        SELECTED PLAN
                    </div>


                    <h2>

                        <?php

                        echo e(
                            $new_plan[
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
                            $new_plan[
                                "price"
                            ],
                            2
                        );

                        ?>

                        <span>
                            / month
                        </span>

                    </div>


                    <div class="feature">

                        <strong>
                            Member Limit:
                        </strong>

                        <?php

                        if (
                            $new_plan[
                                "member_limit"
                            ] !== null
                        ) {

                            echo number_format(
                                (int)
                                $new_plan[
                                    "member_limit"
                                ]
                            );

                        }
                        else {

                            echo "Unlimited";

                        }

                        ?>

                    </div>


                    <div class="feature">

                        <strong>
                            Starts:
                        </strong>

                        <?php

                        echo formatDate(
                            $new_start_date
                        );

                        ?>

                    </div>


                    <div class="feature">

                        <strong>
                            Ends:
                        </strong>

                        <?php

                        echo formatDate(
                            $new_end_date
                        );

                        ?>

                    </div>


                </div>


            </div>



            <!-- CURRENT SUBSCRIPTION NOTICE -->

            <?php if ($current_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        Your current plan will NOT be
                        cancelled immediately.
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

                    subscription remains active until

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

                    The new

                    <strong>

                        <?php

                        echo e(
                            $new_plan[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    plan will begin on

                    <strong>

                        <?php

                        echo formatDate(
                            $new_start_date
                        );

                        ?>

                    </strong>

                    after successful payment.

                </div>


            <?php else: ?>


                <div class="notice notice-success">

                    <strong>
                        First subscription
                    </strong>

                    <br><br>

                    Your selected plan will start on

                    <strong>

                        <?php

                        echo formatDate(
                            $new_start_date
                        );

                        ?>

                    </strong>

                    after successful payment.

                </div>


            <?php endif; ?>



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
                    $new_plan[
                        "member_limit"
                    ] !== null
                ): ?>


                    The selected plan allows

                    <strong>

                        <?php

                        echo number_format(
                            (int)
                            $new_plan[
                                "member_limit"
                            ]
                        );

                        ?>

                    </strong>

                    members.


                <?php else: ?>


                    The selected plan allows

                    <strong>
                        unlimited members
                    </strong>.


                <?php endif; ?>


            </div>



            <!-- MEMBER LIMIT ERROR -->

            <?php if ($limit_error !== ""): ?>


                <div class="notice notice-error">

                    <strong>
                        This plan cannot be selected.
                    </strong>

                    <br><br>

                    <?php

                    echo e(
                        $limit_error
                    );

                    ?>

                    <br><br>

                    Please choose a plan with a higher
                    member limit or reduce your gym's
                    member count before purchasing this
                    plan.

                </div>



            <!-- EXISTING SCHEDULE -->

            <?php elseif ($scheduled_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        A plan change is already scheduled.
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

                    You cannot create another plan change
                    until the existing scheduled plan has
                    been activated.

                </div>



            <?php else: ?>


                <!-- PAYMENT NOTICE -->

                <div class="payment-notice">

                    <strong>
                        Payment required
                    </strong>

                    <br><br>

                    You will be redirected to the payment
                    checkout after clicking the button below.

                    <br><br>

                    <strong>
                        Important:
                    </strong>

                    Your subscription will NOT be created
                    until the payment has been successfully
                    verified.

                </div>



                <!-- CONFIRM FORM -->

                <form
                    method="POST"
                    action="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                    onsubmit="return confirmSubscription();"
                >

                    <input
                        type="hidden"
                        name="plan_id"
                        value="<?php echo $plan_id; ?>"
                    >


                    <div class="actions">


                        <button
                            type="submit"
                            class="button button-primary"
                        >

                            Proceed to Payment

                        </button>


                        <a
                            href="subscription_plans.php"
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

function confirmSubscription()
{
    <?php if ($current_subscription): ?>

        return confirm(
            "Continue to payment?\n\n" +

            "Current plan: " +
            <?php
            echo json_encode(
                $current_subscription["plan_name"]
            );
            ?>
            + "\n" +

            "Current plan remains active until: " +
            <?php
            echo json_encode(
                formatDate(
                    $current_subscription["end_date"]
                )
            );
            ?>
            + "\n\n" +

            "New plan: " +
            <?php
            echo json_encode(
                $new_plan["plan_name"]
            );
            ?>
            + "\n" +

            "New plan starts: " +
            <?php
            echo json_encode(
                formatDate(
                    $new_start_date
                )
            );
            ?>
            + "\n\n" +

            "You will proceed to payment next."
        );

    <?php else: ?>

        return confirm(
            "Continue to payment?\n\n" +

            "Plan: " +
            <?php
            echo json_encode(
                $new_plan["plan_name"]
            );
            ?>
            + "\n" +

            "Price: Rs. " +
            <?php
            echo json_encode(
                number_format(
                    (float)
                    $new_plan["price"],
                    2
                )
            );
            ?>
            + "\n\n" +

            "The subscription will only be created " +
            "after successful payment verification."
        );

    <?php endif; ?>
}

</script>


</body>

</html>