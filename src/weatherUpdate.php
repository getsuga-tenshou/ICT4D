<?php
$api_url = 'https://api.openweathermap.org/data/2.5/onecall?lat=33.44&lon=-94.04&exclude=hourly,daily&appid=ea76187d3f9b6b33b8a0648cd9bd7236';

$response = file_get_contents($api_url);

if ($response === false) {
    echo "Error fetching weather data.";
    exit;
}

$data = json_decode($response, true);

$temperature = $data['current']['temp'];
$windspeed = $data['current']['wind_speed'];
$rainfall = isset($data['current']['rain']) ? $data['current']['rain']['1h'] : 0;

try {
    $pdo = new PDO("mysql:host=your_host;dbname=your_database", "your_username", "your_password");

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("INSERT INTO weather_data (temperature, windspeed, rainfall) VALUES (:temperature, :windspeed, :rainfall)");

    $stmt->bindParam(':temperature', $temperature);
    $stmt->bindParam(':windspeed', $windspeed);
    $stmt->bindParam(':rainfall', $rainfall);

    $stmt->execute();

    echo "Weather data inserted successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$pdo = null;
