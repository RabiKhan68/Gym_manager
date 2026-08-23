<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check admin login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get plan ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !ctype_digit($_GET["id"])
) {

    header("Location: admin_subscription_plans.php");
    exit();

}


$plan_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get subscription plan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        subscription_plan_id,
        plan_name,
        price,
        member_limit
     FROM subscription_plans
     WHERE subscription_plan_id = ?"
);


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

$plan = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Plan not found
|--------------------------------------------------------------------------
*/

if (!$plan) {

    header("Location: admin_subscription_plans.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Check whether gym owners are using this plan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS subscriber_count
     FROM gym_owner_subscriptions
     WHERE subscription_plan_id = ?"
);


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

$row = $result->fetch_assoc();

$stmt->close();


$subscriber_count =
    (int) $row["subscriber_count"];


/*
|--------------------------------------------------------------------------
| Delete only after POST confirmation
|--------------------------------------------------------------------------
*/

$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /*
    |--------------------------------------------------------------------------
    | Do not allow deletion if subscriptions are using this plan
    |--------------------------------------------------------------------------
    */

    if ($subscriber_count > 0) {

        $error =
            "This subscription plan cannot be deleted because " .
            $subscriber_count .
            " gym owner subscription(s) are currently using it.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Delete plan
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "DELETE FROM subscription_plans
             WHERE subscription_plan_id = ?"
        );


        if (!$stmt) {

            $error =
                "Database error: " .
                htmlspecialchars(
                    $conn->error
                );

        } else {


            $stmt->bind_param(
                "i",
                $plan_id
            );


            if ($stmt->execute()) {

                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Redirect after deletion
                |--------------------------------------------------------------------------
                */

                header(
                    "Location: admin_subscription_plans.php"
                );

                exit();

            } else {

                $error =
                    "Failed to delete subscription plan: " .
                    htmlspecialchars(
                        $stmt->error
                    );

                $stmt->close();

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
        Delete Subscription Plan
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                sans-serif;

            background: #f4f6f8;

            color: #1f2937;

        }


        .container {

            max-width: 700px;

            margin: auto;

            padding: 30px;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        h1 {

            margin-top: 0;

            font-size: 28px;

        }


        .warning {

            background: #fff7ed;

            border: 1px solid #fed7aa;

            color: #9a3412;

            padding: 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;

        }


        .error {

            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;

            padding: 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;

        }


        .plan-details {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 10px;

            padding: 20px;

            margin: 20px 0;

        }


        .detail {

            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding: 10px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .detail:last-child {

            border-bottom: none;

        }


        .label {

            color: #6b7280;

        }


        .value {

            font-weight: bold;

            text-align: right;

        }


        .actions {

            display: flex;

            gap: 10px;

            margin-top: 25px;

        }


        .button {

            display: inline-block;

            padding: 11px 18px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

        }


        .cancel-button {

            background: #e5e7eb;

            color: #374151;

        }


        .delete-button {

            background: #dc2626;

            color: white;

        }


        .button:hover {

            opacity: 0.85;

        }


        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }


            .card {

                padding: 20px;

            }


            .actions {

                flex-direction: column;

            }


            .button {

                width: 100%;

                text-align: center;

            }


            .detail {

                flex-direction: column;

                gap: 4px;

            }


            .value {

                text-align: left;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            Delete Subscription Plan
        </h1>


        <?php if ($error !== ""): ?>

            <div class="error">

                <?php

                echo htmlspecialchars($error);

                ?>

            </div>

        <?php endif; ?>


        <?php if ($subscriber_count > 0): ?>


            <div class="warning">

                <strong>
                    Cannot delete this plan.
                </strong>

                <br>

                <?php

                echo $subscriber_count;

                ?>

                gym owner subscription(s) are currently using this plan.

                <br><br>

                You should keep this plan so existing subscriptions continue to reference it.

            </div>


        <?php else: ?>


            <div class="warning">

                <strong>
                    Warning:
                </strong>

                You are about to permanently delete this subscription plan.

                This action cannot be undone.

            </div>


        <?php endif; ?>


        <!-- PLAN DETAILS -->

        <div class="plan-details">


            <div class="detail">

                <span class="label">
                    Plan ID
                </span>

                <span class="value">

                    <?php

                    echo $plan_id;

                    ?>

                </span>

            </div>


            <div class="detail">

                <span class="label">
                    Plan Name
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        $plan["plan_name"]
                    );

                    ?>

                </span>

            </div>


            <div class="detail">

                <span class="label">
                    Price
                </span>

                <span class="value">

                    Rs.

                    <?php

                    echo number_format(
                        (float)$plan["price"],
                        2
                    );

                    ?>

                </span>

            </div>


            <div class="detail">

                <span class="label">
                    Member Limit
                </span>

                <span class="value">

                    <?php

                    if (
                        $plan["member_limit"] !== null
                    ) {

                        echo number_format(
                            (int)$plan["member_limit"]
                        );

                    } else {

                        echo "Unlimited";

                    }

                    ?>

                </span>

            </div>


            <div class="detail">

                <span class="label">
                    Gym Owners Using Plan
                </span>

                <span class="value">

                    <?php

                    echo $subscriber_count;

                    ?>

                </span>

            </div>


        </div>


        <!-- ACTIONS -->

        <div class="actions">


            <a
                href="admin_subscription_plans.php"
                class="button cancel-button"
            >

                Cancel

            </a>


            <?php if ($subscriber_count === 0): ?>


                <form
                    method="POST"
                    action=""
                    style="margin: 0;"
                >

                    <button
                        type="submit"
                        class="button delete-button"
                        onclick="return confirm('Are you absolutely sure you want to permanently delete this subscription plan?');"
                    >

                        Delete Subscription Plan

                    </button>

                </form>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>