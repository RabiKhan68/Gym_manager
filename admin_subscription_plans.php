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
| Get subscription plans
|--------------------------------------------------------------------------
|
| We also count how many gym owners are currently using each plan.
|
*/

$sql = "SELECT

            sp.subscription_plan_id,
            sp.plan_name,
            sp.price,
            sp.member_limit,

            COUNT(s.subscription_id) AS subscriber_count

        FROM subscription_plans sp

        LEFT JOIN gym_owner_subscriptions s
            ON sp.subscription_plan_id =
               s.subscription_plan_id

        GROUP BY

            sp.subscription_plan_id,
            sp.plan_name,
            sp.price,
            sp.member_limit

        ORDER BY
            sp.subscription_plan_id ASC";


$result = $conn->query($sql);


if (!$result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


$plans = [];


while ($row = $result->fetch_assoc()) {

    $plans[] = $row;

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
        Subscription Plans
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

            max-width: 1200px;

            margin: auto;

            padding: 30px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

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


        .header-actions {

            display: flex;

            gap: 10px;

            align-items: center;

        }


        .button {

            display: inline-block;

            padding: 10px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            color: white;

        }


        .dashboard-button {

            background: #111827;

        }


        .create-button {

            background: #16a34a;

        }


        .button:hover {

            opacity: 0.85;

        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 25px;

        }


        .summary-card {

            background: white;

            padding: 22px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .summary-title {

            color: #6b7280;

            font-size: 14px;

            margin-bottom: 10px;

        }


        .summary-number {

            font-size: 30px;

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;

        }


        th,
        td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

        }


        th {

            background: #f8fafc;

            font-weight: bold;

            white-space: nowrap;

        }


        tr:hover {

            background: #f9fafb;

        }


        /*
        |--------------------------------------------------------------------------
        | PLAN
        |--------------------------------------------------------------------------
        */

        .plan-name {

            font-weight: bold;

            font-size: 16px;

        }


        .price {

            font-weight: bold;

            white-space: nowrap;

        }


        .limit {

            white-space: nowrap;

        }


        .subscribers {

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        |
        | Since your current subscription_plans table code does not show
        | a status column, we display every existing plan as Active.
        |
        */

        .status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            background: #dcfce7;

            color: #166534;

            font-size: 12px;

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | ACTION BUTTONS
        |--------------------------------------------------------------------------
        */

        .action-button {

            display: inline-block;

            padding: 8px 12px;

            border-radius: 6px;

            color: white;

            text-decoration: none;

            font-size: 13px;

            margin-right: 4px;

            white-space: nowrap;

        }


        .view-button {

            background: #111827;

        }


        .edit-button {

            background: #2563eb;

        }


        .delete-button {

            background: #dc2626;

        }


        .action-button:hover {

            opacity: 0.85;

        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .empty {

            text-align: center;

            padding: 50px;

            color: #6b7280;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .summary {

                grid-template-columns:
                    1fr;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }


            .header-actions {

                width: 100%;

                flex-wrap: wrap;

            }

        }


        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!--
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    -->

    <div class="header">


        <div>

            <h1>
                Subscription Plans
            </h1>

            <p>
                Manage the packages offered to gym owners
            </p>

        </div>


        <div class="header-actions">


            <a
                href="admin_subscription_plan_create.php"
                class="button create-button"
            >

                + Create Plan

            </a>


            <a
                href="admin_dashboard.php"
                class="button dashboard-button"
            >

                ← Dashboard

            </a>


        </div>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    -->

    <?php

    $total_plans =
        count($plans);

    $total_subscribers = 0;

    foreach ($plans as $plan) {

        $total_subscribers +=
            (int) $plan["subscriber_count"];

    }

    ?>


    <div class="summary">


        <div class="summary-card">

            <div class="summary-title">

                Total Plans

            </div>


            <div class="summary-number">

                <?php

                echo $total_plans;

                ?>

            </div>

        </div>



        <div class="summary-card">

            <div class="summary-title">

                Total Gym Owners Using Plans

            </div>


            <div class="summary-number">

                <?php

                echo $total_subscribers;

                ?>

            </div>

        </div>



        <div class="summary-card">

            <div class="summary-title">

                Available Plans

            </div>


            <div class="summary-number">

                <?php

                echo $total_plans;

                ?>

            </div>

        </div>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | PLANS TABLE
    |--------------------------------------------------------------------------
    -->

    <div class="card">


        <?php if (count($plans) > 0): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Member Limit
                        </th>

                        <th>
                            Gym Owners
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach ($plans as $plan): ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?php

                            echo (int)
                                $plan[
                                    "subscription_plan_id"
                                ];

                            ?>

                        </td>



                        <!-- PLAN NAME -->

                        <td>

                            <div class="plan-name">

                                <?php

                                echo htmlspecialchars(
                                    $plan["plan_name"]
                                );

                                ?>

                            </div>

                        </td>



                        <!-- PRICE -->

                        <td class="price">

                            Rs.

                            <?php

                            echo number_format(
                                (float)
                                $plan["price"],
                                2
                            );

                            ?>

                        </td>



                        <!-- MEMBER LIMIT -->

                        <td class="limit">

                            <?php

                            if (
                                $plan[
                                    "member_limit"
                                ] !== null
                            ) {

                                echo number_format(
                                    (int)
                                    $plan[
                                        "member_limit"
                                    ]
                                );

                            } else {

                                echo "Unlimited";

                            }

                            ?>

                        </td>



                        <!-- SUBSCRIBERS -->

                        <td class="subscribers">

                            <?php

                            echo (int)
                                $plan[
                                    "subscriber_count"
                                ];

                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span class="status">

                                Active

                            </span>

                        </td>



                        <!-- ACTIONS -->

                        <td>


                            <a
                                href="admin_subscription_plan_edit.php?id=<?php echo (int)$plan["subscription_plan_id"]; ?>"
                                class="action-button edit-button"
                            >

                                Edit

                            </a>


                            <a
                                href="admin_subscription_plan_delete.php?id=<?php echo (int)$plan["subscription_plan_id"]; ?>"
                                class="action-button delete-button"
                                onclick="return confirm('Are you sure you want to delete this subscription plan?');"
                            >

                                Delete

                            </a>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">


                <h2>
                    No subscription plans yet
                </h2>


                <p>
                    Create your first subscription plan to offer it to gym owners.
                </p>


                <br>


                <a
                    href="admin_subscription_plan_create.php"
                    class="button create-button"
                >

                    + Create First Plan

                </a>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>