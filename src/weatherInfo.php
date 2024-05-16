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

try {
    $currentQuery = "SELECT temperature, wind_speed, rainfall FROM report WHERE date <= :currentTime ORDER BY date DESC LIMIT 1";
    $stmt = $pdo->prepare($currentQuery);
    $stmt->execute(['currentTime' => $currentTime]);
    $currentWeatherData = $stmt->fetch(PDO::FETCH_ASSOC);

    $averageQuery = "SELECT AVG(temperature) as avg_temperature, AVG(wind_speed) as avg_wind_speed, AVG(rainfall) as avg_rainfall FROM report WHERE date > :currentTime AND date <= :threeHoursLater";
    $stmt = $pdo->prepare($averageQuery);
    $stmt->execute(['currentTime' => $currentTime, 'threeHoursLater' => $threeHoursLater]);
    $averageWeatherData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();
    $pdo = null;
} catch (PDOException $e) {
    die("Error: Could not retrieve data. " . $e->getMessage());
}

$language = isset($_POST['language']) ? $_POST['language'] : 'english';
$currentDateTime = new DateTime();
$currentTime = $currentDateTime->format('Y-m-d H:i:s');
$threeHoursLater = $currentDateTime->modify('+3 hours')->format('Y-m-d H:i:s');

// $weatherData = array(
//     'temperature' => 40,
//     'rainfall' => 1.2,
//     'windspeed' => 15
// );

function generatePrompt($currentWeatherData, $averageWeatherData, $language)
{
    $currentTemperatureTextFr = $currentWeatherData['temperature'] . ' degrés Celsius.';
    $currentRainfallTextFr = $currentWeatherData['rainfall'] ? 'Il pleut avec une quantité de ' . number_format($currentWeatherData['rainfall'], 1) . 'mm.' : 'Il ne pleut pas.';
    $currentWindSpeedTextFr = $currentWeatherData['wind_speed'] . ' km/h.';

    $currentTemperatureTextEn = $currentWeatherData['temperature'] . ' degrees Celsius.';
    $currentRainfallTextEn = $currentWeatherData['rainfall'] ? 'It is raining with an amount of ' . number_format($currentWeatherData['rainfall'], 1) . 'mm.' : 'It is not raining.';
    $currentWindSpeedTextEn = $currentWeatherData['wind_speed'] . ' km/h.';

    $averageTemperatureTextFr = number_format($averageWeatherData['avg_temperature'], 1) . ' degrés Celsius.';
    $averageRainfallTextFr = $averageWeatherData['avg_rainfall'] ? 'Il pleut avec une quantité moyenne de ' . number_format($averageWeatherData['avg_rainfall'], 1) . 'mm.' : 'Il ne pleut pas.';
    $averageWindSpeedTextFr = number_format($averageWeatherData['avg_wind_speed'], 1) . ' km/h.';

    $averageTemperatureTextEn = number_format($averageWeatherData['avg_temperature'], 1) . ' degrees Celsius.';
    $averageRainfallTextEn = $averageWeatherData['avg_rainfall'] ? 'It is raining with an average amount of ' . number_format($averageWeatherData['avg_rainfall'], 1) . 'mm.' : 'It is not raining.';
    $averageWindSpeedTextEn = number_format($averageWeatherData['avg_wind_speed'], 1) . ' km/h.';

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

    $currentTemperatureQualifier = '';
    foreach ($temperatureRanges as $range) {
        if ($currentWeatherData['temperature'] >= $range['min'] && $currentWeatherData['temperature'] < $range['max']) {
            $currentTemperatureQualifier = $range['qualifier'];
            break;
        }
    }

    $currentWindQualifier = '';
    foreach ($windSpeedRanges as $range) {
        if ($currentWeatherData['wind_speed'] >= $range['min'] && $currentWeatherData['wind_speed'] < $range['max']) {
            $currentWindQualifier = $range['qualifier'];
            break;
        }
    }

    $currentRainfallQualifier = '';
    foreach ($rainfallRanges as $range) {
        if ($currentWeatherData['rainfall'] >= $range['min'] && $currentWeatherData['rainfall'] < $range['max']) {
            $currentRainfallQualifier = $range['qualifier'];
            break;
        }
    }

    $averageTemperatureQualifier = '';
    foreach ($temperatureRanges as $range) {
        if ($averageWeatherData['avg_temperature'] >= $range['min'] && $averageWeatherData['avg_temperature'] < $range['max']) {
            $averageTemperatureQualifier = $range['qualifier'];
            break;
        }
    }

    $averageWindQualifier = '';
    foreach ($windSpeedRanges as $range) {
        if ($averageWeatherData['avg_wind_speed'] >= $range['min'] && $averageWeatherData['avg_wind_speed'] < $range['max']) {
            $averageWindQualifier = $range['qualifier'];
            break;
        }
    }

    $averageRainfallQualifier = '';
    foreach ($rainfallRanges as $range) {
        if ($averageWeatherData['avg_rainfall'] >= $range['min'] && $averageWeatherData['avg_rainfall'] < $range['max']) {
            $averageRainfallQualifier = $range['qualifier'];
            break;
        }
    }

    $prompts = [];

    if ($language === 'french') {
        $prompts[] = "<prompt xml:lang=\"fr-fr\">La température actuelle est $currentTemperatureTextFr $currentTemperatureQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">$currentRainfallTextFr $currentRainfallQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">La vitesse du vent est $currentWindSpeedTextFr $currentWindQualifier</prompt>";

        $prompts[] = "<prompt xml:lang=\"fr-fr\">La température moyenne pour les trois prochaines heures est $averageTemperatureTextFr $averageTemperatureQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">$averageRainfallTextFr $averageRainfallQualifier</prompt>";
        $prompts[] = "<prompt xml:lang=\"fr-fr\">La vitesse moyenne du vent pour les trois prochaines heures est $averageWindSpeedTextFr $averageWindQualifier</prompt>";
    } else {
        $prompts[] = "<prompt>Current temperature is $currentTemperatureTextEn $currentTemperatureQualifier</prompt>";
        $prompts[] = "<prompt>$currentRainfallTextEn $currentRainfallQualifier</prompt>";
        $prompts[] = "<prompt>Windspeed is $currentWindSpeedTextEn $currentWindQualifier</prompt>";

        $prompts[] = "<prompt>The average temperature for the next three hours is $averageTemperatureTextEn $averageTemperatureQualifier</prompt>";
        $prompts[] = "<prompt>$averageRainfallTextEn $averageRainfallQualifier</prompt>";
        $prompts[] = "<prompt>The average windspeed for the next three hours is $averageWindSpeedTextEn $averageWindQualifier</prompt>";
    }

    return implode('', $prompts);
}

$englishPrompt = generatePrompt($currentWeatherData, $averageWeatherData, 'english');
$frenchPrompt = generatePrompt($currentWeatherData, $averageWeatherData, 'french');

header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<vxml version="2.1">
    <!-- Language Selection -->
    <form id="languageSelection">
        <field name="languageChoice">
            <prompt>Select your language. For English, press 1. Pour le français, appuyez sur 2.</prompt>
            <grammar xml:lang="en-US" root="languageChoice" type="application/grammar+voicexml">
                <rule id="languageChoice" scope="public">
                    <one-of>
                        <item dtmf="1">english</item>
                        <item dtmf="2">french</item>
                    </one-of>
                </rule>
            </grammar>
        </field>
        <filled>
            <if cond="languageChoice == 'english'">
                <goto next="#mainMenuEnglish" />
            </if>
            <elseif cond="languageChoice == 'french'">
                <goto next="#mainMenuFrench" />
            </elseif>
            <else>
                <prompt>Invalid option, please try again.</prompt>
                <reprompt />
            </else>
        </filled>
    </form>

    <!-- Main Menu English -->
    <form id="mainMenuEnglish">
        <field name="menuChoice">
            <prompt>Welcome to the Weather Voice Service. Please choose an option. For Weather Forecast, press 1. For Wind Alerts, press 2. To return to the main menu at any time, press 0.</prompt>
            <grammar xml:lang="en-US" root="menuChoice" type="application/grammar+voicexml">
                <rule id="menuChoice" scope="public">
                    <one-of>
                        <item dtmf="1">1</item>
                        <item dtmf="2">2</item>
                        <item dtmf="0">0</item>
                    </one-of>
                </rule>
            </grammar>
        </field>
        <filled>
            <switch cond="menuChoice">
                <case expr="'1'">
                    <goto next="#weatherForecastEnglish" />
                </case>
                <case expr="'2'">
                    <goto next="#windAlertsEnglish" />
                </case>
                <default>
                    <goto next="#mainMenuEnglish" />
                </default>
            </switch>
        </filled>
    </form>

    <!-- Weather Forecast English -->
    <form id="weatherForecastEnglish">
        <block>
            <prompt><?php echo $englishPrompt; ?></prompt>
            <goto next="#mainMenuEnglish" />
        </block>
    </form>

    <!-- Main Menu French -->
    <form id="mainMenuFrench">
        <field name="menuChoice">
            <prompt xml:lang="fr-FR">Bienvenue au Service Vocal Météo. Veuillez choisir une option. Pour la prévision météorologique, appuyez sur 1. Pour les alertes de vent, appuyez sur 2. Pour retourner au menu principal à tout moment, appuyez sur 0.</prompt>
            <grammar xml:lang="fr-FR" root="menuChoice" type="application/grammar+voicexml">
                <rule id="menuChoice" scope="public">
                    <one-of>
                        <item dtmf="1">1</item>
                        <item dtmf="2">2</item>
                        <item dtmf="0">0</item>
                    </one-of>
                </rule>
            </grammar>
        </field>
        <filled>
            <switch cond="menuChoice">
                <case expr="'1'">
                    <goto next="#weatherForecastFrench" />
                </case>
                <case expr="'2'">
                    <goto next="#windAlertsFrench" />
                </case>
                <default>
                    <goto next="#mainMenuFrench" />
                </default>
            </switch>
        </filled>
    </form>

    <!-- Weather Forecast French -->
    <form id="weatherForecastFrench">
        <block>
            <prompt xml:lang="fr-FR"><?php echo $frenchPrompt; ?></prompt>
            <goto next="#mainMenuFrench" />
        </block>
    </form>
</vxml>