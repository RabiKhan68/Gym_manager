<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Create Gym Owner Account
    </title>
    <link rel = "stylesheet" href = "css/register.css">
</head>


<body>

    <h1>
        Create Gym Owner Account
    </h1>


    <form
        action="backend/register.php"
        method="POST"
    >

        <!-- NAME -->

        <label for="name">
            Full Name:
        </label>

        <br>

        <input
            type="text"
            id="name"
            name="name"
            required
            autocomplete="name"
        >

        <br><br>


        <!-- EMAIL -->

        <label for="email">
            Email:
        </label>

        <br>

        <input
            type="email"
            id="email"
            name="email"
            required
            autocomplete="email"
        >

        <br><br>


        <!-- PHONE -->

        <label for="phone">
            Phone / WhatsApp Number:
        </label>

        <br>

        <input
            type="tel"
            id="phone"
            name="phone"
            placeholder="03001234567"
            maxlength="15"
            autocomplete="tel"
        >

        <br>

        <small>
            This number can be used for WhatsApp notifications.
        </small>

        <br><br>


        <!-- PASSWORD -->

        <label for="password">
            Password:
        </label>

        <br>

        <input
            type="password"
            id="password"
            name="password"
            required
            minlength="6"
            autocomplete="new-password"
        >

        <br><br>


        <!-- SUBMIT -->

        <button type="submit">
            Create Account
        </button>

    </form>


    <br>


    <a href="login.php">
        Already have an account? Login
    </a>


</body>

</html>