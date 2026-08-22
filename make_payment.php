<?php

session_start();

require_once "backend/db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: login.php");
    exit();
}

$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Current date
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Get active and non-expired memberships
|--------------------------------------------------------------------------
|
| We only show memberships that:
|
| 1. Belong to the logged-in owner's gym
| 2. Are marked active
| 3. Have not expired
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            mm.membership_id,
            mm.member_id,
            mm.start_date,
            mm.end_date,

            m.name AS member_name,
            m.phone,

            mp.plan_name,
            mp.price

        FROM member_memberships mm

        INNER JOIN members m
            ON mm.member_id = m.member_id

        INNER JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE g.owner_id = ?

        AND mm.status = 'active'

        AND mm.start_date <= ?

        AND mm.end_date >= ?

        AND m.status = 'active'

        ORDER BY m.name";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $owner_id,
    $today,
    $today
);

$stmt->execute();

$memberships = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Record Payment</title>

    <link rel = "stylesheet" href = "css/make_payment.css">
</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            💰 Record Payment
        </h1>


        <p>
            Select a member and record their payment.
        </p>


        <?php if ($memberships->num_rows > 0): ?>


            <form
                action="backend/make_payment.php"
                method="POST"
            >


                <!-- MEMBER -->


                <div class="form-group">

                    <label>
                        Member / Membership
                    </label>


                    <select
                        name="membership_id"
                        required
                    >

                        <option value="">
                            Select Member
                        </option>


                        <?php while (
                            $membership =
                            $memberships->fetch_assoc()
                        ): ?>


                            <option
                                value="<?php
                                    echo $membership[
                                        "membership_id"
                                    ];
                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $membership[
                                        "member_name"
                                    ]
                                );

                                ?>

                                -

                                <?php

                                echo htmlspecialchars(
                                    $membership[
                                        "plan_name"
                                    ]
                                );

                                ?>

                                -

                                Rs.

                                <?php

                                echo number_format(
                                    $membership[
                                        "price"
                                    ],
                                    2
                                );

                                ?>

                                -

                                Ends:

                                <?php

                                echo
                                    $membership[
                                        "end_date"
                                    ];

                                ?>

                            </option>


                        <?php endwhile; ?>


                    </select>


                    <div class="membership-info">

                        Only active, non-expired members
                        are shown.

                    </div>

                </div>



                <!-- PAYMENT MONTH -->


                <div class="form-group">

                    <label>
                        Payment For Month
                    </label>


                    <input
                        type="month"
                        name="payment_month"
                        value="<?php
                            echo date("Y-m");
                        ?>"
                        required
                    >

                </div>



                <!-- PAYMENT METHOD -->


                <div class="form-group">

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

                </div>



                <!-- SUBMIT -->


                <button type="submit">

                    💵 Record Payment

                </button>


            </form>


        <?php else: ?>


            <div class="empty">

                <strong>
                    No active memberships found.
                </strong>

                <br><br>

                There are currently no active,
                non-expired members available
                for payment.

            </div>


        <?php endif; ?>


        <a
            href="payments.php"
            class="back"
        >

            ← Back to Payments

        </a>


        <br>


        <a
            href="dashboard.php"
            class="back"
        >

            ← Back to Dashboard

        </a>


    </div>


</div>


</body>

</html>