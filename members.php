<?php

session_start();

require_once "backend/check_subscription.php";

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
            m.member_number,
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

    <link rel = "stylesheet" href = "css/members.css">
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

            <!-- <a
                href="smart_member_import.php"
                class="button"
            >
                📷 Smart Import
            </a> -->


            <a
                href="assign_membership.php"
                class="button"
            >

                Assign Membership

            </a>

            <a
                href="dashboard.php"
                class="button"
            >

               ← Back to Dashboard

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
                        Member #
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
                            echo htmlspecialchars(
                                $member["member_number"]
                            );
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

                                    <img src = "images/circle.png" class="stat-icon" alt="Gym">
                                Paid

                                </span>

                            <?php elseif (
                                !empty(
                                    $member["membership_id"]
                                )
                            ): ?>

                                <span class="unpaid">

                                    <img src = "images/delete.png" class="stat-icon" alt="Gym">
                                Unpaid

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

</div>


</body>

</html>