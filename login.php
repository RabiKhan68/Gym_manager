<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gym Management - Login</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                sans-serif;

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


        .login-container {

            width: 100%;

            max-width: 420px;

        }


        .logo {

            text-align: center;

            color: white;

            margin-bottom: 25px;

        }


        .logo h1 {

            margin: 0;

            font-size: 32px;

        }


        .logo p {

            margin-top: 8px;

            color: #dbeafe;

        }


        .card {

            background: white;

            padding: 35px;

            border-radius: 16px;

            box-shadow:
                0 20px 40px
                rgba(0,0,0,0.2);

        }


        .card h2 {

            margin-top: 0;

            margin-bottom: 25px;

            text-align: center;

            color: #111827;

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


        input {

            width: 100%;

            padding: 13px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.15);

        }


        .login-button {

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


        .login-button:hover {

            background: #1d4ed8;

        }


        .register {

            text-align: center;

            margin-top: 22px;

            color: #6b7280;

        }


        .register a {

            color: #2563eb;

            text-decoration: none;

            font-weight: bold;

        }


        .register a:hover {

            text-decoration: underline;

        }

    </style>

</head>


<body>


<div class="login-container">


    <div class="logo">

        <h1>
            🏋️ GymManager
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