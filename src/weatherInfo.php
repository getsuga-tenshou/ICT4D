<?php
$host = 'sql.freedb.tech';
$dbname = 'freedb_meteodb';
$username = 'freedb_meteouser';
$password = 'YdQnV8fT3%?wpZ&';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: Could not connect. " . $e->getMessage());
}

$query = "SELECT temperature, wind_speed, rainfall FROM report ORDER BY date DESC LIMIT 1";

try {
    $stmt = $pdo->query($query);

    $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();
    $pdo = null;
} catch (PDOException $e) {
    die("Error: Could not retrieve data. " . $e->getMessage());
}

$language = isset($_POST['language']) ? $_POST['language'] : 'english';

// $weatherData = array(
//     'temperature' => 40,
//     'rainfall' => 1.2,
//     'windspeed' => 15
// );

function generatePrompt($weatherData, $language)
{
    $temperatureTextFr = $weatherData['temperature'] . ' degrés Celsius.';
    $rainfallTextFr = $weatherData['rainfall'] ? 'Il pleut avec une quantité de ' . number_format($weatherData['rainfall'], 1) . 'mm.' : 'Il ne pleut pas.';
    $windspeedTextFr = $weatherData['wind_speed'] . ' km/h.';

    $temperatureTextEn = $weatherData['temperature'] . ' degrees Celsius.';
    $rainfallTextEn = $weatherData['rainfall'] ? 'It is raining with an amount of ' . number_format($weatherData['rainfall'], 1) . 'mm.' : 'It is not raining.';
    $windspeedTextEn = $weatherData['wind_speed'] . ' km/h.';

    $temperatureRanges = [
        ['min' => -100, 'max' => 0, 'qualifier' => ($language === 'french') ? 'Il fait très froid.' : 'It is very cold.'],
        ['min' => 0, 'max' => 10, 'qualifier' => ($language === 'french') ? 'Il fait froid.' : 'It is cold.'],
        ['min' => 10, 'max' => 15, 'qualifier' => ($language === 'french') ? 'Il fait frais.' : 'It is cool.'],
        ['min' => 15, 'max' => 25, 'qualifier' => ($language === 'french') ? 'Il fait doux.' : 'It is mild.'],
        ['min' => 25, 'max' => 35, 'qualifier' => ($language === 'french') ? 'Il fait chaud.' : 'It is hot.'],
        ['min' => 35, 'max' => 100, 'qualifier' => ($language === 'french') ? 'Il fait très chaud.' : 'It is very hot.'],
    ];

    $windSpeedRanges = [
        ['min' => 0, 'max' => 10, 'qualifier' => ($language === 'french') ? 'Le vent est calme.' : 'The wind is calm.'],
        ['min' => 10, 'max' => 20, 'qualifier' => ($language === 'french') ? 'Le vent est modéré.' : 'The wind is moderate.'],
        ['min' => 20, 'max' => 30, 'qualifier' => ($language === 'french') ? 'Le vent est fort.' : 'The wind is strong.'],
        ['min' => 30, 'max' => 100, 'qualifier' => ($language === 'french') ? 'Le vent est très fort.' : 'The wind is very strong.'],
    ];

    $rainfallRanges = [
        ['min' => 0, 'max' => 0, 'qualifier' => ($language === 'french') ? 'Il ne pleut pas.' : 'It is not raining.'],
        ['min' => 0.01, 'max' => 5, 'qualifier' => ($language === 'french') ? 'Il pleut légèrement.' : 'It is lightly raining.'],
        ['min' => 5, 'max' => 10, 'qualifier' => ($language === 'french') ? 'Il pleut modérément.' : 'It is moderately raining.'],
        ['min' => 10, 'max' => 20, 'qualifier' => ($language === 'french') ? 'Il pleut fortement.' : 'It is heavily raining.'],
        ['min' => 20, 'max' => 100, 'qualifier' => ($language === 'french') ? 'Il pleut très fortement.' : 'It is raining very heavily.'],
    ];

    $temperatureQualifier = '';
    foreach ($temperatureRanges as $range) {
        if ($weatherData['temperature'] >= $range['min'] && $weatherData['temperature'] < $range['max']) {
            $temperatureQualifier = $range['qualifier'];
            break;
        }
    }

    $windQualifier = '';
    foreach ($windSpeedRanges as $range) {
        if ($weatherData['wind_speed'] >= $range['min'] && $weatherData['wind_speed'] < $range['max']) {
            $windQualifier = $range['qualifier'];
            break;
        }
    }

    $rainfallQualifier = '';
    foreach ($rainfallRanges as $range) {
        if ($weatherData['rainfall'] >= $range['min'] && $weatherData['rainfall'] < $range['max']) {
            $rainfallQualifier = $range['qualifier'];
            break;
        }
    }

    $prompts = [];

    if ($language === 'french') {
        $prompts[] = "<prompt xml:lang=\"fr-fr\">La température actuelle est $temperatureTextFr $temperatureQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">$rainfallTextFr $rainfallQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">La vitesse du vent est $windspeedTextFr $windQualifier</prompt>";
    } else {
        $prompts[] = "<prompt>Current temperature is $temperatureTextEn $temperatureQualifier</prompt>";
        $prompts[] = "<prompt>$rainfallTextEn $rainfallQualifier</prompt>";
        $prompts[] = "<prompt>Windspeed is $windspeedTextEn $windQualifier</prompt>";
    }

    return implode('', $prompts);
}

$xmlPrompt = generatePrompt($weatherData, $language);

header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<vxml version="2.1">
    <form>
        <block>
            <?php echo $xmlPrompt; ?>
        </block>
    </form>
</vxml>