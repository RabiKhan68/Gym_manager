<?php

require_once "db.php";

/*
    Change these values to your own admin details.
*/

$name = "rabi";
$email = "rabibro063@gmail.com";
$password = "srkthepro12";


/*
    Hash the password
*/

$hashed_password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/*
    Insert admin
*/

$sql = "INSERT INTO admins
        (name, email, password)
        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sss",
    $name,
    $email,
    $hashed_password
);


if ($stmt->execute()) {

    echo "Admin account created successfully.";

} else {

    echo "Error: " . $stmt->error;

}


$stmt->close();
$conn->close();

?>