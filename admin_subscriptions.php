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
| Get all gym-owner subscriptions
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            s.subscription_id,
            s.start_date,
            s.end_date,
            s.status,
            s.created_at,

            o.owner_id,
            o.name AS owner_name,
            o.email AS owner_email,
            o.phone AS owner_phone,

            sp.plan_name,
            sp.price,
            sp.member_limit,

            g.gym_name,
            g.address AS gym_address,
            g.phone AS gym_phone

        FROM gym_owner_subscriptions s

        INNER JOIN gym_owners o
            ON s.owner_id = o.owner_id

        INNER JOIN subscription_plans sp
            ON s.subscription_plan_id = sp.subscription_plan_id

        LEFT JOIN gyms g
            ON o.owner_id = g.owner_id

        ORDER BY s.subscription_id DESC";


$result = $conn->query($sql);


if (!$result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total_subscriptions = 0;
$active_subscriptions = 0;
$expired_subscriptions = 0;
$cancelled_subscriptions = 0;


$subscriptions = [];


while ($row = $result->fetch_assoc()) {

    $subscriptions[] = $row;

    $total_subscriptions++;

    if ($row["status"] === "active") {

        $active_subscriptions++;

    }

    elseif ($row["status"] === "expired") {

        $expired_subscriptions++;

    }

    elseif ($row["status"] === "cancelled") {

        $cancelled_subscriptions++;

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
        Gym Owner Subscriptions
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

            max-width: 1500px;

            margin: auto;

            padding: 30px;

        }


        /* HEADER */

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

            margin: 5px 0 0;

            color: #6b7280;

        }


        .back {

            display: inline-block;

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

        }


        .back:hover {

            opacity: 0.85;

        }


        /* SUMMARY */

        .summary {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

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


        /* TABLE CARD */

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

            border-collapse:
                collapse;

            min-width: 1250px;

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


        /* OWNER */

        .owner {

            font-weight: bold;

        }


        .email {

            color: #6b7280;

            font-size: 13px;

        }


        /* GYM */

        .gym {

            font-weight: bold;

        }


        /* PLAN */

        .plan {

            font-weight: bold;

        }


        .price {

            font-weight: bold;

            white-space: nowrap;

        }


        /* DATES */

        .date {

            white-space: nowrap;

            color: #374151;

        }


        /* STATUS */

        .status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            text-transform: capitalize;

        }


        .status-active {

            background: #dcfce7;

            color: #166534;

        }


        .status-expired {

            background: #fee2e2;

            color: #991b1b;

        }


        .status-cancelled {

            background: #e5e7eb;

            color: #374151;

        }


        /* ACTION */

        .view-button {

            display: inline-block;

            padding: 8px 12px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-size: 13px;

            white-space: nowrap;

        }


        .view-button:hover {

            opacity: 0.85;

        }


        /* EMPTY */

        .empty {

            text-align: center;

            padding: 50px;

            color: #6b7280;

        }


        /* MOBILE */

        @media (max-width: 900px) {

            .summary {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }


            .summary {

                grid-template-columns:
                    1fr;

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
                Gym Owner Subscriptions
            </h1>

            <p>
                Monitor subscription plans for all gym owners
            </p>

        </div>


        <a
            href="admin_dashboard.php"
            class="back"
        >

            ← Dashboard

        </a>

    </div>



    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-card">

            <div class="summary-title">
                Total Subscriptions
            </div>

            <div class="summary-number">

                <?php
                echo $total_subscriptions;
                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Active
            </div>

            <div class="summary-number">

                <?php
                echo $active_subscriptions;
                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Expired
            </div>

            <div class="summary-number">

                <?php
                echo $expired_subscriptions;
                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Cancelled
            </div>

            <div class="summary-number">

                <?php
                echo $cancelled_subscriptions;
                ?>

            </div>

        </div>


    </div>



    <!-- SUBSCRIPTIONS -->

    <div class="card">


        <?php if (count($subscriptions) > 0): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Gym Owner
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Gym
                        </th>

                        <th>
                            Subscription
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Member Limit
                        </th>

                        <th>
                            Start Date
                        </th>

                        <th>
                            Expiry Date
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $subscriptions
                    as $subscription
                ): ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?php

                            echo (int)
                                $subscription[
                                    "subscription_id"
                                ];

                            ?>

                        </td>



                        <!-- OWNER -->

                        <td>

                            <div class="owner">

                                <?php

                                echo htmlspecialchars(
                                    $subscription[
                                        "owner_name"
                                    ]
                                );

                                ?>

                            </div>


                            <div class="email">

                                <?php

                                echo htmlspecialchars(
                                    $subscription[
                                        "owner_phone"
                                    ] ?? "-"
                                );

                                ?>

                            </div>

                        </td>



                        <!-- EMAIL -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $subscription[
                                    "owner_email"
                                ]
                            );

                            ?>

                        </td>



                        <!-- GYM -->

                        <td class="gym">

                            <?php

                            echo htmlspecialchars(
                                $subscription[
                                    "gym_name"
                                ] ?? "No gym"
                            );

                            ?>

                        </td>



                        <!-- PLAN -->

                        <td class="plan">

                            <?php

                            echo htmlspecialchars(
                                $subscription[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </td>



                        <!-- PRICE -->

                        <td class="price">

                            Rs.

                            <?php

                            echo number_format(
                                $subscription[
                                    "price"
                                ],
                                2
                            );

                            ?>

                        </td>



                        <!-- MEMBER LIMIT -->

                        <td>

                            <?php

                            if (
                                $subscription[
                                    "member_limit"
                                ] !== null
                            ) {

                                echo (int)
                                    $subscription[
                                        "member_limit"
                                    ];

                            } else {

                                echo "Unlimited";

                            }

                            ?>

                        </td>



                        <!-- START -->

                        <td class="date">

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $subscription[
                                        "start_date"
                                    ]
                                )
                            );

                            ?>

                        </td>



                        <!-- END -->

                        <td class="date">

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $subscription[
                                        "end_date"
                                    ]
                                )
                            );

                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <?php

                            $status =
                                $subscription[
                                    "status"
                                ];

                            $status_class =
                                "status-" .
                                $status;

                            ?>

                            <span
                                class="status <?php echo $status_class; ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $status
                                );

                                ?>

                            </span>

                        </td>



                        <!-- ACTION -->

                        <td>

                            <a
                                href="admin_owner_details.php?id=<?php echo (int)$subscription["owner_id"]; ?>"
                                class="view-button"
                            >

                                Owner Details

                            </a>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                <h2>
                    No subscriptions yet
                </h2>

                <p>
                    No gym owner subscriptions have been created.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>