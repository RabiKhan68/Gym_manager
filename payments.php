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


$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Find owner's gym
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        gym_id,
        gym_name

    FROM gyms

    WHERE owner_id = ?

    LIMIT 1
";


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


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load gym."
    );

}


$result = $stmt->get_result();

$gym = $result->fetch_assoc();

$stmt->close();


if (!$gym) {

    die("Gym not found.");

}


$gym_id = (int) $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Current month
|--------------------------------------------------------------------------
|
| Example:
| August 2026 = 2026-08-01
|
*/

$current_month = date("Y-m-01");

$current_month_name = date(
    "F Y",
    strtotime($current_month)
);


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = "";

if (isset($_GET["search"])) {

    $search = trim(
        (string) $_GET["search"]
    );

}


/*
|--------------------------------------------------------------------------
| Get members + membership + current month's payment
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| There are THREE date placeholders:
|
| 1. mm.start_date <= LAST_DAY(?)
| 2. mm.end_date >= ?
| 3. p.payment_for_month = ?
|
| Then:
|
| 4. m.gym_id = ?
|
| If search is used, there are TWO additional placeholders:
|
| 5. m.name LIKE ?
| 6. m.phone LIKE ?
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        m.member_id,
        m.name,
        m.phone,
        m.status,

        mm.membership_id,
        mm.start_date,
        mm.end_date,

        mp.plan_name,
        mp.price,

        p.payment_id,
        p.amount AS paid_amount,
        p.payment_date,
        p.payment_method,
        p.payment_status

    FROM members m

    LEFT JOIN member_memberships mm
        ON m.member_id = mm.member_id

        AND mm.start_date <= LAST_DAY(?)

        AND mm.end_date >= ?

        AND mm.status = 'active'

    LEFT JOIN membership_plans mp
        ON mm.plan_id = mp.plan_id

    LEFT JOIN payments p
        ON p.member_id = m.member_id

        AND p.membership_id = mm.membership_id

        AND p.payment_for_month = ?

        AND p.payment_status = 'paid'

    WHERE m.gym_id = ?

    AND m.status = 'active'
";


/*
|--------------------------------------------------------------------------
| Add search condition
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "
        AND (
            m.name LIKE ?
            OR m.phone LIKE ?
        )
    ";

}


$sql .= "
    ORDER BY
        m.name ASC
";


/*
|--------------------------------------------------------------------------
| Prepare query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| Bind parameters
|--------------------------------------------------------------------------
|
| WITHOUT SEARCH:
|
| s = current month
| s = current month
| s = current month
| i = gym ID
|
| Therefore:
|
| "sssi"
|
|
| WITH SEARCH:
|
| s = current month
| s = current month
| s = current month
| i = gym ID
| s = search
| s = search
|
| Therefore:
|
| "sssiss"
|
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $search_value =
        "%" .
        $search .
        "%";


    $stmt->bind_param(
        "sssiss",
        $current_month,
        $current_month,
        $current_month,
        $gym_id,
        $search_value,
        $search_value
    );

}
else {

    $stmt->bind_param(
        "sssi",
        $current_month,
        $current_month,
        $current_month,
        $gym_id
    );

}


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load payment records."
    );

}


$payments =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Count paid / unpaid
|--------------------------------------------------------------------------
*/

$paid_count = 0;

$unpaid_count = 0;

$total_expected = 0;

$total_collected = 0;

$rows = [];


while (
    $row =
    $payments->fetch_assoc()
) {

    $rows[] = $row;


    /*
    |--------------------------------------------------------------------------
    | PAID
    |--------------------------------------------------------------------------
    */

    if (
        $row["payment_id"] !== null &&
        strtolower(
            trim(
                (string)
                $row["payment_status"]
            )
        ) === "paid"
    ) {

        $paid_count++;


        $total_collected +=
            (float)
            $row["paid_amount"];

    }

    /*
    |--------------------------------------------------------------------------
    | UNPAID
    |--------------------------------------------------------------------------
    */

    elseif (
        $row["membership_id"] !== null
    ) {

        $unpaid_count++;

    }


    /*
    |--------------------------------------------------------------------------
    | EXPECTED
    |--------------------------------------------------------------------------
    */

    if (
        $row["price"] !== null
    ) {

        $total_expected +=
            (float)
            $row["price"];

    }

}


$stmt->close();

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

        Payments -

        <?php

        echo htmlspecialchars(
            $gym["gym_name"]
        );

        ?>

    </title>


    <link
        rel="stylesheet"
        href="css/payments.css"
    >

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

                <img
                    src="images/debit-card.png"
                    alt="debit"
                    class="stat-icon"
                >

                Payments

            </h1>


            <p>

                <?php

                echo htmlspecialchars(
                    $gym["gym_name"]
                );

                ?>

                —

                <?php

                echo htmlspecialchars(
                    $current_month_name
                );

                ?>

            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >

            ← Back to Dashboard

        </a>

    </div>



    <!--
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    -->


    <div class="summary">


        <!-- PAID MEMBERS -->

        <div class="summary-card">

            <div class="summary-title">

                Paid Members

            </div>


            <div class="summary-number paid">

                <?php

                echo $paid_count;

                ?>

            </div>

        </div>



        <!-- UNPAID MEMBERS -->

        <div class="summary-card">

            <div class="summary-title">

                Unpaid Members

            </div>


            <div class="summary-number unpaid">

                <?php

                echo $unpaid_count;

                ?>

            </div>

        </div>



        <!-- EXPECTED -->

        <div class="summary-card">

            <div class="summary-title">

                Expected

            </div>


            <div class="summary-number">

                Rs.

                <?php

                echo number_format(
                    $total_expected,
                    2
                );

                ?>

            </div>

        </div>



        <!-- COLLECTED -->

        <div class="summary-card">

            <div class="summary-title">

                Collected

            </div>


            <div class="summary-number paid">

                Rs.

                <?php

                echo number_format(
                    $total_collected,
                    2
                );

                ?>

            </div>

        </div>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    -->


    <div class="search-box">


        <form method="GET">


            <input
                type="text"
                name="search"
                placeholder="Search member by name or phone..."
                value="<?php

                    echo htmlspecialchars(
                        $search
                    );

                ?>"
            >


            <button
                type="submit"
                class="search-button"
            >

                Search

            </button>


            <?php if (
                $search !== ""
            ): ?>


                <a
                    href="payments.php"
                    class="clear-button"
                >

                    Clear

                </a>


            <?php endif; ?>


        </form>


    </div>



    <!--
    |--------------------------------------------------------------------------
    | PAYMENT TABLE
    |--------------------------------------------------------------------------
    -->


    <div class="table-card">


        <?php if (
            count($rows) > 0
        ): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Fee
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Payment Date
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $rows as $row
                ): ?>


                    <tr>


                        <!--
                        ------------------------------------------------------
                        MEMBER
                        ------------------------------------------------------
                        -->


                        <td>

                            <a
                                href="member_details.php?id=<?php

                                    echo (int)
                                        $row["member_id"];

                                ?>"
                                class="member-button"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $row["name"]
                                );

                                ?>

                            </a>

                        </td>



                        <!--
                        ------------------------------------------------------
                        PHONE
                        ------------------------------------------------------
                        -->


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row["phone"] ??
                                "-"
                            );

                            ?>

                        </td>



                        <!--
                        ------------------------------------------------------
                        PLAN
                        ------------------------------------------------------
                        -->


                        <td>


                            <?php if (
                                $row["plan_name"]
                            ): ?>


                                <?php

                                echo htmlspecialchars(
                                    $row["plan_name"]
                                );

                                ?>


                            <?php else: ?>


                                <span
                                    class="no-membership"
                                >

                                    No Membership

                                </span>


                            <?php endif; ?>


                        </td>



                        <!--
                        ------------------------------------------------------
                        FEE
                        ------------------------------------------------------
                        -->


                        <td>


                            <?php if (
                                $row["price"] !== null
                            ): ?>


                                Rs.

                                <?php

                                echo number_format(
                                    (float)
                                    $row["price"],
                                    2
                                );

                                ?>


                            <?php else: ?>


                                -


                            <?php endif; ?>


                        </td>



                        <!--
                        ------------------------------------------------------
                        STATUS
                        ------------------------------------------------------
                        -->


                        <td>


                            <?php if (
                                $row["payment_id"] !== null &&
                                strtolower(
                                    trim(
                                        (string)
                                        $row["payment_status"]
                                    )
                                ) === "paid"
                            ): ?>


                                <span class="paid">

                                    🟢 PAID

                                </span>


                            <?php elseif (
                                $row["membership_id"] !== null
                            ): ?>


                                <span class="unpaid">

                                    🔴 UNPAID

                                </span>


                            <?php else: ?>


                                <span
                                    class="no-membership"
                                >

                                    No Plan

                                </span>


                            <?php endif; ?>


                        </td>



                        <!--
                        ------------------------------------------------------
                        PAYMENT DATE
                        ------------------------------------------------------
                        -->


                        <td>


                            <?php if (
                                !empty(
                                    $row["payment_date"]
                                )
                            ): ?>


                                <?php

                                $payment_timestamp =
                                    strtotime(
                                        $row["payment_date"]
                                    );


                                if (
                                    $payment_timestamp
                                ) {

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y, h:i A",
                                            $payment_timestamp
                                        )
                                    );

                                }
                                else {

                                    echo "-";

                                }

                                ?>


                            <?php else: ?>


                                -


                            <?php endif; ?>


                        </td>



                        <!--
                        ------------------------------------------------------
                        ACTION
                        ------------------------------------------------------
                        -->


                        <td>


                            <?php if (
                                $row["payment_id"] !== null &&
                                strtolower(
                                    trim(
                                        (string)
                                        $row["payment_status"]
                                    )
                                ) === "paid"
                            ): ?>


                                <a
                                    href="payment_receipt.php?id=<?php

                                        echo (int)
                                            $row["payment_id"];

                                    ?>"
                                    target="_blank"
                                    class="receipt-button"
                                >

                                    <img
                                        src="images/receipt.png"
                                        alt="receipt"
                                        class="stat-icon"
                                    >

                                    Receipt

                                </a>


                            <?php elseif (
                                $row["membership_id"] !== null
                            ): ?>


                                <a
                                    href="record_payment.php?member_id=<?php

                                        echo (int)
                                            $row["member_id"];

                                    ?>"
                                    class="pay-button"
                                >

                                    💰 Record Payment

                                </a>


                            <?php else: ?>


                                -


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                No active members found.

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>