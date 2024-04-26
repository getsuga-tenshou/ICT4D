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
                <goto next="#mainMenuEnglish"/>
            </if>
            <elseif cond="languageChoice == 'french'">
                <goto next="#mainMenuFrench"/>
            </elseif>
            <else>
                <prompt>Invalid option, please try again.</prompt>
                <reprompt/>
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
                    <goto next="#weatherForecastEnglish"/>
                </case>
                <case expr="'2'">
                    <goto next="#windAlertsEnglish"/>
                </case>
                <default>
                    <goto next="#mainMenuEnglish"/>
                </default>
            </switch>
        </filled>
    </form>

    <!-- Weather Forecast English -->
    <form id="weatherForecastEnglish">
        <block>
            <prompt expr="'Today’s weather features a temperature of ' + weatherData.temperature + ' degrees Celsius, ' + (weatherData.rainfall > 0 ? 'with rainfall of ' + weatherData.rainfall + ' mm' : 'no rainfall') + ', and a wind speed of ' + weatherData.wind_speed + ' km per hour.'"></prompt>
            <goto next="#mainMenuEnglish"/>
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
                    <goto next="#weatherForecastFrench"/>
                </case>
                <case expr="'2'">
                    <goto next="#windAlertsFrench"/>
                </case>
                <default>
                    <goto next="#mainMenuFrench"/>
                </default>
            </switch>
        </filled>
    </form>

    <!-- Weather Forecast French -->
    <form id="weatherForecastFrench">
        <block>
            <prompt xml:lang="fr-FR" expr="'La météo d’aujourd’hui présente une température de ' + weatherData.temperature + ' degrés Celsius, ' + (weatherData.rainfall > 0 ? 'avec des précipitations de ' + weatherData.rainfall + ' mm' : 'sans précipitations') + ', et une vitesse du vent de ' + weatherData.wind_speed + ' kilomètres par heure.'"></prompt>
            <goto next="#mainMenuFrench"/>
        </block>
    </form>
</vxml>
