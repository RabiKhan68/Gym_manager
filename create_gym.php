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
    
    <link rel = "stylesheet" href = "css/create_gym.css">

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