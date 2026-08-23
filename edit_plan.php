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
| Check Plan ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {
    header("Location: plans.php");
    exit();
}

$plan_id = intval($_GET["id"]);


/*
|--------------------------------------------------------------------------
| Get the plan
|--------------------------------------------------------------------------
|
| We also verify that the plan belongs to
| the logged-in owner's gym.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            mp.plan_id,
            mp.plan_name,
            mp.price,
            mp.duration_months,
            mp.description

        FROM membership_plans mp

        JOIN gyms g
            ON mp.gym_id = g.gym_id

        WHERE mp.plan_id = ?
        AND g.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $plan_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$plan = $result->fetch_assoc();


if (!$plan) {
    die("Membership plan not found.");
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

    <title>Edit Membership Plan</title>
    <link rel = "stylesheet" href = "css/edit_plan.css">

</head>

<body>

<h1>Edit Membership Plan</h1>


<form
    action="backend/update_plan.php"
    method="POST"
>

    <!-- Plan ID -->

    <input
        type="hidden"
        name="plan_id"
        value="<?php echo $plan["plan_id"]; ?>"
    >


    <!-- Plan Name -->

    <label>
        Plan Name:
    </label>

    <br>

    <input
        type="text"
        name="plan_name"
        value="<?php
            echo htmlspecialchars(
                $plan["plan_name"]
            );
        ?>"
        required
    >

    <br><br>


    <!-- Price -->

    <label>
        Price:
    </label>

    <br>

    <input
        type="number"
        name="price"
        value="<?php
            echo htmlspecialchars(
                $plan["price"]
            );
        ?>"
        step="0.01"
        min="0"
        required
    >

    <br><br>


    <!-- Duration -->

    <label>
        Duration (months):
    </label>

    <br>

    <input
        type="number"
        name="duration_months"
        value="<?php
            echo htmlspecialchars(
                $plan["duration_months"]
            );
        ?>"
        min="1"
        required
    >

    <br><br>


    <!-- Description -->

    <label>
        Description:
    </label>

    <br>

    <textarea
        name="description"
        rows="5"
        cols="40"
    ><?php
        echo htmlspecialchars(
            $plan["description"] ?? ""
        );
    ?></textarea>

    <br><br>


    <button type="submit">
        Save Changes
    </button>

</form>


<br>


<a href="plans.php">
    Cancel
</a>

</body>

</html>