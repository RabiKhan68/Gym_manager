<?php

session_start();

require_once "backend/db.php";

/*
    Automatically expire old memberships.
*/

// $sql = "UPDATE member_memberships
//         SET status = 'expired'
//         WHERE end_date < CURDATE()
//         AND status = 'active'";

// $conn->query($sql);


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
| Find owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            gym_id,
            gym_name
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

    die("You have not created a gym yet.");

}


$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = "";

if (isset($_GET["search"])) {

    $search = trim($_GET["search"]);

}


/*
|--------------------------------------------------------------------------
| Current month
|--------------------------------------------------------------------------
|
| Example:
| 2026-08-01
|
|--------------------------------------------------------------------------
*/

$current_month = date("Y-m-01");


/*
|--------------------------------------------------------------------------
| Get members
|--------------------------------------------------------------------------
|
| We get:
|
| - Member information
| - Current membership
| - Membership plan
| - Current month's payment
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            m.member_id,
            m.name,
            m.phone,
            m.email,
            m.joining_date,
            m.status,

            mm.membership_id,
            mm.start_date,
            mm.end_date,
            mm.status AS membership_status,

            mp.plan_name,
            mp.price,

            p.payment_status

        FROM members m

        LEFT JOIN member_memberships mm

            ON mm.membership_id = (

                SELECT mm2.membership_id

                FROM member_memberships mm2

                WHERE mm2.member_id = m.member_id

                AND mm2.start_date <= LAST_DAY(?)

                AND mm2.end_date >= ?

                ORDER BY mm2.end_date DESC,
                         mm2.membership_id DESC

                LIMIT 1

            )

        LEFT JOIN membership_plans mp

            ON mm.plan_id = mp.plan_id

        LEFT JOIN payments p

            ON mm.membership_id = p.membership_id

            AND p.payment_for_month = ?

            AND p.payment_status = 'paid'

        WHERE m.gym_id = ?

        AND (

            m.name LIKE ?
            OR m.phone LIKE ?
            OR m.email LIKE ?

        )

        ORDER BY m.member_id DESC";


$stmt = $conn->prepare($sql);


$search_value = "%" . $search . "%";


$stmt->bind_param(

    "sssisss",

    $current_month,
    $current_month,
    $current_month,

    $gym_id,

    $search_value,
    $search_value,
    $search_value

);


$stmt->execute();

$members = $stmt->get_result();


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
        Members
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 30px;

            font-family:
                Arial,
                sans-serif;

            background: #f4f6f8;

            color: #222;

        }


        .container {

            max-width: 1300px;

            margin: auto;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 25px;

            flex-wrap: wrap;

            gap: 15px;

        }


        .header h1 {

            margin: 0;

        }


        .actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        .button {

            display: inline-block;

            padding: 10px 16px;

            border-radius: 7px;

            text-decoration: none;

            background: #111827;

            color: white;

        }


        .button:hover {

            opacity: 0.9;

        }


        .search-box {

            background: white;

            padding: 20px;

            border-radius: 10px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .search-box form {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        .search-box input {

            padding: 10px;

            width: 300px;

            max-width: 100%;

            border: 1px solid #ccc;

            border-radius: 6px;

        }


        .search-box button {

            padding: 10px 16px;

            border: none;

            border-radius: 6px;

            background: #2563eb;

            color: white;

            cursor: pointer;

        }


        .clear {

            display: inline-flex;

            align-items: center;

            padding: 10px 15px;

            text-decoration: none;

            color: #555;

        }


        .table-container {

            background: white;

            border-radius: 10px;

            overflow-x: auto;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;

        }


        th,
        td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #eee;

        }


        th {

            background: #f8fafc;

        }


        tr:hover {

            background: #fafafa;

        }


        .active {

            color: #16a34a;

            font-weight: bold;

        }


        .inactive {

            color: #dc2626;

            font-weight: bold;

        }


        .expiry-active {

            color: #16a34a;

            font-weight: bold;

        }


        .expiry-soon {

            color: #d97706;

            font-weight: bold;

        }


        .expiry-expired {

            color: #dc2626;

            font-weight: bold;

        }


        .no-membership {

            color: #6b7280;

        }


        .paid {

            color: #16a34a;

            font-weight: bold;

        }


        .unpaid {

            color: #dc2626;

            font-weight: bold;

        }


        .action a {

            text-decoration: none;

            margin-right: 8px;

        }


        .edit {

            color: #2563eb;

        }


        .details {

            color: #7c3aed;

        }


        .deactivate {

            color: #dc2626;

        }


        .activate {

            color: #16a34a;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #777;

        }


        @media (max-width: 600px) {

            body {

                padding: 15px;

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

                <?php

                echo htmlspecialchars(
                    $gym["gym_name"]
                );

                ?>

            </h1>

            <p>
                Members
            </p>

        </div>


        <div class="actions">

            <a
                href="add_member.php"
                class="button"
            >

                + Add Member

            </a>


            <a
                href="assign_membership.php"
                class="button"
            >

                Assign Membership

            </a>

        </div>

    </div>



    <!-- SEARCH -->

    <div class="search-box">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search name, phone or email"
                value="<?php
                    echo htmlspecialchars($search);
                ?>"
            >


            <button type="submit">

                Search

            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="members.php"
                    class="clear"
                >

                    Clear

                </a>

            <?php endif; ?>

        </form>

    </div>



    <!-- MEMBERS TABLE -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Membership
                    </th>

                    <th>
                        Expiry
                    </th>

                    <th>
                        Payment
                    </th>

                    <th>
                        Member Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if ($members->num_rows > 0): ?>


                <?php while (
                    $member =
                    $members->fetch_assoc()
                ): ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Membership expiry calculation
                    |--------------------------------------------------------------------------
                    */

                    $expiry_status = "No Membership";

                    $expiry_class = "no-membership";

                    $days_remaining = null;


                    if (!empty($member["end_date"])) {

                        $today = new DateTime(
                            date("Y-m-d")
                        );

                        $end_date = new DateTime(
                            $member["end_date"]
                        );


                        if ($end_date < $today) {

                            $expiry_status = "Expired";

                            $expiry_class =
                                "expiry-expired";

                            $days_remaining = 0;

                        }

                        else {

                            $days_remaining =
                                (int) $today
                                ->diff($end_date)
                                ->days;


                            if (
                                $days_remaining <= 7
                            ) {

                                $expiry_status =
                                    "Expiring Soon";

                                $expiry_class =
                                    "expiry-soon";

                            }

                            else {

                                $expiry_status =
                                    "Active";

                                $expiry_class =
                                    "expiry-active";

                            }

                        }

                    }

                    ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?php
                            echo $member["member_id"];
                            ?>

                        </td>



                        <!-- NAME -->

                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $member["name"]
                                );

                                ?>

                            </strong>

                        </td>



                        <!-- PHONE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member["phone"] ?? "-"
                            );

                            ?>

                        </td>



                        <!-- EMAIL -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member["email"] ?? "-"
                            );

                            ?>

                        </td>



                        <!-- MEMBERSHIP -->

                        <td>

                            <?php if (
                                !empty(
                                    $member["plan_name"]
                                )
                            ): ?>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $member["plan_name"]
                                    );

                                    ?>

                                </strong>

                                <br>

                                <small>

                                    Rs.

                                    <?php

                                    echo number_format(
                                        $member["price"],
                                        2
                                    );

                                    ?>

                                </small>

                            <?php else: ?>

                                <span
                                    class="no-membership"
                                >

                                    No Membership

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- EXPIRY -->

                        <td>

                            <?php if (
                                !empty(
                                    $member["end_date"]
                                )
                            ): ?>


                                <span
                                    class="<?php
                                        echo $expiry_class;
                                    ?>"
                                >

                                    <?php

                                    if (
                                        $expiry_status
                                        === "Expired"
                                    ) {

                                        echo "🔴 ";

                                    }

                                    elseif (
                                        $expiry_status
                                        === "Expiring Soon"
                                    ) {

                                        echo "🟠 ";

                                    }

                                    else {

                                        echo "🟢 ";

                                    }

                                    echo $expiry_status;

                                    ?>

                                </span>


                                <br>


                                <small>

                                    <?php

                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $member["end_date"]
                                        )
                                    );

                                    ?>


                                    <?php if (
                                        $expiry_status
                                        === "Expiring Soon"
                                    ): ?>

                                        <br>

                                        <strong>

                                            <?php

                                            echo $days_remaining;

                                            ?>

                                            day<?php
                                                echo
                                                    $days_remaining == 1
                                                    ? ""
                                                    : "s";
                                            ?>

                                            remaining

                                        </strong>

                                    <?php elseif (
                                        $expiry_status
                                        === "Active"
                                    ): ?>

                                        <br>

                                        <?php

                                        echo $days_remaining;

                                        ?>

                                        days remaining

                                    <?php endif; ?>

                                </small>


                            <?php else: ?>


                                <span
                                    class="no-membership"
                                >

                                    No Membership

                                </span>


                            <?php endif; ?>

                        </td>



                        <!-- PAYMENT -->

                        <td>

                            <?php if (
                                $member["payment_status"]
                                === "paid"
                            ): ?>

                                <span class="paid">

                                    🟢 Paid

                                </span>

                            <?php elseif (
                                !empty(
                                    $member["membership_id"]
                                )
                            ): ?>

                                <span class="unpaid">

                                    🔴 Unpaid

                                </span>

                            <?php else: ?>

                                <span
                                    class="no-membership"
                                >

                                    -

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- MEMBER STATUS -->

                        <td>

                            <?php if (
                                $member["status"]
                                === "active"
                            ): ?>

                                <span class="active">

                                    🟢 Active

                                </span>

                            <?php else: ?>

                                <span class="inactive">

                                    🔴 Inactive

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- ACTION -->

                        <td class="action">


                            <a
                                href="member_details.php?id=<?php
                                    echo $member["member_id"];
                                ?>"
                                class="details"
                            >

                                Details

                            </a>


                            <a
                                href="edit_member.php?id=<?php
                                    echo $member["member_id"];
                                ?>"
                                class="edit"
                            >

                                Edit

                            </a>


                            <?php if (
                                $member["status"]
                                === "active"
                            ): ?>


                                <a
                                    href="backend/deactivate_member.php?id=<?php
                                        echo $member["member_id"];
                                    ?>"
                                    class="deactivate"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to deactivate this member?'
                                        );
                                    "
                                >

                                    Deactivate

                                </a>


                            <?php else: ?>


                                <a
                                    href="backend/activate_member.php?id=<?php
                                        echo $member["member_id"];
                                    ?>"
                                    class="activate"
                                    onclick="
                                        return confirm(
                                            'Activate this member?'
                                        );
                                    "
                                >

                                    Activate

                                </a>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endwhile; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="9"
                        class="empty"
                    >

                        <?php if ($search !== ""): ?>

                            No members found for:

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $search
                                );

                                ?>

                            </strong>

                        <?php else: ?>

                            No members found.

                        <?php endif; ?>

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

    </div>



    <br>


    <a
        href="dashboard.php"
        class="button"
    >

        ← Back to Dashboard

    </a>


</div>


</body>

</html>