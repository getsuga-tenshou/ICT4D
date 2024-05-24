<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection details
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

// Get the current server time in hours
$currentHour = date('H');
$currentDate = date('Y-m-d');

// Fetch weather data for the current hour and the next three hours
$query = "
    SELECT date, temperature, wind_speed, rainfall, isAlert 
    FROM report 
    WHERE DATE(date) = :currentDate 
    AND HOUR(date) BETWEEN :currentHour AND :nextHour";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':currentDate' => $currentDate,
        ':currentHour' => $currentHour,
        ':nextHour' => $currentHour + 3
    ]);

    $weatherData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $pdo = null;
} catch (PDOException $e) {
    die("Error: Could not retrieve data. " . $e->getMessage());
}

function generateAlertPrompt($weatherData, $language)
{
    $alertText = '';

    foreach ($weatherData as $data) {
        if ($data['isAlert'] == 1) {
            if ($data['temperature'] > 40) {
                $alertText .= $language === 'french' ? "<prompt xml:lang=\"fr-FR\">Alerte de vague de chaleur: La température est de " . $data['temperature'] . " degrés Celsius.</prompt>" : "<prompt>Heatwave alert: The temperature is " . $data['temperature'] . " degrees Celsius.</prompt>";
            }
            if ($data['rainfall'] > 200) {
                $alertText .= $language === 'french' ? "<prompt xml:lang=\"fr-FR\">Alerte d'inondation éclair: Les précipitations sont de " . $data['rainfall'] . " millimètres.</prompt>" : "<prompt>Flash flood warning: The rainfall is " . $data['rainfall'] . " millimeters.</prompt>";
            }
            if ($data['wind_speed'] > 20) {
                $alertText .= $language === 'french' ? "<prompt xml:lang=\"fr-FR\">Alerte de tempête: La vitesse du vent est de " . $data['wind_speed'] . " kilomètres par heure.</prompt>" : "<prompt>Storm warning: The wind speed is " . $data['wind_speed'] . " kilometers per hour.</prompt>";
            }
            break;
        }
    }

    return $alertText;
}

function generateWeatherForecastPrompt($weatherData, $language)
{
    $forecastText = '';
    $hours = ['current', 'next', 'following', 'then'];

    if ($language === 'french') {
        $forecastText .= '<prompt xml:lang="fr-FR">Les températures pour les prochaines heures sont :</prompt>';
    } else {
        $forecastText .= '<prompt>The temperatures for the upcoming hours are:</prompt>';
    }

    foreach ($weatherData as $index => $data) {
        $hourText = $index == 0 ? ($language === 'french' ? 'actuelle' : 'current') : "$hours[$index] hour";
        $temperatureText = $language === 'french' ? "La température $hourText à " . date('H:i', strtotime($data['date'])) . " est " . $data['temperature'] . " degrés Celsius." : "The $hourText temperature at " . date('H:i', strtotime($data['date'])) . " is " . $data['temperature'] . " degrees Celsius.";
        $forecastText .= "<prompt>$temperatureText</prompt>";
    }

    return $forecastText;
}

$language = isset($_POST['language']) ? $_POST['language'] : 'english';
$alertPrompt = generateAlertPrompt($weatherData, $language);
$weatherForecastPrompt = generateWeatherForecastPrompt($weatherData, $language);

header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<vxml version="2.1">
  <!-- Main Menu for Language Selection -->
  <form id="mainMenu">
    <block>
      <prompt>
        Welcome to our Meteo Weele. Press 1 for English, Press 2 for French.
      </prompt>
    </block>
    <field name="menuChoice">
      <grammar type="application/grammar+xml">
        <![CDATA[
          <grammar version="1.0" xml:lang="en-US" mode="dtmf">
            <rule id="main">
              <one-of>
                <item>
                  <ruleref uri="#english" />
                </item>
                <item>
                  <ruleref uri="#french" />
                </item>
              </one-of>
            </rule>
            <rule id="english" scope="public">
              <one-of>
                <item>1</item>
              </one-of>
            </rule>
            <rule id="french" scope="public">
              <one-of>
                <item>2</item>
              </one-of>
            </rule>
          </grammar>
        ]]>
      </grammar>
      <prompt>
        Please press 1 for English or 2 for French.
      </prompt>
      <nomatch>
        I'm sorry, I didn't understand. Please press 1 for English or 2 for French.
      </nomatch>
      <noinput>
        I'm sorry, I didn't hear anything. Please press 1 for English or 2 for French.
      </noinput>
    </field>
    <filled>
      <if cond="menuChoice == '1'">
        <goto next="#englishMenu" />
      </if>
      <if cond="menuChoice == '2'">
        <goto next="#frenchMenu" />
      </if>
    </filled>
  </form>

  <!-- English Menu -->
  <form id="englishMenu">
    <block>
      <?php echo $alertPrompt; ?>
      <prompt>
        Please choose an option. Press 1 for Weather Forecast, Press 2 for Wind Alerts, Press 3 for Rainfall Information.
      </prompt>
    </block>
    <field name="englishChoice">
      <grammar type="application/grammar+xml">
        <![CDATA[
          <grammar version="1.0" xml:lang="en-US" mode="dtmf">
            <rule id="main">
              <one-of>
                <item>1</item>
                <item>2</item>
                <item>3</item>
              </one-of>
            </rule>
          </grammar>
        ]]>
      </grammar>
      <prompt>
        Press 1 for Weather Forecast, Press 2 for Wind Alerts, Press 3 for Rainfall Information.
      </prompt>
      <nomatch>
        I'm sorry, I didn't understand. Press 1 for Weather Forecast, Press 2 for Wind Alerts, Press 3 for Rainfall Information.
      </nomatch>
      <noinput>
        I'm sorry, I didn't hear anything. Press 1 for Weather Forecast, Press 2 for Wind Alerts, Press 3 for Rainfall Information.
      </noinput>
    </field>
    <filled>
      <if cond="englishChoice == '1'">
        <goto next="#weatherForecast" />
      </if>
      <if cond="englishChoice == '2'">
        <goto next="#windAlerts" />
      </if>
      <if cond="englishChoice == '3'">
        <goto next="#rainfallInfo" />
      </if>
    </filled>
  </form>

  <!-- French Menu -->
  <form id="frenchMenu">
    <block>
      <?php echo $alertPrompt; ?>
      <prompt>
        Veuillez choisir une option. Appuyez sur 1 pour les prévisions météorologiques, Appuyez sur 2 pour les alertes de vent, Appuyez sur 3 pour les informations sur les précipitations.
      </prompt>
    </block>
    <field name="frenchChoice">
      <grammar type="application/grammar+xml">
        <![CDATA[
          <grammar version="1.0" xml:lang="fr-FR" mode="dtmf">
            <rule id="main">
              <one-of>
                <item>1</item>
                <item>2</item>
                <item>3</item>
              </one-of>
            </rule>
          </grammar>
        ]]>
      </grammar>
      <prompt>
        Appuyez sur 1 pour les prévisions météorologiques, Appuyez sur 2 pour les alertes de vent, Appuyez sur 3 pour les informations sur les précipitations.
      </prompt>
      <nomatch>
        Désolé, je n'ai pas compris. Appuyez sur 1 pour les prévisions météorologiques, Appuyez sur 2 pour les alertes de vent, Appuyez sur 3 pour les informations sur les précipitations.
      </nomatch>
      <noinput>
        Désolé, je n'ai rien entendu. Appuyez sur 1 pour les prévisions météorologiques, Appuyez sur 2 pour les alertes de vent, Appuyez sur 3 pour les informations sur les précipitations.
      </noinput>
    </field>
    <filled>
      <if cond="frenchChoice == '1'">
        <goto next="#weatherForecast" />
      </if>
      <if cond="frenchChoice == '2'">
        <goto next="#windAlerts" />
      </if>
      <if cond="frenchChoice == '3'">
        <goto next="#rainfallInfo" />
      </if>
    </filled>
  </form>

  <!-- Weather Forecast -->
  <form id="weatherForecast">
    <block>
      <?php echo $weatherForecastPrompt; ?>
      <exit/>
    </block>
  </form>

  <!-- Wind Alerts -->
  <form id="windAlerts">
    <block>
      <prompt>
        The current wind speed is <?php echo $weatherData[0]['wind_speed']; ?> kilometers per hour.
        <?php
        if ($weatherData[0]['wind_speed'] < 10) {
            echo 'The wind is calm.';
        } elseif ($weatherData[0]['wind_speed'] < 20) {
            echo 'The wind is moderate.';
        } elseif ($weatherData[0]['wind_speed'] < 30) {
            echo 'The wind is strong.';
        } else {
            echo 'The wind is very strong.';
        }
        ?>
      </prompt>
      <exit/>
    </block>
  </form>

  <!-- Rainfall Information -->
  <form id="rainfallInfo">
    <block>
      <prompt>
        The current rainfall amount is <?php echo $weatherData[0]['rainfall']; ?> millimeters.
        <?php
        if ($weatherData[0]['rainfall'] == 0) {
            echo 'It is not raining.';
        } elseif ($weatherData[0]['rainfall'] < 5) {
            echo 'It is lightly raining.';
        } elseif ($weatherData[0]['rainfall'] < 10) {
            echo 'It is moderately raining.';
        } elseif ($weatherData[0]['rainfall'] < 20) {
            echo 'It is heavily raining.';
        } else {
            echo 'It is raining very heavily.';
        }
        ?>
      </prompt>
      <exit/>
    </block>
  </form>
</vxml>
