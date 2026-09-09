<?php
function sendSMS($to, $message) {
    $apiKey = "73ef3b954df7b34164d3fb8a57df4040bb1f9292f7190cdc";  // Replace with your new secure API Key
    $url = "https://smsmobileapi.com/api/send/";

    $data = [
        "key" => $apiKey,
        "to" => $to,
        "message" => $message
    ];

    $headers = [
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Example: Send an SMS
sendSMS("+639XXXXXXXXX", "🎉 Your enrollment is successful! Welcome!");
?>
