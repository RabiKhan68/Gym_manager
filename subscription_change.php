<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check gym owner login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Get selected plan ID
|--------------------------------------------------------------------------
*/

$plan_id = isset($_GET["plan_id"])
    ? (int) $_GET["plan_id"]
    : 0;


/*
|--------------------------------------------------------------------------
| Validate plan ID
|--------------------------------------------------------------------------
*/

if ($plan_id <= 0) {

    header("Location: my_subscription.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get selected subscription plan
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            subscription_plan_id,
            plan_name,
            price,
            member_limit

        FROM subscription_plans

        WHERE subscription_plan_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $plan_id
);

$stmt->execute();

$result = $stmt->get_result();

$new_plan = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check whether plan exists
|--------------------------------------------------------------------------
*/

if (!$new_plan) {

    die(
        "The selected subscription plan does not exist."
    );

}


/*
|--------------------------------------------------------------------------
| Get current subscription
|--------------------------------------------------------------------------
*/

$sql = "SELECT

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

        ORDER BY s.subscription_id DESC

        LIMIT 1";

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

$stmt->execute();

$result = $stmt->get_result();

$current_subscription =
    $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get current member count
|--------------------------------------------------------------------------
*/

$total_members = 0;

$sql = "SELECT COUNT(*) AS total

        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE g.owner_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $owner_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $row =
        $result->fetch_assoc();

    $total_members =
        (int) ($row["total"] ?? 0);

    $stmt->close();

}


/*
|--------------------------------------------------------------------------
| Check whether selected plan is current plan
|--------------------------------------------------------------------------
*/

$is_same_plan = false;

if ($current_subscription) {

    $is_same_plan =
        (int) $current_subscription[
            "subscription_plan_id"
        ] === $plan_id;

}


/*
|--------------------------------------------------------------------------
| Check member limit
|--------------------------------------------------------------------------
*/

$limit_error = "";

if (
    $new_plan["member_limit"] !== null
) {

    $new_member_limit =
        (int) $new_plan["member_limit"];

    if (
        $total_members >
        $new_member_limit
    ) {

        $limit_error =
            "You currently have " .
            $total_members .
            " members, but the selected plan " .
            "only allows " .
            $new_member_limit .
            " members. Please choose a higher plan.";

    }

}


/*
|--------------------------------------------------------------------------
| Handle confirmation
|--------------------------------------------------------------------------
*/

$success = "";
$error = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $confirm_plan_id =
        isset($_POST["plan_id"])
        ? (int) $_POST["plan_id"]
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Security check
    |--------------------------------------------------------------------------
    */

    if (
        $confirm_plan_id !== $plan_id
    ) {

        $error =
            "Invalid subscription plan.";

    }

    elseif ($is_same_plan) {

        $error =
            "You are already using this subscription plan.";

    }

    elseif ($limit_error !== "") {

        $error =
            $limit_error;

    }

    else {

        /*
        |--------------------------------------------------------------------------
        | Make sure current subscription exists
        |--------------------------------------------------------------------------
        */

        if (!$current_subscription) {

            $error =
                "You do not have an existing subscription.";

        }

        else {

            /*
            |--------------------------------------------------------------------------
            | Only active subscriptions can be changed
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(
                    $current_subscription["status"]
                ) !== "active"
            ) {

                $error =
                    "Your current subscription is not active. " .
                    "Please choose a subscription from the available plans.";

            }

            else {

                /*
                |--------------------------------------------------------------------------
                | Update subscription plan
                |--------------------------------------------------------------------------
                */

                $sql = "UPDATE gym_owner_subscriptions

                        SET subscription_plan_id = ?

                        WHERE subscription_id = ?

                        AND owner_id = ?";


                $stmt =
                    $conn->prepare($sql);


                if (!$stmt) {

                    $error =
                        "Database error: " .
                        htmlspecialchars(
                            $conn->error
                        );

                }

                else {

                    $stmt->bind_param(
                        "iii",
                        $plan_id,
                        $current_subscription[
                            "subscription_id"
                        ],
                        $owner_id
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $success =
                            "Your subscription plan has been changed successfully.";

                    }

                    else {

                        $error =
                            "Unable to change your subscription plan.";

                    }


                    $stmt->close();

                }

            }

        }

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

            max-width: 900px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

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

            display: inline-block;

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

        }


        .back:hover {

            opacity: 0.85;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 20px;

        }


        .card h2 {

            margin-top: 0;

        }


        .comparison {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin-top: 25px;

        }


        .plan {

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

        }


        .plan.current {

            border-color: #9ca3af;

            background: #f9fafb;

        }


        .plan.new {

            border: 2px solid #2563eb;

            background: #eff6ff;

        }


        .label {

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 8px;

        }


        .plan h3 {

            margin: 0 0 12px;

            font-size: 25px;

        }


        .price {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 15px;

        }


        .price span {

            font-size: 13px;

            font-weight: normal;

            color: #6b7280;

        }


        .feature {

            padding: 10px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .feature:last-child {

            border-bottom: none;

        }


        .notice {

            padding: 15px;

            border-radius: 8px;

            margin-top: 20px;

        }


        .notice-warning {

            background: #fffbeb;

            border: 1px solid #fde68a;

            color: #92400e;

        }


        .notice-error {

            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;

        }


        .notice-success {

            background: #dcfce7;

            border: 1px solid #bbf7d0;

            color: #166534;

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

            opacity: 0.85;

        }


        .member-info {

            background: #f8fafc;

            padding: 15px;

            border-radius: 8px;

            margin-top: 20px;

        }


        .member-info strong {

            font-size: 18px;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

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
                Review your new subscription plan
            </p>

        </div>


        <a
            href="my_subscription.php"
            class="back"
        >
            ← My Subscription
        </a>

    </div>



    <?php if ($success !== ""): ?>


        <div class="card">

            <div class="notice notice-success">

                <?php
                echo htmlspecialchars(
                    $success
                );
                ?>

            </div>


            <div class="actions">

                <a
                    href="my_subscription.php"
                    class="button button-primary"
                >
                    View My Subscription
                </a>

            </div>

        </div>


    <?php else: ?>


        <div class="card">


            <h2>
                Confirm Plan Change
            </h2>


            <p>
                Please review the details below before
                changing your subscription.
            </p>


            <?php if ($error !== ""): ?>

                <div class="notice notice-error">

                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($limit_error === ""): ?>


                <div class="comparison">


                    <!-- CURRENT PLAN -->

                    <div class="plan current">

                        <div class="label">
                            CURRENT PLAN
                        </div>


                        <?php if (
                            $current_subscription
                        ): ?>


                            <h3>

                                <?php

                                echo htmlspecialchars(
                                    $current_subscription[
                                        "plan_name"
                                    ]
                                );

                                ?>

                            </h3>


                            <div class="price">

                                Rs.

                                <?php

                                echo number_format(
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
                                    Members:
                                </strong>

                                <?php

                                echo $total_members;

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

                                    echo (int)
                                        $current_subscription[
                                            "member_limit"
                                        ];

                                } else {

                                    echo "Unlimited";

                                }

                                ?>

                            </div>


                            <div class="feature">

                                <strong>
                                    Expires:
                                </strong>

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $current_subscription[
                                            "end_date"
                                        ]
                                    )
                                );

                                ?>

                            </div>


                        <?php else: ?>


                            <h3>
                                No Subscription
                            </h3>


                            <div class="feature">

                                You are choosing your
                                first subscription plan.

                            </div>


                        <?php endif; ?>


                    </div>



                    <!-- NEW PLAN -->

                    <div class="plan new">

                        <div class="label">
                            NEW PLAN
                        </div>


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $new_plan[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </h3>


                        <div class="price">

                            Rs.

                            <?php

                            echo number_format(
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
                                Members:
                            </strong>

                            <?php

                            echo $total_members;

                            ?>

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

                                echo (int)
                                    $new_plan[
                                        "member_limit"
                                    ];

                            } else {

                                echo "Unlimited";

                            }

                            ?>

                        </div>


                        <div class="feature">

                            <strong>
                                Monthly Price:
                            </strong>

                            Rs.

                            <?php

                            echo number_format(
                                $new_plan[
                                    "price"
                                ],
                                2
                            );

                            ?>

                        </div>


                    </div>


                </div>



                <?php if (
                    $current_subscription
                ): ?>


                    <div class="notice notice-warning">

                        <strong>
                            Important:
                        </strong>

                        Changing your plan will update
                        your current subscription to the
                        selected plan.

                        Your current subscription expiry
                        date will remain unchanged.

                    </div>


                <?php endif; ?>



                <!-- CONFIRM FORM -->

                <form
                    method="POST"
                    action="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                    onsubmit="return confirm('Are you sure you want to change your subscription plan?');"
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
                            Confirm Plan Change
                        </button>


                        <a
                            href="my_subscription.php"
                            class="button button-cancel"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            <?php else: ?>


                <div class="notice notice-error">

                    <strong>
                        Cannot change to this plan.
                    </strong>

                    <br><br>

                    <?php

                    echo htmlspecialchars(
                        $limit_error
                    );

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


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>