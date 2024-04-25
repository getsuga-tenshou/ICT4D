<?php
// $host = 'database_host';
// $dbname = 'database_name';
// $username = 'database_username';
// $password = 'database_password';

// try {
//     $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch (PDOException $e) {
//     die("Error: Could not connect. " . $e->getMessage());
// }

// $query = "SELECT temperature, wind, rainfall FROM weather_data ORDER BY id DESC LIMIT 1";

// try {
//     $stmt = $pdo->query($query);

//     $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);

//     $stmt->closeCursor();
//     $pdo = null;
// } catch (PDOException $e) {
//     die("Error: Could not retrieve data. " . $e->getMessage());
// }

$language = isset($_POST['language']) ? $_POST['language'] : 'english';

$weatherData = array(
    'temperature' => 40,
    'rainfall' => true,
    'windspeed' => 15
);

function generatePrompt($weatherData, $language)
{
    //TODO: qualifiers
    $temperatureTextFr = $weatherData['temperature'] . ' degrés Celsius.';
    $rainfallTextFr = $weatherData['rainfall'] ? 'Il pleut.' : 'Il ne pleut pas.';
    $windspeedTextFr = $weatherData['windspeed'] . ' km/h.';

    $temperatureTextEn = $weatherData['temperature'] . ' degrees Celsius.';
    $rainfallTextEn = $weatherData['rainfall'] ? 'There is rainfall.' : 'There is no rainfall.';
    $windspeedTextEn = $weatherData['windspeed'] . ' km/h.';

    if ($language === 'french') {
        return "<prompt xml:lang=\"fr-fr\">La température actuelle est $temperatureTextFr $rainfallTextFr La vitesse du vent est $windspeedTextFr</prompt>";
    } else {
        return "<prompt>Current temperature is $temperatureTextEn $rainfallTextEn Windspeed is $windspeedTextEn</prompt>";
    }
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