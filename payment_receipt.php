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
| Check payment ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {

    die("Payment ID is required.");

}

$payment_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get payment
|--------------------------------------------------------------------------
|
| We verify that the payment belongs to a member
| inside the logged-in owner's gym.
|
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

            m.member_id,
            m.name AS member_name,
            m.phone AS member_phone,
            m.email AS member_email,

            mp.plan_name,

            g.gym_id,
            g.gym_name,
            g.address AS gym_address,
            g.phone AS gym_phone

        FROM payments p

        INNER JOIN members m
            ON p.member_id = m.member_id

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        LEFT JOIN member_memberships mm
            ON p.membership_id = mm.membership_id

        LEFT JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        WHERE p.payment_id = ?

        AND g.owner_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $payment_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$payment = $result->fetch_assoc();


if (!$payment) {

    die("Payment not found.");

}

/*
|--------------------------------------------------------------------------
| Prepare WhatsApp information
|--------------------------------------------------------------------------
*/

$member_phone = $payment["member_phone"] ?? "";

$member_phone = preg_replace(
    "/[^0-9]/",
    "",
    $member_phone
);


/*
| Convert Pakistani number:
| 03001234567
| ->
| 923001234567
*/

if (
    substr($member_phone, 0, 1) === "0"
) {

    $member_phone =
        "92" .
        substr($member_phone, 1);

}


/*
|--------------------------------------------------------------------------
| WhatsApp message
|--------------------------------------------------------------------------
*/

$whatsapp_message =
    "Payment Receipt\n\n" .

    "Gym: " .
    $payment["gym_name"] .
    "\n" .

    "Member: " .
    $payment["member_name"] .
    "\n" .

    "Receipt No: #" .
    $payment["payment_id"] .
    "\n" .

    "Membership Plan: " .
    ($payment["plan_name"] ?? "-") .
    "\n" .

    "Payment For: " .
    date(
        "F Y",
        strtotime(
            $payment["payment_for_month"]
        )
    ) .
    "\n" .

    "Amount Paid: Rs. " .
    number_format(
        $payment["amount"],
        2
    ) .
    "\n" .

    "Payment Method: " .
    strtoupper(
        $payment["payment_method"]
    ) .
    "\n" .

    "Payment Date: " .
    date(
        "d F Y, h:i A",
        strtotime(
            $payment["payment_date"]
        )
    ) .
    "\n\n" .

    "Thank you for being a member of " .
    $payment["gym_name"] .
    ".";

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
        Payment Receipt
    </title>


    <style>

        body {

            margin: 0;

            padding: 30px;

            background: #f3f4f6;

            font-family:
                Arial,
                sans-serif;

        }


        .receipt {

            width: 700px;

            max-width: 100%;

            margin: auto;

            background: white;

            padding: 40px;

            box-sizing: border-box;

            border-radius: 10px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.1);

        }


        .gym-header {

            text-align: center;

            border-bottom:
                2px solid #222;

            padding-bottom: 20px;

            margin-bottom: 25px;

        }


        .gym-header h1 {

            margin: 0;

            font-size: 30px;

        }


        .gym-header p {

            margin: 5px 0;

            color: #666;

        }


        .receipt-title {

            text-align: center;

            font-size: 22px;

            font-weight: bold;

            margin-bottom: 25px;

        }


        .row {

            display: flex;

            justify-content:
                space-between;

            padding: 10px 0;

            border-bottom:
                1px solid #eee;

        }


        .label {

            color: #666;

        }


        .value {

            font-weight: bold;

            text-align: right;

        }


        .amount {

            font-size: 28px;

            text-align: center;

            margin: 25px 0;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .pending {

            color: orange;

            font-weight: bold;

        }


        .failed {

            color: red;

            font-weight: bold;

        }


        .footer {

            text-align: center;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #ddd;

            color: #777;

            font-size: 13px;

        }


        .buttons {

            width: 700px;

            max-width: 100%;

            margin: 20px auto;

            text-align: center;

        }


        button,
        .button {

            padding: 11px 18px;

            border: none;

            border-radius: 6px;

            background: #111827;

            color: white;

            text-decoration: none;

            cursor: pointer;

            margin: 5px;

            display: inline-block;

        }

        .whatsapp {
    background: #25D366;
}


        @media print {

            body {

                background: white;

                padding: 0;

            }


            .receipt {

                width: 100%;

                box-shadow: none;

                border-radius: 0;

            }


            .buttons {

                display: none;

            }

        }

    </style>

</head>


<body>


<div class="receipt">


    <!-- GYM INFORMATION -->

    <div class="gym-header">

        <h1>

            <?php

            echo htmlspecialchars(
                $payment["gym_name"]
            );

            ?>

        </h1>


        <?php if (
            !empty($payment["gym_address"])
        ): ?>

            <p>

                <?php

                echo htmlspecialchars(
                    $payment["gym_address"]
                );

                ?>

            </p>

        <?php endif; ?>


        <?php if (
            !empty($payment["gym_phone"])
        ): ?>

            <p>

                Phone:

                <?php

                echo htmlspecialchars(
                    $payment["gym_phone"]
                );

                ?>

            </p>

        <?php endif; ?>

    </div>



    <div class="receipt-title">

        PAYMENT RECEIPT

    </div>



    <!-- PAYMENT STATUS -->

    <div class="row">

        <span class="label">
            Payment Status
        </span>


        <span class="value">

            <?php if (
                $payment["payment_status"]
                === "paid"
            ): ?>

                <span class="paid">
                    PAID
                </span>

            <?php elseif (
                $payment["payment_status"]
                === "pending"
            ): ?>

                <span class="pending">
                    PENDING
                </span>

            <?php else: ?>

                <span class="failed">
                    FAILED
                </span>

            <?php endif; ?>

        </span>

    </div>



    <!-- PAYMENT ID -->

    <div class="row">

        <span class="label">
            Receipt No.
        </span>

        <span class="value">

            #<?php

            echo $payment["payment_id"];

            ?>

        </span>

    </div>



    <!-- MEMBER -->

    <div class="row">

        <span class="label">
            Member
        </span>

        <span class="value">

            <?php

            echo htmlspecialchars(
                $payment["member_name"]
            );

            ?>

        </span>

    </div>



    <!-- PHONE -->

    <div class="row">

        <span class="label">
            Phone
        </span>

        <span class="value">

            <?php

            echo htmlspecialchars(
                $payment["member_phone"]
                ?? "-"
            );

            ?>

        </span>

    </div>



    <!-- PLAN -->

    <div class="row">

        <span class="label">
            Membership Plan
        </span>

        <span class="value">

            <?php

            echo htmlspecialchars(
                $payment["plan_name"]
                ?? "-"
            );

            ?>

        </span>

    </div>



    <!-- MONTH -->

    <div class="row">

        <span class="label">
            Payment For
        </span>

        <span class="value">

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

        </span>

    </div>



    <!-- DATE -->

    <div class="row">

        <span class="label">
            Payment Date
        </span>

        <span class="value">

            <?php

            echo date(
                "d F Y, h:i A",
                strtotime(
                    $payment[
                        "payment_date"
                    ]
                )
            );

            ?>

        </span>

    </div>



    <!-- METHOD -->

    <div class="row">

        <span class="label">
            Payment Method
        </span>

        <span class="value">

            <?php

            echo strtoupper(
                htmlspecialchars(
                    $payment[
                        "payment_method"
                    ]
                )
            );

            ?>

        </span>

    </div>



    <!-- TRANSACTION -->

    <?php if (
        !empty(
            $payment[
                "transaction_reference"
            ]
        )
    ): ?>

        <div class="row">

            <span class="label">
                Transaction Reference
            </span>

            <span class="value">

                <?php

                echo htmlspecialchars(
                    $payment[
                        "transaction_reference"
                    ]
                );

                ?>

            </span>

        </div>

    <?php endif; ?>



    <!-- AMOUNT -->

    <div class="amount">

        <div>

            Amount Paid

        </div>


        <strong>

            Rs.

            <?php

            echo number_format(
                $payment["amount"],
                2
            );

            ?>

        </strong>

    </div>



    <div class="footer">

        Thank you for being a member of
        <?php

        echo htmlspecialchars(
            $payment["gym_name"]
        );

        ?>.

        <br>

        Please keep this receipt for your records.

    </div>


</div>



<!-- BUTTONS -->

<div class="buttons">

    <button
        onclick="window.print()"
    >

        🖨 Print Receipt

    </button>


    <?php if (
        $payment["payment_status"] === "paid"
        &&
        !empty($member_phone)
    ): ?>

        <a
            href="https://wa.me/<?php
                echo $member_phone;
            ?>?text=<?php
                echo rawurlencode(
                    $whatsapp_message
                );
            ?>"
            target="_blank"
            class="button whatsapp"
        >

            💬 WhatsApp Receipt

        </a>

    <?php endif; ?>


    <a
        href="member_details.php?id=<?php
            echo $payment["member_id"];
        ?>"
        class="button"
    >

        ← Back to Member

    </a>

</div>


</body>

</html>