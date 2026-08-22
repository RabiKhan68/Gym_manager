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
| Check if owner already has a gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT gym_id
        FROM gyms
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    header("Location: dashboard.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get owner's information
|--------------------------------------------------------------------------
*/

$sql = "SELECT name, phone
        FROM gym_owners
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$owner = $result->fetch_assoc();


if (!$owner) {

    die("Owner account not found.");

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
        Create Your Gym
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family: Arial, sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #2563eb
                );

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

        }


        .container {

            width: 100%;

            max-width: 500px;

        }


        .card {

            background: white;

            padding: 35px;

            border-radius: 16px;

            box-shadow:
                0 20px 40px
                rgba(0,0,0,0.2);

        }


        h1 {

            margin-top: 0;

            color: #111827;

        }


        .welcome {

            color: #6b7280;

            margin-bottom: 25px;

        }


        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            margin-bottom: 8px;

            font-weight: bold;

            color: #374151;

        }


        input,
        textarea {

            width: 100%;

            padding: 13px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

        }


        textarea {

            min-height: 100px;

            resize: vertical;

        }


        input:focus,
        textarea:focus {

            outline: none;

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.15);

        }


        .phone-info {

            background: #eff6ff;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 20px;

            color: #1e40af;

        }


        button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 8px;

            background: #2563eb;

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;

        }


        button:hover {

            background: #1d4ed8;

        }

    </style>

</head>


<body>


<div class="container">

    <div class="card">


        <h1>
            🏋️ Create Your Gym
        </h1>


        <p class="welcome">

            Welcome,

            <strong>
                <?php
                echo htmlspecialchars(
                    $owner["name"]
                );
                ?>
            </strong>!

            <br><br>

            Let's set up your gym before
            you continue to the dashboard.

        </p>


        <form
            action="backend/create_gym.php"
            method="POST"
        >


            <div class="form-group">

                <label for="gym_name">
                    Gym Name
                </label>

                <input
                    type="text"
                    id="gym_name"
                    name="gym_name"
                    placeholder="e.g. Fitness Arena"
                    required
                >

            </div>


            <div class="form-group">

                <label for="address">
                    Gym Address
                </label>

                <textarea
                    id="address"
                    name="address"
                    placeholder="Enter your gym address"
                ></textarea>

            </div>


            <div class="phone-info">

                📱 Gym contact / WhatsApp number:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $owner["phone"] ?? "-"
                    );

                    ?>

                </strong>

                <br><br>

                This number will be used as
                your gym's contact number.

            </div>


            <button type="submit">

                Create Gym

            </button>


        </form>


    </div>

</div>


</body>

</html>