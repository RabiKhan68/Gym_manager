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
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");


/*
|--------------------------------------------------------------------------
| Get payments
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

            g.gym_id,
            g.gym_name,

            mp.plan_name

        FROM payments p

        INNER JOIN members m
            ON p.member_id = m.member_id

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        LEFT JOIN member_memberships mm
            ON p.membership_id = mm.membership_id

        LEFT JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= " WHERE

                m.name LIKE ?

                OR m.email LIKE ?

                OR g.gym_name LIKE ?

                OR p.transaction_reference LIKE ?

                OR p.payment_method LIKE ?

                OR p.payment_status LIKE ?";

}


$sql .= " ORDER BY p.payment_id DESC";


$stmt = $conn->prepare($sql);


if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "ssssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

}


$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Payment summary
|--------------------------------------------------------------------------
*/

$total_amount = 0;
$paid_count = 0;
$pending_count = 0;
$failed_count = 0;


foreach ($result as $row) {

    $total_amount += (float)$row["amount"];

    if ($row["payment_status"] === "paid") {

        $paid_count++;

    }

    elseif ($row["payment_status"] === "pending") {

        $pending_count++;

    }

    elseif ($row["payment_status"] === "failed") {

        $failed_count++;

    }

}


/*
|--------------------------------------------------------------------------
| Run query again for table
|--------------------------------------------------------------------------
*/

$stmt->execute();

$result = $stmt->get_result();

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
        Payments
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f8;

            color: #1f2937;

        }


        .container {

            max-width: 1500px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content: space-between;

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

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

        }


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


        .search-card {

            background: white;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 20px;

        }


        .search-form {

            display: flex;

            gap: 10px;

        }


        .search-input {

            flex: 1;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

        }


        .search-button {

            padding: 12px 20px;

            border: none;

            background: #111827;

            color: white;

            border-radius: 8px;

            cursor: pointer;

        }


        .clear-button {

            display: inline-flex;

            align-items: center;

            padding: 12px 18px;

            background: #e5e7eb;

            color: #111827;

            text-decoration: none;

            border-radius: 8px;

        }


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

            min-width: 1200px;

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


        .member {

            font-weight: bold;

        }


        .gym {

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


        .method {

            text-transform: capitalize;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #6b7280;

        }


        .count {

            margin-bottom: 15px;

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


            .search-form {

                flex-direction: column;

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
                Payments
            </h1>

            <p>
                Monitor payments across all gyms
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
                    $total_amount,
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



    <!-- SEARCH -->

    <div class="search-card">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search member, gym, transaction, method or status..."
                value="<?php echo htmlspecialchars($search); ?>"
            >


            <button
                type="submit"
                class="search-button"
            >

                Search

            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="admin_payments.php"
                    class="clear-button"
                >

                    Clear

                </a>

            <?php endif; ?>


        </form>

    </div>



    <!-- PAYMENT TABLE -->

    <div class="card">


        <div class="count">

            <?php

            echo $result->num_rows;

            ?>

            payment(s) found.

        </div>


        <?php if ($result->num_rows > 0): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Member
                        </th>

                        <th>
                            Gym
                        </th>

                        <th>
                            Membership
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            For Month
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
                            Transaction
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $payment =
                    $result->fetch_assoc()
                ): ?>


                    <tr>


                        <td>

                            <?php

                            echo (int)
                                $payment["payment_id"];

                            ?>

                        </td>


                        <td class="member">

                            <?php

                            echo htmlspecialchars(
                                $payment["member_name"]
                            );

                            ?>

                        </td>


                        <td class="gym">

                            <?php

                            echo htmlspecialchars(
                                $payment["gym_name"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $payment["plan_name"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td class="amount">

                            Rs.

                            <?php

                            echo number_format(
                                $payment["amount"],
                                2
                            );

                            ?>

                        </td>


                        <td class="date">

                            <?php

                            echo date(
                                "M Y",
                                strtotime(
                                    $payment[
                                        "payment_for_month"
                                    ]
                                )
                            );

                            ?>

                        </td>


                        <td class="date">

                            <?php

                            if (
                                !empty(
                                    $payment["payment_date"]
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


                        <td class="method">

                            <?php

                            echo htmlspecialchars(
                                $payment["payment_method"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            $status =
                                $payment[
                                    "payment_status"
                                ];

                            if (
                                $status === "paid"
                            ) {

                                echo '<span class="paid">
                                        Paid
                                      </span>';

                            }

                            elseif (
                                $status === "pending"
                            ) {

                                echo '<span class="pending">
                                        Pending
                                      </span>';

                            }

                            elseif (
                                $status === "failed"
                            ) {

                                echo '<span class="failed">
                                        Failed
                                      </span>';

                            }

                            else {

                                echo htmlspecialchars(
                                    $status ?? "-"
                                );

                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $payment[
                                    "transaction_reference"
                                ]
                                ?? "-"
                            );

                            ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                <?php if ($search !== ""): ?>

                    No payments found for:

                    <strong>
                        <?php

                        echo htmlspecialchars(
                            $search
                        );

                        ?>
                    </strong>

                <?php else: ?>

                    No payments found.

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>