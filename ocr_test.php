<?php

require_once __DIR__ . "/vendor/autoload.php";

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;


echo "<h1>Google Vision Connection Test</h1>";


try {

    /*
    |--------------------------------------------------------------------------
    | Get Google credentials from Render
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
    | Validate JSON
    |--------------------------------------------------------------------------
    */

    $credentials =
        json_decode(
            $credentials_json,
            true
        );


    if (
        !is_array($credentials)
    ) {

        throw new Exception(
            "Google credentials contain invalid JSON."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Create temporary credentials file
    |--------------------------------------------------------------------------
    */

    $credentials_file =
        sys_get_temp_dir() .
        DIRECTORY_SEPARATOR .
        "google-vision-" .
        uniqid() .
        ".json";


    $written =
        file_put_contents(
            $credentials_file,
            $credentials_json
        );


    if ($written === false) {

        throw new Exception(
            "Could not create temporary Google credentials file."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Tell Google client where credentials are
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
    | Success
    |--------------------------------------------------------------------------
    */

    echo "<p style='
        color: green;
        font-weight: bold;
        font-size: 18px;
    '>";

    echo "✓ Google Vision client initialized successfully.";

    echo "</p>";


    echo "<p>";

    echo "Google Vision PHP package is installed and the ";

    echo "service account credentials were loaded.";

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
    | Delete temporary credentials
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
    | Error
    |--------------------------------------------------------------------------
    */

    echo "<p style='
        color: red;
        font-weight: bold;
        font-size: 18px;
    '>";

    echo "✗ Google Vision connection failed.";

    echo "</p>";


    echo "<pre style='
        background: #f4f4f4;
        padding: 15px;
        border-radius: 8px;
        white-space: pre-wrap;
    '>";

    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    );

    echo "</pre>";

}