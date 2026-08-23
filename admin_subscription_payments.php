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
| Get subscription payments
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            p.payment_id,
            p.amount,
            p.payment_date,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,

            o.owner_id,
            o.name AS owner_name,
            o.email AS owner_email,
            o.phone AS owner_phone,

            s.subscription_id,
            s.start_date,
            s.end_date,
            s.status AS subscription_status,

            sp.subscription_plan_id,
            sp.plan_name,
            sp.price,
            sp.member_limit,

            g.gym_name

        FROM gym_owner_subscription_payments p

        INNER JOIN gym_owners o
            ON p.owner_id = o.owner_id

        INNER JOIN gym_owner_subscriptions s
            ON p.subscription_id = s.subscription_id

        INNER JOIN subscription_plans sp
            ON s.subscription_plan_id = sp.subscription_plan_id

        LEFT JOIN gyms g
            ON o.owner_id = g.owner_id

        ORDER BY p.payment_id DESC";


$result = $conn->query($sql);


if (!$result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$total_revenue = 0;

$paid_count = 0;
$pending_count = 0;
$failed_count = 0;

$payments = [];


while ($row = $result->fetch_assoc()) {

    $payments[] = $row;


    if ($row["payment_status"] === "paid") {

        $paid_count++;

        $total_revenue +=
            (float) $row["amount"];

    }

    elseif ($row["payment_status"] === "pending") {

        $pending_count++;

    }

    elseif ($row["payment_status"] === "failed") {

        $failed_count++;

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
        Subscription Payments
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

            font-size: 28px;

            font-weight: bold;

        }


        /* TABLE */

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

            min-width: 1350px;

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


        .owner {

            font-weight: bold;

        }


        .email {

            color: #6b7280;

            font-size: 13px;

        }


        .gym {

            font-weight: bold;

        }


        .plan {

            font-weight: bold;

        }


        .amount {

            font-weight: bold;

            white-space: nowrap;

        }


        .date {

            white-space: nowrap;

            color: #6b7280;

        }


        /* PAYMENT STATUS */

        .status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .paid {

            background: #dcfce7;

            color: #166534;

        }


        .pending {

            background: #fef3c7;

            color: #92400e;

        }


        .failed {

            background: #fee2e2;

            color: #991b1b;

        }


        /* SUBSCRIPTION STATUS */

        .subscription-active {

            color: #166534;

            font-weight: bold;

        }


        .subscription-expired {

            color: #991b1b;

            font-weight: bold;

        }


        .subscription-cancelled {

            color: #6b7280;

            font-weight: bold;

        }


        .transaction {

            font-family:
                monospace;

            font-size: 13px;

        }


        .empty {

            text-align: center;

            padding: 50px;

            color: #6b7280;

        }


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
                Subscription Payments
            </h1>

            <p>
                Monitor payments made by gym owners
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
                Total Revenue
            </div>

            <div class="summary-number">

                Rs.

                <?php

                echo number_format(
                    $total_revenue,
                    2
                );

                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Paid Payments
            </div>

            <div class="summary-number">

                <?php
                echo $paid_count;
                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Pending Payments
            </div>

            <div class="summary-number">

                <?php
                echo $pending_count;
                ?>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Failed Payments
            </div>

            <div class="summary-number">

                <?php
                echo $failed_count;
                ?>

            </div>

        </div>


    </div>



    <!-- PAYMENTS -->

    <div class="card">


        <?php if (count($payments) > 0): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            Payment ID
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
                            Package
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Payment Date
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Subscription
                        </th>

                        <th>
                            Transaction
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $payments
                    as $payment
                ): ?>


                    <tr>


                        <!-- PAYMENT ID -->

                        <td>

                            <?php

                            echo (int)
                                $payment[
                                    "payment_id"
                                ];

                            ?>

                        </td>



                        <!-- OWNER -->

                        <td>

                            <div class="owner">

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "owner_name"
                                    ]
                                );

                                ?>

                            </div>


                            <div class="email">

                                <?php

                                echo htmlspecialchars(
                                    $payment[
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
                                $payment[
                                    "owner_email"
                                ]
                            );

                            ?>

                        </td>



                        <!-- GYM -->

                        <td class="gym">

                            <?php

                            echo htmlspecialchars(
                                $payment[
                                    "gym_name"
                                ] ?? "No gym"
                            );

                            ?>

                        </td>



                        <!-- PACKAGE -->

                        <td class="plan">

                            <?php

                            echo htmlspecialchars(
                                $payment[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </td>



                        <!-- AMOUNT -->

                        <td class="amount">

                            Rs.

                            <?php

                            echo number_format(
                                $payment[
                                    "amount"
                                ],
                                2
                            );

                            ?>

                        </td>



                        <!-- PAYMENT DATE -->

                        <td class="date">

                            <?php

                            if (
                                !empty(
                                    $payment[
                                        "payment_date"
                                    ]
                                )
                            ) {

                                echo date(
                                    "d M Y h:i A",
                                    strtotime(
                                        $payment[
                                            "payment_date"
                                        ]
                                    )
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>



                        <!-- METHOD -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $payment[
                                            "payment_method"
                                        ]
                                    )
                                )
                            );

                            ?>

                        </td>



                        <!-- PAYMENT STATUS -->

                        <td>

                            <?php

                            $payment_status =
                                $payment[
                                    "payment_status"
                                ];

                            ?>

                            <span
                                class="status <?php echo htmlspecialchars($payment_status); ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    ucfirst(
                                        $payment_status
                                    )
                                );

                                ?>

                            </span>

                        </td>



                        <!-- SUBSCRIPTION STATUS -->

                        <td>

                            <?php

                            $subscription_status =
                                $payment[
                                    "subscription_status"
                                ];

                            $subscription_class =
                                "subscription-" .
                                $subscription_status;

                            ?>

                            <span
                                class="<?php echo htmlspecialchars($subscription_class); ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    ucfirst(
                                        $subscription_status
                                    )
                                );

                                ?>

                            </span>

                        </td>



                        <!-- TRANSACTION -->

                        <td class="transaction">

                            <?php

                            echo htmlspecialchars(
                                $payment[
                                    "transaction_reference"
                                ] ?? "-"
                            );

                            ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                <h2>
                    No subscription payments yet
                </h2>

                <p>
                    Gym owners have not made any subscription payments.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>