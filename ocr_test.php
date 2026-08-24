<?php

session_start();

require_once __DIR__ . "/vendor/autoload.php";

use Google\Cloud\Vision\V1\ImageAnnotatorClient;


/*
|--------------------------------------------------------------------------
| TEST GOOGLE VISION CONNECTION
|--------------------------------------------------------------------------
|
| The Google JSON credentials are stored in Render as:
|
| GOOGLE_APPLICATION_CREDENTIALS_JSON
|
| We temporarily create the credentials file so the Google client can
| authenticate without putting the JSON key inside GitHub.
|
|--------------------------------------------------------------------------
*/


try {

    /*
    |--------------------------------------------------------------------------
    | Get credentials from Render
    |--------------------------------------------------------------------------
    */

    $credentials_json =
        getenv("GOOGLE_APPLICATION_CREDENTIALS_JSON");


    if (
        !$credentials_json ||
        trim($credentials_json) === ""
    ) {

        throw new Exception(
            "GOOGLE_APPLICATION_CREDENTIALS_JSON is not configured."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Create temporary credentials file
    |--------------------------------------------------------------------------
    */

    $credentials_file =
        sys_get_temp_dir() .
        "/google-vision-" .
        uniqid() .
        ".json";


    if (
        file_put_contents(
            $credentials_file,
            $credentials_json
        ) === false
    ) {

        throw new Exception(
            "Could not create temporary Google credentials file."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Tell Google libraries where credentials are
    |--------------------------------------------------------------------------
    */

    putenv(
        "GOOGLE_APPLICATION_CREDENTIALS=" .
        $credentials_file
    );


    /*
    |--------------------------------------------------------------------------
    | Create Vision client
    |--------------------------------------------------------------------------
    */

    $client =
        new ImageAnnotatorClient();


    /*
    |--------------------------------------------------------------------------
    | Test successful authentication
    |--------------------------------------------------------------------------
    */

    echo "<h1>Google Vision Connection Test</h1>";

    echo "<p style='color: green; font-weight: bold;'>";

    echo "✓ Google Vision client initialized successfully.";

    echo "</p>";


    /*
    |--------------------------------------------------------------------------
    | Close client
    |--------------------------------------------------------------------------
    */

    $client->close();


    /*
    |--------------------------------------------------------------------------
    | Delete temporary credentials
    |--------------------------------------------------------------------------
    */

    if (
        file_exists(
            $credentials_file
        )
    ) {

        unlink(
            $credentials_file
        );

    }


}
catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | Delete temporary credentials if they exist
    |--------------------------------------------------------------------------
    */

    if (
        isset($credentials_file) &&
        file_exists($credentials_file)
    ) {

        unlink(
            $credentials_file
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Display safe error
    |--------------------------------------------------------------------------
    */

    echo "<h1>Google Vision Connection Test</h1>";

    echo "<p style='color: red; font-weight: bold;'>";

    echo "✗ Google Vision connection failed.";

    echo "</p>";


    echo "<pre>";

    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    );

    echo "</pre>";

}