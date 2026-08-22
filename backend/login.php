<?php

session_start();

require_once "db.php";

if($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit();
}

$email = trim($_POST["email"]);
$password = $_POST["password"];

$sql = "SELECT owner_id, name, email, password
        FROM gym_owners
        WHERE email = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $owner = $result->fetch_assoc();

    if (password_verify($password, $owner["password"])) {
        $_SESSION["owner_id"] = $owner["owner_id"];
        $_SESSION["owner_name"] = $owner["name"];
        $_SESSION["owner_email"] = $owner["email"];

        header("Location: ../dashboard.php");
        exit();
    } else {
        echo "Invalid email or password.";
    }
} else {
    echo "Invalid email or password.";
}

$stmt->close();
$conn->close();

?>