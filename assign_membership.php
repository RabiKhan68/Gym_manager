<?php

session_start();

date_default_timezone_set("Asia/Karachi");

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
| Get owner's gym
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
| Get active members
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            member_id,
            name,
            phone
        FROM members
        WHERE gym_id = ?
        AND status = 'active'
        ORDER BY name";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$members = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Get membership plans
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            plan_id,
            plan_name,
            price,
            duration_months,
            description
        FROM membership_plans
        WHERE gym_id = ?
        ORDER BY plan_name";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$plans = $stmt->get_result();

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
        Assign Membership
    </title>
    
    <link rel = "stylesheet" href="css/assign_membership.css">

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            Assign Membership
        </h1>


        <p class="subtitle">

            Assign a membership plan to a member of

            <span class="gym-name">

                <?php

                echo htmlspecialchars(
                    $gym["gym_name"]
                );

                ?>

            </span>

        </p>


        <form
            action="backend/assign_membership.php"
            method="POST"
        >


            <!-- MEMBER -->

            <div class="form-group">

                <label for="member_id">

                    Member

                </label>


                <select
                    name="member_id"
                    id="member_id"
                    required
                >

                    <option value="">

                        Select Member

                    </option>


                    <?php while (
                        $member =
                        $members->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo $member["member_id"];
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $member["name"]
                            );

                            ?>

                            <?php if (
                                !empty(
                                    $member["phone"]
                                )
                            ): ?>

                                -
                                <?php

                                echo htmlspecialchars(
                                    $member["phone"]
                                );

                                ?>

                            <?php endif; ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>



            <!-- PLAN -->

            <div class="form-group">

                <label for="plan_id">

                    Membership Plan

                </label>


                <select
                    name="plan_id"
                    id="plan_id"
                    required
                >

                    <option value="">

                        Select Membership Plan

                    </option>


                    <?php while (
                        $plan =
                        $plans->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php
                                echo $plan["plan_id"];
                            ?>"
                            data-price="<?php
                                echo htmlspecialchars(
                                    $plan["price"]
                                );
                            ?>"
                            data-duration="<?php
                                echo htmlspecialchars(
                                    $plan["duration_months"]
                                );
                            ?>"
                        >

                            <?php

                            echo htmlspecialchars(
                                $plan["plan_name"]
                            );

                            ?>

                            -

                            Rs.

                            <?php

                            echo number_format(
                                $plan["price"],
                                2
                            );

                            ?>

                            -

                            <?php

                            echo $plan[
                                "duration_months"
                            ];

                            ?>

                            month<?php
                                echo
                                    $plan[
                                        "duration_months"
                                    ] == 1
                                    ? ""
                                    : "s";
                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>


                <div
                    id="planInfo"
                    class="plan-info"
                >

                    <strong>
                        Selected Plan
                    </strong>

                    <br>

                    Price:
                    Rs.
                    <span id="planPrice">
                        -
                    </span>

                    <br>

                    Duration:
                    <span id="planDuration">
                        -
                    </span>

                </div>

            </div>



            <!-- START DATE -->

            <div class="form-group">

                <label for="start_date">

                    Start Date

                </label>


                <input
                    type="date"
                    name="start_date"
                    id="start_date"
                    value="<?php
                        echo date("Y-m-d");
                    ?>"
                    required
                >


                <span class="hint">

                    The membership expiry date will
                    be calculated automatically from
                    the selected plan's duration.

                </span>

            </div>



            <!-- BUTTONS -->

            <div class="buttons">

                <button type="submit">

                    Assign Membership

                </button>


                <a
                    href="members.php"
                    class="back"
                >

                    Cancel

                </a>

            </div>


        </form>


    </div>


</div>



<script>

const planSelect =
    document.getElementById("plan_id");

const planInfo =
    document.getElementById("planInfo");

const planPrice =
    document.getElementById("planPrice");

const planDuration =
    document.getElementById("planDuration");


planSelect.addEventListener(
    "change",
    function () {

        const option =
            this.options[this.selectedIndex];


        if (!option.value) {

            planInfo.style.display =
                "none";

            return;

        }


        const price =
            option.dataset.price;

        const duration =
            option.dataset.duration;


        planPrice.textContent =
            Number(price).toLocaleString(
                "en-PK",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );


        planDuration.textContent =
            duration +
            (
                duration == 1
                    ? " month"
                    : " months"
            );


        planInfo.style.display =
            "block";

    }
);

</script>


</body>

</html>