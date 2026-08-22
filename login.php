<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gym Management - Login</title>
    
    <link rel = "stylesheet" href = "css/login.css">
</head>


<body>


<div class="login-container">


    <div class="logo">

        <h1>
            <img src = "images/gym.png" class="gym-icon" alt="Gym">
            GymManager
        </h1>

        <p>
            Manage your gym with ease
        </p>

    </div>


    <div class="card">


        <h2>
            Gym Owner Login
        </h2>


        <form
            action="backend/login.php"
            method="POST"
        >


            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>


            <button
                type="submit"
                class="login-button"
            >

                Login

            </button>


        </form>


        <div class="register">

            Don't have an account?

            <a href="register.php">
                Create an account
            </a>

        </div>


    </div>


</div>


</body>

</html>