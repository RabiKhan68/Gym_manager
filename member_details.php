<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Check member ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {

    die("Member ID is required.");

}

$member_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Find owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            gym_id,
            gym_name,
            phone,
            address

        FROM gyms

        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$gym_result = $stmt->get_result();

$gym = $gym_result->fetch_assoc();


if (!$gym) {

    die("Gym not found.");

}


$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Get member
|--------------------------------------------------------------------------
|
| We check both member_id and gym_id.
|
| This prevents an owner from viewing another gym's member
| by manually changing the URL.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            member_id,
            member_number,
            name,
            phone,
            email,
            joining_date,
            status

        FROM members

        WHERE member_id = ?

        AND gym_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $gym_id
);

$stmt->execute();

$member_result = $stmt->get_result();

$member = $member_result->fetch_assoc();


if (!$member) {

    die("Member not found.");

}


/*
|--------------------------------------------------------------------------
| Current date/month
|--------------------------------------------------------------------------
*/

$current_month = date("Y-m-01");

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Get current membership
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            mm.membership_id,
            mm.start_date,
            mm.end_date,
            mm.status AS membership_status,

            mp.plan_name,
            mp.price,
            mp.duration_months,
            mp.description

        FROM member_memberships mm

        INNER JOIN membership_plans mp

            ON mm.plan_id = mp.plan_id

        WHERE mm.member_id = ?

        AND mm.start_date <= LAST_DAY(?)

        AND mm.end_date >= ?

        AND mm.status = 'active'

        ORDER BY mm.membership_id DESC

        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $member_id,
    $current_month,
    $current_month
);

$stmt->execute();

$membership_result = $stmt->get_result();

$current_membership =
    $membership_result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Current month's payment
|--------------------------------------------------------------------------
*/

$current_payment = null;


if ($current_membership) {

    $membership_id =
        $current_membership["membership_id"];


    $sql = "SELECT

                payment_id,
                amount,
                payment_date,
                payment_method,
                payment_status,
                transaction_reference

            FROM payments

            WHERE member_id = ?

            AND membership_id = ?

            AND payment_for_month = ?

            ORDER BY payment_id DESC

            LIMIT 1";


    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "iis",
        $member_id,
        $membership_id,
        $current_month
    );

    $stmt->execute();

    $payment_result =
        $stmt->get_result();

    $current_payment =
        $payment_result->fetch_assoc();

}


/*
|--------------------------------------------------------------------------
| Payment history
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            p.payment_id,
            p.amount,
            p.payment_for_month,
            p.payment_date,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,

            mp.plan_name

        FROM payments p

        LEFT JOIN member_memberships mm

            ON p.membership_id =
               mm.membership_id

        LEFT JOIN membership_plans mp

            ON mm.plan_id =
               mp.plan_id

        WHERE p.member_id = ?

        ORDER BY
            p.payment_for_month DESC,
            p.payment_id DESC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$payments =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Attendance history
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            attendance_date,
            attendance_time

        FROM attendance

        WHERE member_id = ?

        ORDER BY
            attendance_date DESC,
            attendance_time DESC

        LIMIT 100";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$attendance =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Current month's attendance count
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            COUNT(*) AS total

        FROM attendance

        WHERE member_id = ?

        AND attendance_date >= ?

        AND attendance_date <= ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $member_id,
    $current_month,
    $today
);

$stmt->execute();

$attendance_count_result =
    $stmt->get_result();

$attendance_count =
    $attendance_count_result->fetch_assoc();

$total_attendance =
    $attendance_count["total"];


/*
|--------------------------------------------------------------------------
| Prepare phone numbers
|--------------------------------------------------------------------------
*/

/*
 * Member's phone is the WhatsApp recipient.
 */

$member_whatsapp = preg_replace(
    "/[^0-9]/",
    "",
    $member["phone"] ?? ""
);


/*
 * Convert Pakistani number:
 *
 * 03001234567
 *
 * to:
 *
 * 923001234567
 */

if (
    substr($member_whatsapp, 0, 1) === "0"
) {

    $member_whatsapp =
        "92" .
        substr($member_whatsapp, 1);

}


/*
 * Gym phone is included as contact information.
 */

$gym_phone =
    $gym["phone"] ?? "";


/*
|--------------------------------------------------------------------------
| Create WhatsApp message function
|--------------------------------------------------------------------------
*/

function createWhatsAppUrl(
    $memberName,
    $gymName,
    $gymPhone,
    $planName,
    $month,
    $amount,
    $paymentId
) {

    $message =
        "Assalam-o-Alaikum "
        . $memberName
        . ",\n\n"

        . "Your gym payment has been received.\n\n"

        . "Gym: "
        . $gymName
        . "\n"

        . "Membership: "
        . $planName
        . "\n"

        . "Payment For: "
        . $month
        . "\n"

        . "Amount: Rs. "
        . number_format(
            $amount,
            2
        )
        . "\n"

        . "Status: PAID\n"

        . "Receipt No: #"
        . $paymentId
        . "\n\n"

        . "Gym Contact: "
        . $gymPhone
        . "\n\n"

        . "Thank you for being a member of "
        . $gymName
        . ".";


    return
        "https://wa.me/"
        . $GLOBALS["member_whatsapp"]
        . "?text="
        . urlencode($message);
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
        Member Details
    </title>

    <link rel = "stylesheet" href = "css/member_details.css">
</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>

                <?php

                echo htmlspecialchars(
                    $member["name"]
                );

                ?>

            </h1>


            <?php if (
                $member["status"]
                === "active"
            ): ?>

                <span class="active">

                    <img src = "images/circle.png" class="stat-icon" alt="Gym">
                Active

                </span>

            <?php else: ?>

                <span class="inactive">

                    <img src = "images/delete.png" class="stat-icon" alt="Gym">
                Inactive

                </span>

            <?php endif; ?>

        </div>


        <div>

            <a
                href="members.php"
                class="back"
            >

                ← Back to Members

            </a>

        </div>

    </div>



    <div class="grid">


        <!-- MEMBER INFORMATION -->

        <div class="card">

            <h2>
                <img src = "images/group-users.png" class="stat-icon" alt="Gym">
            Personal Information
            </h2>

            <div class="info-row">

    <span class="label">
        Member #
    </span>

    <strong>

        <?php

        echo htmlspecialchars(
            $member["member_number"]
        );

        ?>

    </strong>

</div>

            <div class="info-row">

                <span class="label">
                    Name
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $member["name"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-row">

                <span class="label">
                    Phone
                </span>

                <span>

                    <?php

                    echo htmlspecialchars(
                        $member["phone"]
                        ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    Email
                </span>

                <span>

                    <?php

                    echo htmlspecialchars(
                        $member["email"]
                        ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    Joining Date
                </span>

                <span>

                    <?php

if (
    !empty($member["joining_date"])
) {

    echo htmlspecialchars(
        $member["joining_date"]
    );

} else {

    echo "—";

}

?>

                </span>

            </div>

        </div>



        <!-- ATTENDANCE -->

        <div class="card">

            <h2>
                <img src = "images/calendar.png" class="stat-icon" alt="Gym">
            Attendance
            </h2>


            <p>
                Attendance this month
            </p>


            <div class="stat">

                <?php

                echo $total_attendance;

                ?>

            </div>


            <p>
                visits
            </p>

        </div>



        <!-- CURRENT MEMBERSHIP -->

        <div class="card">

            <h2>
                <img src = "images/gym.png" class="stat-icon" alt="Gym">
            Current Membership
            </h2>


            <?php if (
                $current_membership
            ): ?>


                <div class="info-row">

                    <span class="label">
                        Plan
                    </span>

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $current_membership[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                </div>


                <div class="info-row">

                    <span class="label">
                        Price
                    </span>

                    <strong>

                        Rs.

                        <?php

                        echo number_format(
                            $current_membership[
                                "price"
                            ],
                            2
                        );

                        ?>

                    </strong>

                </div>


                <div class="info-row">

                    <span class="label">
                        Start
                    </span>

                    <span>

                        <?php

                        echo
                            $current_membership[
                                "start_date"
                            ];

                        ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="label">
                        End
                    </span>

                    <span>

                        <?php

                        echo
                            $current_membership[
                                "end_date"
                            ];

                        ?>

                    </span>

                </div>


            <?php else: ?>


                <p class="no-data">

                    No active membership.

                </p>


                <a
                    href="assign_membership.php"
                    class="button"
                >

                    Assign Membership

                </a>


            <?php endif; ?>

        </div>



        <!-- CURRENT PAYMENT -->

        <div class="card">

            <h2>
                <img src = "images/debit-card.png" class="revenue-icon" alt="Gym">
            This Month's Payment
            </h2>


            <?php if (
                $current_payment
                &&
                $current_payment[
                    "payment_status"
                ] === "paid"
            ): ?>


                <p class="paid">

                    <img src = "images/money.png" class="stat-icon" alt="Gym">
                PAID

                </p>


                <div class="info-row">

                    <span class="label">
                        Amount
                    </span>

                    <strong>

                        Rs.

                        <?php

                        echo number_format(
                            $current_payment[
                                "amount"
                            ],
                            2
                        );

                        ?>

                    </strong>

                </div>


                <div class="info-row">

                    <span class="label">
                        Payment Date
                    </span>

                    <span>

                        <?php

                        echo
                            $current_payment[
                                "payment_date"
                            ];

                        ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="label">
                        Method
                    </span>

                    <span>

                        <?php

                        echo htmlspecialchars(
                            $current_payment[
                                "payment_method"
                            ]
                        );

                        ?>

                    </span>

                </div>


                <br>


                <div class="action-buttons">

                    <a
                        href="payment_receipt.php?id=<?php
                            echo $current_payment[
                                "payment_id"
                            ];
                        ?>"
                        target="_blank"
                        class="receipt-button"
                    >

                        <img src = "images/receipt.png" class="stat-icon" alt="Gym">
                    View Receipt

                    </a>


                    <?php if (
                        !empty(
                            $member_whatsapp
                        )
                    ): ?>

                        <?php

                        $current_whatsapp_url =
                            createWhatsAppUrl(

                                $member["name"],

                                $gym["gym_name"],

                                $gym_phone,

                                $current_membership[
                                    "plan_name"
                                ] ?? "-",

                                date(
                                    "F Y",
                                    strtotime(
                                        $current_month
                                    )
                                ),

                                $current_payment[
                                    "amount"
                                ],

                                $current_payment[
                                    "payment_id"
                                ]

                            );

                        ?>


                        <a
                            href="<?php
                                echo htmlspecialchars(
                                    $current_whatsapp_url
                                );
                            ?>"
                            target="_blank"
                            class="whatsapp-button"
                        >

                            <img src = "images/whatsapp.png" class = "stat-icon" alt = "whatsapp">
                        WhatsApp

                        </a>

                    <?php endif; ?>

                </div>


            <?php else: ?>


                <p class="unpaid">

                    <img src = "images/delete.png" class = "stat-icon" alt = "whatsapp">
                UNPAID

                </p>


                <p>

                    No paid payment has been
                    recorded for this month.

                </p>


            <?php endif; ?>

        </div>



        <!-- PAYMENT HISTORY -->

        <div class="card full">

            <h2>
                <img src = "images/money.png" class = "stat-icon" alt = "whatsapp">
            Payment History
            </h2>


            <?php if (
                $payments->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Month
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>


                    <?php while (
                        $payment =
                        $payments->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo date(
                                    "F Y",
                                    strtotime(
                                        $payment[
                                            "payment_for_month"
                                        ]
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "plan_name"
                                    ] ?? "-"
                                );

                                ?>

                            </td>


                            <td>

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


                            <td>

                                <?php

                                echo
                                    $payment[
                                        "payment_date"
                                    ];

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

                                <?php if (
                                    $payment[
                                        "payment_status"
                                    ] === "paid"
                                ): ?>

                                    <span
                                        class="active"
                                    >

                                        🟢 Paid

                                    </span>

                                <?php elseif (
                                    $payment[
                                        "payment_status"
                                    ] === "pending"
                                ): ?>

                                    🟠 Pending

                                <?php else: ?>

                                    <span
                                        class="inactive"
                                    >

                                        🔴 Failed

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    $payment[
                                        "payment_status"
                                    ] === "paid"
                                ): ?>


                                    <div
                                        class="action-buttons"
                                    >

                                        <!-- RECEIPT -->

                                        <a
                                            href="payment_receipt.php?id=<?php
                                                echo $payment[
                                                    "payment_id"
                                                ];
                                            ?>"
                                            target="_blank"
                                            class="receipt-button"
                                        >

                                            <img src = "images/receipt.png" class = "stat-icon" alt = "whatsapp">
                                        Receipt

                                        </a>


                                        <!-- WHATSAPP -->

                                        <?php if (
                                            !empty(
                                                $member_whatsapp
                                            )
                                        ): ?>


                                            <?php

                                            $whatsapp_url =
                                                createWhatsAppUrl(

                                                    $member[
                                                        "name"
                                                    ],

                                                    $gym[
                                                        "gym_name"
                                                    ],

                                                    $gym_phone,

                                                    $payment[
                                                        "plan_name"
                                                    ] ?? "-",

                                                    date(
                                                        "F Y",
                                                        strtotime(
                                                            $payment[
                                                                "payment_for_month"
                                                            ]
                                                        )
                                                    ),

                                                    $payment[
                                                        "amount"
                                                    ],

                                                    $payment[
                                                        "payment_id"
                                                    ]

                                                );

                                            ?>


                                            <a
                                                href="<?php
                                                    echo htmlspecialchars(
                                                        $whatsapp_url
                                                    );
                                                ?>"
                                                target="_blank"
                                                class="whatsapp-button"
                                            >

                                                <img src = "images/whatsapp.png" class = "stat-icon" alt = "whatsapp">
                                            WhatsApp

                                            </a>


                                        <?php endif; ?>


                                    </div>


                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="no-data">

                    No payment history found.

                </p>


            <?php endif; ?>


        </div>



        <!-- ATTENDANCE HISTORY -->

        <div class="card full">

            <h2>
                <img src = "images/calendar.png" class = "stat-icon" alt = "whatsapp">
            Attendance History
            </h2>


            <?php if (
                $attendance->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
                        </th>

                    </tr>


                    <?php while (
                        $record =
                        $attendance->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo date(
                                    "d F Y",
                                    strtotime(
                                        $record[
                                            "attendance_date"
                                        ]
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $record[
                                            "attendance_time"
                                        ]
                                    )
                                );

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="no-data">

                    No attendance records found.

                </p>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>