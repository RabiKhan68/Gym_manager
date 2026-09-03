<?php

/**
 * Send an email using Brevo Transactional Email API
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlContent
 * @return bool
 */

function sendEmail($toEmail, $toName, $subject, $htmlContent)
{
    $apiKey = getenv("BREVO_API_KEY");
    $senderEmail = getenv("BREVO_SENDER_EMAIL");
    $senderName = getenv("BREVO_SENDER_NAME");

    // Make sure required environment variables exist
    if (!$apiKey || !$senderEmail) {
        error_log("Brevo configuration is missing.");
        return false;
    }

    $data = [
        "sender" => [
            "name" => $senderName ?: "Gym Management System",
            "email" => $senderEmail
        ],

        "to" => [
            [
                "email" => $toEmail,
                "name" => $toName
            ]
        ],

        "subject" => $subject,

        "htmlContent" => $htmlContent
    ];

    $ch = curl_init(
        "https://api.brevo.com/v3/smtp/email"
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . $apiKey,
        "content-type: application/json"
    ]);

    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($data)
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    if ($response === false) {

        error_log(
            "Brevo cURL error: " .
            curl_error($ch)
        );

        curl_close($ch);

        return false;
    }

    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    error_log(
        "Brevo API error. HTTP " .
        $httpCode .
        " Response: " .
        $response
    );

    return false;
}