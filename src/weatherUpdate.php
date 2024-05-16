<?php
$api_url = 'https://api.weatherapi.com/v1/current.json?q=18,-4&key=f53317d9afcf4cce844123315242604';

$response = file_get_contents($api_url);

if ($response === false) {
    echo "Error fetching weather data.";
    exit;
}

$data = json_decode($response, true);

$lastUpdated = date('Y-m-d H:i:s', strtotime($data['current']['last_updated']));

$location = $data['location']['name'];
$temperature = $data['current']['temp_c'];
$wind_speed = $data['current']['wind_kph'];
$rainfall = $data['current']['precip_mm'];

try {
    $pdo = new PDO("mysql:host=sql.freedb.tech;dbname=freedb_meteodb", "freedb_meteouser", "YdQnV8fT3%?wpZ&");

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        INSERT INTO report (date, location, temperature, wind_speed, rainfall, isAlert) 
        VALUES (:last_updated, :location, :temperature, :wind_speed, :rainfall, 0)
        ON DUPLICATE KEY UPDATE 
            temperature = IF(isAlert = 0, VALUES(temperature), temperature),
            wind_speed = IF(isAlert = 0, VALUES(wind_speed), wind_speed),
            rainfall = IF(isAlert = 0, VALUES(rainfall), rainfall),
            isAlert = IF(isAlert = 0, VALUES(isAlert), isAlert)
    ");

    $stmt->bindParam(':last_updated', $lastUpdated);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':temperature', $temperature);
    $stmt->bindParam(':wind_speed', $wind_speed);
    $stmt->bindParam(':rainfall', $rainfall);

    $stmt->execute();

    echo "Weather data inserted successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$pdo = null;
