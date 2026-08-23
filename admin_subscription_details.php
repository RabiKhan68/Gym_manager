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
| Check subscription ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    die("Invalid subscription ID.");

}

$subscription_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get subscription details
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            s.subscription_id,
            s.start_date,
            s.end_date,
            s.status AS subscription_status,
            s.created_at AS subscription_created_at,

            o.owner_id,
            o.name AS owner_name,
            o.email AS owner_email,
            o.phone AS owner_phone,

            sp.subscription_plan_id,
            sp.plan_name,
            sp.price,
            sp.member_limit,

            g.gym_id,
            g.gym_name,
            g.address AS gym_address,
            g.phone AS gym_phone

        FROM gym_owner_subscriptions s

        INNER JOIN gym_owners o
            ON s.owner_id = o.owner_id

        INNER JOIN subscription_plans sp
            ON s.subscription_plan_id =
               sp.subscription_plan_id

        LEFT JOIN gyms g
            ON o.owner_id = g.owner_id

        WHERE s.subscription_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $subscription_id
);

$stmt->execute();

$result = $stmt->get_result();

$subscription = $result->fetch_assoc();


if (!$subscription) {

    die("Subscription not found.");

}


/*
|--------------------------------------------------------------------------
| Calculate days remaining
|--------------------------------------------------------------------------
*/

$today = new DateTime();

$end_date = new DateTime(
    $subscription["end_date"]
);

$interval = $today->diff($end_date);

if ($end_date >= $today) {

    $days_remaining = $interval->days;

} else {

    $days_remaining = 0;

}


/*
|--------------------------------------------------------------------------
| Get payment history
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            payment_id,
            amount,
            payment_date,
            payment_method,
            payment_status,
            transaction_reference

        FROM gym_owner_subscription_payments

        WHERE subscription_id = ?

        ORDER BY payment_id DESC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $subscription_id
);

$stmt->execute();

$payments = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Payment statistics
|--------------------------------------------------------------------------
*/

$total_paid = 0;
$total_payments = 0;
$paid_payments = 0;
$pending_payments = 0;
$failed_payments = 0;


/*
|--------------------------------------------------------------------------
| Store payments so we can display them later
|--------------------------------------------------------------------------
*/

$payment_rows = [];


while (
    $payment =
    $payments->fetch_assoc()
) {

    $payment_rows[] = $payment;

    $total_payments++;


    if (
        $payment["payment_status"] === "paid"
    ) {

        $paid_payments++;

        $total_paid +=
            (float)$payment["amount"];

    }

    elseif (
        $payment["payment_status"] === "pending"
    ) {

        $pending_payments++;

    }

    elseif (
        $payment["payment_status"] === "failed"
    ) {

        $failed_payments++;

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
        Subscription Details
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

            max-width: 1300px;

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


        /* GRID */

        .grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

            margin-bottom: 25px;

        }


        /* CARD */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 25px;

        }


        .card h2 {

            margin-top: 0;

            margin-bottom: 20px;

        }


        /* INFO */

        .info {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

        }


        .info-item {

            background: #f8fafc;

            padding: 15px;

            border-radius: 8px;

        }


        .label {

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 5px;

        }


        .value {

            font-weight: bold;

            font-size: 16px;

        }


        /* PLAN */

        .plan-box {

            background: #f8fafc;

            padding: 25px;

            border-radius: 10px;

            text-align: center;

        }


        .plan-name {

            font-size: 26px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan-price {

            font-size: 30px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan-limit {

            color: #6b7280;

        }


        /* STATUS */

        .status {

            display: inline-block;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 13px;

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


        /* DAYS */

        .days {

            font-size: 30px;

            font-weight: bold;

        }


        .days-label {

            color: #6b7280;

        }


        /* PAYMENT SUMMARY */

        .payment-summary {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 25px;

        }


        .payment-stat {

            background: white;

            padding: 20px;

            border-radius: 10px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .payment-stat-title {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 8px;

        }


        .payment-stat-number {

            font-size: 24px;

            font-weight: bold;

        }


        /* TABLE */

        .table-container {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 900px;

        }


        th,
        td {

            padding: 13px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

        }


        th {

            background: #f8fafc;

            white-space: nowrap;

        }


        tr:hover {

            background: #f9fafb;

        }


        .amount {

            font-weight: bold;

            white-space: nowrap;

        }


        .date {

            white-space: nowrap;

            color: #6b7280;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .pending {

            color: #d97706;

            font-weight: bold;

        }


        .failed {

            color: red;

            font-weight: bold;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #6b7280;

        }


        /* MOBILE */

        @media (max-width: 900px) {

            .grid {

                grid-template-columns: 1fr;

            }


            .payment-summary {

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


            .info {

                grid-template-columns: 1fr;

            }


            .payment-summary {

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
                Subscription Details
            </h1>

            <p>

                <?php

                echo htmlspecialchars(
                    $subscription["owner_name"]
                );

                ?>

                — 

                <?php

                echo htmlspecialchars(
                    $subscription["plan_name"]
                );

                ?>

            </p>

        </div>


        <a
            href="admin_subscriptions.php"
            class="back"
        >

            ← Subscriptions

        </a>

    </div>



    <!-- OWNER + PLAN -->

    <div class="grid">


        <!-- OWNER -->

        <div class="card">

            <h2>
                Gym Owner
            </h2>


            <div class="info">


                <div class="info-item">

                    <div class="label">
                        Name
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "owner_name"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="label">
                        Email
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "owner_email"
                            ]
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="label">
                        Phone
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "owner_phone"
                            ] ?? "-"
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="label">
                        Gym
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "gym_name"
                            ] ?? "No gym"
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="label">
                        Gym Phone
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "gym_phone"
                            ] ?? "-"
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="label">
                        Address
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $subscription[
                                "gym_address"
                            ] ?? "-"
                        );

                        ?>

                    </div>

                </div>


            </div>

        </div>



        <!-- PLAN -->

        <div class="card">

            <h2>
                Current Subscription
            </h2>


            <div class="plan-box">


                <div class="plan-name">

                    <?php

                    echo htmlspecialchars(
                        $subscription[
                            "plan_name"
                        ]
                    );

                    ?>

                </div>


                <div class="plan-price">

                    Rs.

                    <?php

                    echo number_format(
                        $subscription[
                            "price"
                        ],
                        2
                    );

                    ?>

                </div>


                <div class="plan-limit">

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

                        echo " members";

                    } else {

                        echo "Unlimited members";

                    }

                    ?>

                </div>


                <br>


                <?php

                $status =
                    $subscription[
                        "subscription_status"
                    ];

                ?>


                <span
                    class="status status-<?php echo htmlspecialchars($status); ?>"
                >

                    <?php

                    echo htmlspecialchars(
                        $status
                    );

                    ?>

                </span>


            </div>

        </div>


    </div>



    <!-- SUBSCRIPTION DATES -->

    <div class="card">

        <h2>
            Subscription Period
        </h2>


        <div class="info">


            <div class="info-item">

                <div class="label">
                    Start Date
                </div>

                <div class="value">

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

                </div>

            </div>


            <div class="info-item">

                <div class="label">
                    End Date
                </div>

                <div class="value">

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

                </div>

            </div>


            <div class="info-item">

                <div class="label">
                    Days Remaining
                </div>

                <div class="days">

                    <?php

                    if (
                        $subscription[
                            "subscription_status"
                        ] === "active"
                    ) {

                        echo $days_remaining;

                    } else {

                        echo "0";

                    }

                    ?>

                </div>

                <div class="days-label">
                    days
                </div>

            </div>


            <div class="info-item">

                <div class="label">
                    Subscription ID
                </div>

                <div class="value">

                    #<?php

                    echo (int)
                        $subscription[
                            "subscription_id"
                        ];

                    ?>

                </div>

            </div>


        </div>

    </div>



    <!-- PAYMENT SUMMARY -->

    <div class="payment-summary">


        <div class="payment-stat">

            <div class="payment-stat-title">
                Total Paid
            </div>

            <div class="payment-stat-number">

                Rs.

                <?php

                echo number_format(
                    $total_paid,
                    2
                );

                ?>

            </div>

        </div>


        <div class="payment-stat">

            <div class="payment-stat-title">
                Total Payments
            </div>

            <div class="payment-stat-number">

                <?php

                echo $total_payments;

                ?>

            </div>

        </div>


        <div class="payment-stat">

            <div class="payment-stat-title">
                Paid Payments
            </div>

            <div class="payment-stat-number">

                <?php

                echo $paid_payments;

                ?>

            </div>

        </div>


        <div class="payment-stat">

            <div class="payment-stat-title">
                Pending / Failed
            </div>

            <div class="payment-stat-number">

                <?php

                echo
                    $pending_payments +
                    $failed_payments;

                ?>

            </div>

        </div>


    </div>



    <!-- PAYMENT HISTORY -->

    <div class="card">

        <h2>
            Payment History
        </h2>


        <?php if (
            count($payment_rows) > 0
        ): ?>


            <div class="table-container">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Payment ID
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
                                Status
                            </th>

                            <th>
                                Transaction Reference
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $payment_rows
                        as $payment
                    ): ?>


                        <tr>


                            <td>

                                #

                                <?php

                                echo (int)
                                    $payment[
                                        "payment_id"
                                    ];

                                ?>

                            </td>


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


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "payment_method"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                $payment_status =
                                    $payment[
                                        "payment_status"
                                    ];


                                if (
                                    $payment_status ===
                                    "paid"
                                ) {

                                    echo '<span class="paid">
                                            Paid
                                          </span>';

                                }

                                elseif (
                                    $payment_status ===
                                    "pending"
                                ) {

                                    echo '<span class="pending">
                                            Pending
                                          </span>';

                                }

                                elseif (
                                    $payment_status ===
                                    "failed"
                                ) {

                                    echo '<span class="failed">
                                            Failed
                                          </span>';

                                }

                                else {

                                    echo htmlspecialchars(
                                        $payment_status
                                    );

                                }

                                ?>

                            </td>


                            <td>

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


            </div>


        <?php else: ?>


            <div class="empty">

                No payments have been recorded
                for this subscription.

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>