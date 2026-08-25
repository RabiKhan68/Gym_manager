<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
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
| CHECK ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| PENDING SUBSCRIPTION PAYMENT NOTIFICATION
|--------------------------------------------------------------------------
|
| Count submitted payments that have not yet been linked
| to a subscription.
|
|--------------------------------------------------------------------------
*/

$notification_count = 0;

$notification_sql = "
    SELECT COUNT(*) AS total
    FROM owner_subscription_payments
    WHERE payment_status = 'submitted'
    AND subscription_id IS NULL
";

$notification_result =
    $conn->query($notification_sql);

if ($notification_result) {

    $notification_row =
        $notification_result->fetch_assoc();

    $notification_count =
        (int) ($notification_row["total"] ?? 0);

}

/*
|--------------------------------------------------------------------------
| GET SUBSCRIPTION PAYMENTS
|--------------------------------------------------------------------------
|
| Correct payment table:
|
| owner_subscription_payments
|
| Important:
|
| A payment can exist BEFORE a subscription is created.
|
| Therefore:
|
| p.subscription_id can be NULL.
|
| The subscription plan is taken directly from:
|
| p.subscription_plan_id
|
| The subscription table is LEFT JOINed because the
| subscription may not exist yet.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        p.payment_id,
        p.owner_id,
        p.subscription_plan_id,
        p.subscription_id,
        p.amount,
        p.payment_method,
        p.payment_status,
        p.transaction_reference,
        p.gateway_transaction_id,
        p.created_at,

        o.name AS owner_name,
        o.email AS owner_email,
        o.phone AS owner_phone,

        sp.plan_name,
        sp.price,
        sp.member_limit,

        s.start_date,
        s.end_date,
        s.status AS subscription_status,

        g.gym_name

    FROM owner_subscription_payments p

    INNER JOIN gym_owners o
        ON p.owner_id = o.owner_id

    INNER JOIN subscription_plans sp
        ON p.subscription_plan_id =
           sp.subscription_plan_id

    LEFT JOIN gym_owner_subscriptions s
        ON p.subscription_id =
           s.subscription_id

    LEFT JOIN gyms g
        ON o.owner_id = g.owner_id

    ORDER BY
        p.payment_id DESC
";


$result =
    $conn->query($sql);


if (!$result) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$total_revenue = 0;

$paid_count = 0;

$submitted_count = 0;

$pending_count = 0;

$failed_count = 0;

$payments = [];


while (
    $row =
    $result->fetch_assoc()
) {

    $payments[] =
        $row;


    $payment_status =
        strtolower(
            trim(
                (string)
                $row["payment_status"]
            )
        );


    /*
    |--------------------------------------------------------------------------
    | PAID
    |--------------------------------------------------------------------------
    */

    if (
        $payment_status === "paid"
    ) {

        $paid_count++;


        $total_revenue +=
            (float)
            $row["amount"];

    }


    /*
    |--------------------------------------------------------------------------
    | SUBMITTED
    |--------------------------------------------------------------------------
    */

    elseif (
        $payment_status === "submitted"
    ) {

        $submitted_count++;

    }


    /*
    |--------------------------------------------------------------------------
    | PENDING
    |--------------------------------------------------------------------------
    */

    elseif (
        $payment_status === "pending"
    ) {

        $pending_count++;

    }


    /*
    |--------------------------------------------------------------------------
    | FAILED
    |--------------------------------------------------------------------------
    */

    elseif (
        $payment_status === "failed"
    ) {

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
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #1f2937;

        }


        .container {

            max-width: 1650px;

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

            gap: 20px;

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

            font-weight: bold;

            white-space: nowrap;

        }


        .back:hover {

            opacity: .85;

        }

        /*
|--------------------------------------------------------------------------
| NOTIFICATION BADGE
|--------------------------------------------------------------------------
*/

.notification-link {

    position: relative;

    display: inline-block;

}


.notification-badge {

    position: absolute;

    top: -9px;

    right: -9px;

    min-width: 22px;

    height: 22px;

    padding: 0 6px;

    background: #dc2626;

    color: white;

    border-radius: 999px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    font-weight: bold;

    line-height: 1;

    border: 2px solid white;

    box-shadow:
        0 2px 6px
        rgba(0, 0, 0, 0.2);

}

        /*
        |--------------------------------------------------------------------------
        | SUCCESS MESSAGE
        |--------------------------------------------------------------------------
        */

        .success {

            margin-bottom: 20px;

            padding: 15px 18px;

            background: #dcfce7;

            border:
                1px solid #86efac;

            border-radius: 10px;

            color: #166534;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | INFO
        |--------------------------------------------------------------------------
        */

        .info {

            margin-bottom: 20px;

            padding: 15px 18px;

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            border-radius: 10px;

            color: #1e40af;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        .summary {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 20px;

            margin-bottom: 25px;

        }


        .summary-card {

            background: white;

            padding: 22px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, .06);

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


        /*
        |--------------------------------------------------------------------------
        | TABLE CARD
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, .06);

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1750px;

        }


        th,
        td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

            vertical-align: top;

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
        | OWNER
        |--------------------------------------------------------------------------
        */

        .owner {

            font-weight: bold;

        }


        .phone {

            color: #6b7280;

            font-size: 13px;

            margin-top: 4px;

        }


        .email {

            color: #374151;

        }


        /*
        |--------------------------------------------------------------------------
        | GYM
        |--------------------------------------------------------------------------
        */

        .gym {

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | PLAN
        |--------------------------------------------------------------------------
        */

        .plan {

            font-weight: bold;

        }


        .plan-limit {

            color: #6b7280;

            font-size: 12px;

            margin-top: 4px;

        }


        /*
        |--------------------------------------------------------------------------
        | AMOUNT
        |--------------------------------------------------------------------------
        */

        .amount {

            font-weight: bold;

            white-space: nowrap;

        }


        /*
        |--------------------------------------------------------------------------
        | DATE
        |--------------------------------------------------------------------------
        */

        .date {

            white-space: nowrap;

            color: #6b7280;

        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT STATUS
        |--------------------------------------------------------------------------
        */

        .status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

        }


        .paid {

            background: #dcfce7;

            color: #166534;

        }


        .submitted {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .pending {

            background: #fef3c7;

            color: #92400e;

        }


        .failed {

            background: #fee2e2;

            color: #991b1b;

        }


        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION
        |--------------------------------------------------------------------------
        */

        .subscription-status {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

        }


        .subscription-active {

            background: #dcfce7;

            color: #166534;

        }


        .subscription-scheduled {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .subscription-expired {

            background: #fee2e2;

            color: #991b1b;

        }


        .subscription-cancelled {

            background: #e5e7eb;

            color: #374151;

        }


        .subscription-not-created {

            background: #fef3c7;

            color: #92400e;

        }


        .subscription-details {

            margin-top: 7px;

            color: #6b7280;

            font-size: 13px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        .transaction {

            font-family: monospace;

            font-size: 13px;

            max-width: 280px;

            word-break: break-all;

        }


        .gateway {

            margin-top: 7px;

            color: #6b7280;

            font-size: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY BUTTON
        |--------------------------------------------------------------------------
        */

        .verify-button {

            display: inline-block;

            margin-top: 12px;

            padding: 9px 13px;

            background: #16a34a;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

        }


        .verify-button:hover {

            background: #15803d;

        }


        .verified-text {

            display: inline-block;

            margin-top: 8px;

            color: #166534;

            font-size: 12px;

            font-weight: bold;

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
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1200px) {

            .summary {

                grid-template-columns:
                    repeat(3, 1fr);

            }

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

            }


            .summary {

                grid-template-columns: 1fr;

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
                Subscription Payments
            </h1>


            <p>
                Monitor payments made by gym owners
            </p>

        </div>


        <a
            href="admin_dashboard.php"
            class="back notification-link"
        >

            ← Dashboard

        </a>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    -->

    <?php if (
        isset($_GET["verified"]) &&
        $_GET["verified"] === "1"
    ): ?>


        <div class="success">

            <strong>
                Payment verified successfully.
            </strong>

            The subscription was created, the payment was
            linked to it, and the payment status was changed
            to <strong>Paid</strong>.

        </div>


    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | INFORMATION
    |--------------------------------------------------------------------------
    -->

    <div class="info">


        <strong>
            Payment verification:
        </strong>


        Payments marked as
        <strong>Submitted</strong>
        are waiting for administrator verification.


        <br>


        When you verify a submitted payment, the system will:


        <strong>

            create the subscription,

            link the payment to that subscription,

            and change the payment status to Paid.

        </strong>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    -->

    <div class="summary">


        <!-- TOTAL REVENUE -->

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



        <!-- PAID -->

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



        <!-- SUBMITTED -->

        <div class="summary-card">

            <div class="summary-title">

                Submitted Payments

            </div>


            <div class="summary-number">

                <?php

                echo $submitted_count;

                ?>

            </div>

        </div>



        <!-- PENDING -->

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



        <!-- FAILED -->

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



    <!--
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    -->

    <div class="card">


        <?php if (
            count($payments) > 0
        ): ?>


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

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>



                <tbody>


                <?php foreach (
                    $payments as $payment
                ): ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | PAYMENT STATUS
                    |--------------------------------------------------------------------------
                    */

                    $payment_status =
                        strtolower(
                            trim(
                                (string)
                                $payment[
                                    "payment_status"
                                ]
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | SUBSCRIPTION EXISTS?
                    |--------------------------------------------------------------------------
                    */

                    $has_subscription =
                        !empty(
                            $payment[
                                "subscription_id"
                            ]
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | CAN VERIFY?
                    |--------------------------------------------------------------------------
                    */

                    $can_verify =
                        (
                            $payment_status ===
                            "submitted"
                            &&
                            !$has_subscription
                        );


                    ?>


                    <tr>


                        <!-- PAYMENT ID -->

                        <td>

                            <strong>

                                #<?php

                                echo (int)
                                    $payment[
                                        "payment_id"
                                    ];

                                ?>

                            </strong>

                        </td>



                        <!-- OWNER -->

                        <td>

                            <div class="owner">

                                <?php

                                echo e(
                                    $payment[
                                        "owner_name"
                                    ]
                                );

                                ?>

                            </div>


                            <div class="phone">

                                <?php

                                echo e(
                                    $payment[
                                        "owner_phone"
                                    ] ??
                                    "-"
                                );

                                ?>

                            </div>

                        </td>



                        <!-- EMAIL -->

                        <td>

                            <div class="email">

                                <?php

                                echo e(
                                    $payment[
                                        "owner_email"
                                    ]
                                );

                                ?>

                            </div>

                        </td>



                        <!-- GYM -->

                        <td class="gym">

                            <?php

                            echo e(
                                $payment[
                                    "gym_name"
                                ] ??
                                "No gym"
                            );

                            ?>

                        </td>



                        <!-- PACKAGE -->

                        <td>

                            <div class="plan">

                                <?php

                                echo e(
                                    $payment[
                                        "plan_name"
                                    ]
                                );

                                ?>

                            </div>


                            <div class="plan-limit">

                                <?php

                                if (
                                    $payment[
                                        "member_limit"
                                    ] !== null
                                ) {

                                    echo "Up to ";

                                    echo number_format(
                                        (int)
                                        $payment[
                                            "member_limit"
                                        ]
                                    );

                                    echo " members";

                                }
                                else {

                                    echo
                                        "Unlimited members";

                                }

                                ?>

                            </div>

                        </td>



                        <!-- AMOUNT -->

                        <td class="amount">

                            Rs.

                            <?php

                            echo number_format(
                                (float)
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

                            /*
                            |--------------------------------------------------------------------------
                            | IMPORTANT
                            |--------------------------------------------------------------------------
                            |
                            | Your payment table does NOT have payment_date.
                            |
                            | Therefore we use created_at.
                            |
                            |--------------------------------------------------------------------------
                            */

                            $display_date =
                                $payment[
                                    "created_at"
                                ] ?? null;


                            if (
                                !empty(
                                    $display_date
                                )
                            ) {

                                $timestamp =
                                    strtotime(
                                        $display_date
                                    );


                                if (
                                    $timestamp !== false
                                ) {

                                    echo e(
                                        date(
                                            "d M Y h:i A",
                                            $timestamp
                                        )
                                    );

                                }
                                else {

                                    echo "-";

                                }

                            }
                            else {

                                echo "-";

                            }

                            ?>

                        </td>



                        <!-- PAYMENT METHOD -->

                        <td>

                            <?php

                            echo e(
                                ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        (string)
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

                            $payment_status_class =
                                "pending";


                            if (
                                $payment_status ===
                                "paid"
                            ) {

                                $payment_status_class =
                                    "paid";

                            }
                            elseif (
                                $payment_status ===
                                "submitted"
                            ) {

                                $payment_status_class =
                                    "submitted";

                            }
                            elseif (
                                $payment_status ===
                                "failed"
                            ) {

                                $payment_status_class =
                                    "failed";

                            }

                            ?>


                            <span
                                class="
                                    status
                                    <?php
                                    echo $payment_status_class;
                                    ?>
                                "
                            >

                                <?php

                                echo e(
                                    ucfirst(
                                        $payment_status
                                    )
                                );

                                ?>

                            </span>


                        </td>



                        <!-- SUBSCRIPTION -->

                        <td>


                            <?php if (
                                !$has_subscription
                            ): ?>


                                <span
                                    class="
                                        subscription-status
                                        subscription-not-created
                                    "
                                >

                                    Not Created Yet

                                </span>


                                <div
                                    class="
                                        subscription-details
                                    "
                                >

                                    Waiting for
                                    administrator
                                    verification.

                                </div>


                            <?php else: ?>


                                <?php

                                $subscription_status =
                                    strtolower(
                                        trim(
                                            (string)
                                            (
                                                $payment[
                                                    "subscription_status"
                                                ] ?? ""
                                            )
                                        )
                                    );


                                $subscription_class =
                                    "subscription-not-created";


                                switch (
                                    $subscription_status
                                ) {

                                    case "active":

                                        $subscription_class =
                                            "subscription-active";

                                        break;


                                    case "scheduled":

                                        $subscription_class =
                                            "subscription-scheduled";

                                        break;


                                    case "expired":

                                        $subscription_class =
                                            "subscription-expired";

                                        break;


                                    case "cancelled":

                                        $subscription_class =
                                            "subscription-cancelled";

                                        break;

                                }

                                ?>


                                <span
                                    class="
                                        subscription-status
                                        <?php

                                        echo e(
                                            $subscription_class
                                        );

                                        ?>
                                    "
                                >

                                    <?php

                                    echo e(
                                        $subscription_status !== ""
                                            ? ucfirst(
                                                $subscription_status
                                            )
                                            : "Unknown"
                                    );

                                    ?>

                                </span>


                                <div
                                    class="
                                        subscription-details
                                    "
                                >

                                    <strong>
                                        Subscription ID:
                                    </strong>

                                    #<?php

                                    echo (int)
                                        $payment[
                                            "subscription_id"
                                        ];

                                    ?>


                                    <?php if (
                                        !empty(
                                            $payment[
                                                "start_date"
                                            ]
                                        )
                                    ): ?>


                                        <br>


                                        Start:

                                        <?php

                                        $start_timestamp =
                                            strtotime(
                                                $payment[
                                                    "start_date"
                                                ]
                                            );


                                        echo $start_timestamp
                                            ? e(
                                                date(
                                                    "d M Y",
                                                    $start_timestamp
                                                )
                                            )
                                            : "-";

                                        ?>


                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $payment[
                                                "end_date"
                                            ]
                                        )
                                    ): ?>


                                        <br>


                                        End:

                                        <?php

                                        $end_timestamp =
                                            strtotime(
                                                $payment[
                                                    "end_date"
                                                ]
                                            );


                                        echo $end_timestamp
                                            ? e(
                                                date(
                                                    "d M Y",
                                                    $end_timestamp
                                                )
                                            )
                                            : "-";

                                        ?>


                                    <?php endif; ?>


                                </div>


                            <?php endif; ?>


                        </td>



                        <!-- TRANSACTION -->

                        <td class="transaction">


                            <strong>
                                Owner Reference:
                            </strong>


                            <br>


                            <?php

                            echo e(
                                $payment[
                                    "transaction_reference"
                                ] ??
                                "-"
                            );

                            ?>


                            <?php if (
                                !empty(
                                    $payment[
                                        "gateway_transaction_id"
                                    ]
                                )
                            ): ?>


                                <div class="gateway">

                                    <strong>
                                        Gateway:
                                    </strong>


                                    <?php

                                    echo e(
                                        $payment[
                                            "gateway_transaction_id"
                                        ]
                                    );

                                    ?>

                                </div>


                            <?php endif; ?>


                        </td>



                        <!-- ACTION -->

                        <td>


                            <?php if (
                                $can_verify
                            ): ?>


                                <a
                                    href="admin_subscription_create.php?payment_id=<?php

                                        echo (int)
                                            $payment[
                                                "payment_id"
                                            ];

                                    ?>"
                                    class="verify-button"
                                    onclick="return confirm(
                                        'Open this submitted payment for verification and subscription creation?'
                                    );"
                                >

                                    ✓ Verify & Create

                                </a>


                            <?php elseif (
                                $has_subscription
                            ): ?>


                                <span
                                    class="verified-text"
                                >

                                    ✓ Verified

                                </span>


                            <?php elseif (
                                $payment_status ===
                                "paid"
                            ): ?>


                                <span
                                    class="verified-text"
                                >

                                    ✓ Paid

                                </span>


                            <?php else: ?>


                                <span
                                    style="
                                        color:#6b7280;
                                        font-size:12px;
                                    "
                                >

                                    No action

                                </span>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">


                <h2>
                    No Subscription Payments Yet
                </h2>


                <p>

                    Gym owners have not made any
                    subscription payments.

                </p>


            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>