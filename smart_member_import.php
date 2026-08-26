<?php

session_start();

if (!isset($_SESSION["owner_id"])) {
    header("Location: login.php");
    exit();
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
        Smart Member Import
    </title>

    <link
        rel="stylesheet"
        href="css/smart_member_import.css"
    >

</head>


<body>


<div class="container">


    <div class="header">

        <div>

            <h1>
                📷 Smart Member Import
            </h1>

            <p>
                Upload a photo of your member register
                and GymManager will extract member information.
            </p>

        </div>


        <a
            href="members.php"
            class="back"
        >
            ← Back to Members
        </a>

    </div>



    <div class="card">


        <div class="info-box">

            <strong>
                How it works
            </strong>

            <ol>

                <li>
                    Take a clear photo of your member register.
                </li>

                <li>
                    Upload the image below.
                </li>

                <li>
                    GymManager will scan the image using OCR.
                </li>

                <li>
                    Review and correct the detected information.
                </li>

                <li>
                    Confirm the import.
                </li>

            </ol>

        </div>



        <div class="warning-box">

            <strong>
                Important
            </strong>

            <p>

                OCR may make mistakes when reading handwriting.
                Always review the detected names and phone numbers
                before importing them.

            </p>

        </div>



        <form
            action="backend/ocr_process.php"
            method="POST"
            enctype="multipart/form-data"
            id="ocrForm"
        >


            <div class="upload-area">


                <div class="upload-icon">
                    📷
                </div>


                <h2>
                    Upload Register Image
                </h2>


                <p>
                    JPG, JPEG or PNG
                </p>


                <input
                    type="file"
                    name="register_image"
                    id="register_image"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    required
                >


                <label
                    for="register_image"
                    class="choose-button"
                >
                    Choose Image
                </label>


                <div
                    id="file-name"
                    class="file-name"
                >
                    No image selected
                </div>


            </div>



            <div class="tips">

                <h3>
                    📌 For better OCR results
                </h3>

                <ul>

                    <li>
                        Take the photo in good lighting.
                    </li>

                    <li>
                        Keep the register flat.
                    </li>

                    <li>
                        Keep the camera directly above the page.
                    </li>

                    <li>
                        Make sure names and phone numbers are clearly visible.
                    </li>

                    <li>
                        Avoid shadows over the writing.
                    </li>

                </ul>

            </div>



            <button
                type="submit"
                class="scan-button"
                id="scanButton"
            >

                🔍 Scan Register

            </button>


        </form>


    </div>


</div>



<script>

const fileInput =
    document.getElementById("register_image");

const fileName =
    document.getElementById("file-name");

const form =
    document.getElementById("ocrForm");

const scanButton =
    document.getElementById("scanButton");


fileInput.addEventListener(
    "change",
    function () {

        if (this.files.length > 0) {

            fileName.textContent =
                this.files[0].name;

        } else {

            fileName.textContent =
                "No image selected";

        }

    }
);


form.addEventListener(
    "submit",
    function () {

        scanButton.disabled = true;

        scanButton.innerHTML =
            "⏳ Scanning Register...";

    }
);

</script>


</body>

</html>