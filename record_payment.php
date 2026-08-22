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

if (!isset($_GET["member_id"])) {

    die("Member ID is required.");

}

$member_id = (int) $_GET["member_id"];


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

$result = $stmt->get_result();

$gym = $result->fetch_assoc();


if (!$gym) {

    die("Gym not found.");

}

$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Get member + current membership
|--------------------------------------------------------------------------
*/

$current_month = date("Y-m-01");

$sql = "SELECT

            m.member_id,
            m.name,
            m.phone,

            mm.membership_id,
            mm.start_date,
            mm.end_date,

            mp.plan_id,
            mp.plan_name,
            mp.price

        FROM members m

        INNER JOIN member_memberships mm
            ON m.member_id = mm.member_id

        INNER JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        WHERE m.member_id = ?

        AND m.gym_id = ?

        AND m.status = 'active'

        AND mm.status = 'active'

        AND mm.start_date <= LAST_DAY(?)

        AND mm.end_date >= ?

        ORDER BY mm.membership_id DESC

        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iiss",
    $member_id,
    $gym_id,
    $current_month,
    $current_month
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {

    die(
        "Member does not have an active membership for this month."
    );

}


/*
|--------------------------------------------------------------------------
| Check whether already paid
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            payment_id
        FROM payments
        WHERE member_id = ?
        AND payment_for_month = ?
        AND payment_status = 'paid'
        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $member_id,
    $current_month
);

$stmt->execute();

$result = $stmt->get_result();

$existing_payment = $result->fetch_assoc();


if ($existing_payment) {

    die(
        "This member has already paid for " .
        date("F Y", strtotime($current_month)) .
        "."
    );

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
        Record Payment
    </title>

    <link rel = "stylesheet" href = "css/record_payment.css">
</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            💰 Record Payment
        </h1>


        <p class="month">

            <?php

            echo date(
                "F Y",
                strtotime($current_month)
            );

            ?>

        </p>


        <!-- MEMBER INFORMATION -->

        <div class="info">


            <div class="info-row">

                <span class="label">
                    Member
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
                        $member["phone"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    Plan
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $member["plan_name"]
                    );

                    ?>

                </strong>

            </div>


            <div class="info-row">

                <span class="label">
                    Membership
                </span>

                <span>

                    <?php

                    echo $member["start_date"];

                    ?>

                    →

                    <?php

                    echo $member["end_date"];

                    ?>

                </span>

            </div>


            <div class="info-row">

                <span class="label">
                    Monthly Fee
                </span>

                <span class="amount">

                    Rs.

                    <?php

                    echo number_format(
                        $member["price"],
                        2
                    );

                    ?>

                </span>

            </div>


        </div>


        <!-- PAYMENT FORM -->

        <form
            method="POST"
            action="backend/record_payment.php"
        >


            <input
                type="hidden"
                name="member_id"
                value="<?php
                    echo $member["member_id"];
                ?>"
            >


            <input
                type="hidden"
                name="membership_id"
                value="<?php
                    echo $member["membership_id"];
                ?>"
            >


            <label>
                Payment Amount
            </label>

            <input
                type="number"
                name="amount"
                min="0.01"
                step="0.01"
                value="<?php
                    echo $member["price"];
                ?>"
                required
            >


            <label>
                Payment Method
            </label>

            <select
                name="payment_method"
                required
            >

                <option value="cash">
                    Cash
                </option>

                <option value="online">
                    Online
                </option>

            </select>


            <div class="buttons">


                <a
                    href="payments.php"
                    class="button cancel"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="button submit"
                >

                    ✅ Record Payment

                </button>


            </div>


        </form>


    </div>


</div>


</body>

</html>