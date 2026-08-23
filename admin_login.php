<?php

session_start();

require_once "backend/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } else {

        $sql = "SELECT admin_id, name, email, password
                FROM admins
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "s",
            $email
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $admin = $result->fetch_assoc();

        if (
            $admin &&
            password_verify(
                $password,
                $admin["password"]
            )
        ) {

            $_SESSION["admin_id"] =
                $admin["admin_id"];

            $_SESSION["admin_name"] =
                $admin["name"];

            $_SESSION["admin_email"] =
                $admin["email"];

            header("Location: admin_dashboard.php");

            exit();

        } else {

            $error = "Invalid email or password.";

        }

        $stmt->close();

    }

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

    <title>Admin Login</title>

</head>

<body>

    <h1>Admin Login</h1>

    <?php if ($error): ?>

        <p style="color:red;">

            <?php
            echo htmlspecialchars($error);
            ?>

        </p>

    <?php endif; ?>


    <form method="POST">

        <label>
            Email:
        </label>

        <br>

        <input
            type="email"
            name="email"
            required
        >

        <br><br>


        <label>
            Password:
        </label>

        <br>

        <input
            type="password"
            name="password"
            required
        >

        <br><br>


        <button type="submit">
            Login as Admin
        </button>

    </form>


</body>

</html>