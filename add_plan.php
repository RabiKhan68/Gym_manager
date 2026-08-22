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


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f8;

            color: #222;

        }


        .container {

            max-width: 650px;

            margin: 40px auto;

            padding: 20px;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.08);

        }


        h1 {

            margin-top: 0;

            margin-bottom: 25px;

        }


        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

        }


        input,
        textarea {

            width: 100%;

            padding: 12px;

            border: 1px solid #ccc;

            border-radius: 7px;

            font-size: 15px;

            font-family: Arial, sans-serif;

        }


        textarea {

            resize: vertical;

            min-height: 100px;

        }


        input:focus,
        textarea:focus {

            outline: none;

            border-color: #2563eb;

        }


        .hint {

            margin-top: 6px;

            font-size: 13px;

            color: #777;

        }


        button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 7px;

            background: #111827;

            color: white;

            font-size: 16px;

            cursor: pointer;

        }


        button:hover {

            background: #1f2937;

        }


        .back {

            display: inline-block;

            margin-top: 20px;

            text-decoration: none;

            color: #2563eb;

        }

    </style>

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