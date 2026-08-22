<?php

session_start();

if (!isset($_SESSION["owner_id"])) {
    header("Location: login.php");
    exit();
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
        Create Membership Plan
    </title>

    <link rel = "stylesheet" href = "css/add_plan.css">

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            ➕ Create Membership Plan
        </h1>


        <form
            action="backend/add_plan.php"
            method="POST"
        >


            <!-- PLAN NAME -->

            <div class="form-group">

                <label for="plan_name">
                    Plan Name
                </label>

                <input
                    type="text"
                    id="plan_name"
                    name="plan_name"
                    placeholder="e.g. Basic, Premium, Gold"
                    maxlength="100"
                    required
                >

            </div>



            <!-- PRICE -->

            <div class="form-group">

                <label for="price">
                    Monthly / Plan Price
                </label>

                <input
                    type="number"
                    id="price"
                    name="price"
                    placeholder="e.g. 3000"
                    step="0.01"
                    min="0"
                    required
                >

                <div class="hint">
                    Enter the price charged for this membership plan.
                </div>

            </div>



            <!-- DURATION -->

            <div class="form-group">

                <label for="duration_months">
                    Duration
                </label>

                <input
                    type="number"
                    id="duration_months"
                    name="duration_months"
                    placeholder="e.g. 1"
                    min="1"
                    max="120"
                    required
                >

                <div class="hint">
                    How many months the membership lasts.
                </div>

            </div>



            <!-- DESCRIPTION -->

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Describe what this plan includes..."
                    maxlength="1000"
                ></textarea>

            </div>



            <!-- SUBMIT -->

            <button type="submit">

                💾 Create Membership Plan

            </button>


        </form>



        <a
            href="plans.php"
            class="back"
        >

            ← Back to Plans

        </a>


    </div>


</div>


</body>

</html>