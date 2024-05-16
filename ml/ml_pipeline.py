import mysql.connector
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LinearRegression
from datetime import datetime, timedelta
import joblib

def fetch_data():
    conn = mysql.connector.connect(
        host="sql.freedb.tech",
        user="freedb_meteouser",
        password="YdQnV8fT3%?wpZ&",
        database="freedb_meteodb"
    )
    cursor = conn.cursor()
    cursor.execute(
        "SELECT date, temperature, rainfall, wind_speed FROM report")
    data = cursor.fetchall()
    conn.close()
    return data

def prepare_data(data):
    df = pd.DataFrame(data, columns=['date', 'temperature', 'rainfall', 'wind_speed'])
    df.dropna(inplace=True)

    df['date'] = pd.to_datetime(df['date'])  # Convert 'date' column to datetime format
    df['hour'] = df['date'].dt.hour
    df['month'] = df['date'].dt.month
    df['day'] = df['date'].dt.day

    df['last_3_hour_avg_temperature'] = df['temperature'].shift(1).rolling(window=3, min_periods=1).mean()
    df['last_12_hour_avg_temperature'] = df['temperature'].shift(1).rolling(window=12, min_periods=1).mean()
    df['last_day_avg_temperature'] = df['temperature'].shift(24).rolling(window=24, min_periods=1).mean()
    df['last_3_hour_avg_rainfall'] = df['rainfall'].shift(1).rolling(window=3, min_periods=1).mean()
    df['last_12_hour_avg_rainfall'] = df['rainfall'].shift(1).rolling(window=12, min_periods=1).mean()
    df['last_day_avg_rainfall'] = df['rainfall'].shift(24).rolling(window=24, min_periods=1).mean()
    df['last_3_hour_avg_wind_speed'] = df['wind_speed'].shift(1).rolling(window=3, min_periods=1).mean()
    df['last_12_hour_avg_wind_speed'] = df['wind_speed'].shift(1).rolling(window=12, min_periods=1).mean()
    df['last_day_avg_wind_speed'] = df['wind_speed'].shift(24).rolling(window=24, min_periods=1).mean()

    df.dropna(inplace=True)

    X_temp = df[['last_3_hour_avg_temperature', 'last_12_hour_avg_temperature', 'last_day_avg_temperature', 'hour', 'month', 'day']]
    X_rain = df[['last_3_hour_avg_rainfall', 'last_12_hour_avg_rainfall', 'last_day_avg_rainfall', 'hour', 'month', 'day']]
    X_wind = df[['last_3_hour_avg_wind_speed', 'last_12_hour_avg_wind_speed', 'last_day_avg_wind_speed', 'hour', 'month', 'day']]
    
    y_temp = df['temperature']
    y_rain = df['rainfall']
    y_wind = df['wind_speed']

    return (X_temp, y_temp), (X_rain, y_rain), (X_wind, y_wind)

def load_models():
    models = {}
    for target_feature in ['temperature', 'wind_speed', 'rainfall']:
        model = joblib.load(f'model_{target_feature}.pkl')
        models[target_feature] = model
    return models

def insert_predictions(predictions, date, location):
    print(predictions)
    print(date)
    print(location)
    conn = mysql.connector.connect(
        host="sql.freedb.tech",
        user="freedb_meteouser",
        password="YdQnV8fT3%?wpZ&",
        database="freedb_meteodb"
    )
    cursor = conn.cursor()
    cursor.execute(
            "INSERT INTO report (temperature, rainfall, wind_speed, date, location) "
            "VALUES (%s, %s, %s, %s, %s) "
            "ON DUPLICATE KEY UPDATE "
            "temperature = VALUES(temperature), "
            "rainfall = VALUES(rainfall), "
            "wind_speed = VALUES(wind_speed), "
            "location = VALUES(location)",
            (round(predictions[0],1), round(predictions[1],1), round(predictions[2],1), date, location))
    conn.commit()
    conn.close()

if __name__ == "__main__":
    data = fetch_data()
    X_y_pairs = prepare_data(data)
    print(X_y_pairs)
    models = load_models()

    current_time = datetime.now().replace(minute=0, second=0, microsecond=0)
    location = 'Araouan'
    for hour in range(24):
        prediction_time = current_time + timedelta(hours=hour)

        prediction_features_temp = [
            prediction_time.hour,
            prediction_time.day,
            prediction_time.month,
            X_y_pairs[0][0]['last_3_hour_avg_temperature'].iloc[hour],
            X_y_pairs[0][0]['last_12_hour_avg_temperature'].iloc[hour],
            X_y_pairs[0][0]['last_day_avg_temperature'].iloc[hour],
        ]
        
        prediction_features_rain = [
            prediction_time.hour,
            prediction_time.day,
            prediction_time.month,
            X_y_pairs[1][0]['last_3_hour_avg_rainfall'].iloc[hour],
            X_y_pairs[1][0]['last_12_hour_avg_rainfall'].iloc[hour],
            X_y_pairs[1][0]['last_day_avg_rainfall'].iloc[hour],
        ]
        
        prediction_features_wind = [
            prediction_time.hour,
            prediction_time.day,
            prediction_time.month,
            X_y_pairs[2][0]['last_3_hour_avg_wind_speed'].iloc[hour],
            X_y_pairs[2][0]['last_12_hour_avg_wind_speed'].iloc[hour],
            X_y_pairs[2][0]['last_day_avg_wind_speed'].iloc[hour],
        ]

        predictions = []
        for target_feature in ['temperature', 'rainfall', 'wind_speed']:
            model = models[target_feature]
            if target_feature == 'temperature':
                X_pred = pd.DataFrame([prediction_features_temp], columns=['hour', 'day', 'month', 'last_3_hour_avg_temperature', 'last_12_hour_avg_temperature', 'last_day_avg_temperature'])
            elif target_feature == 'rainfall':
                X_pred = pd.DataFrame([prediction_features_rain], columns=['hour', 'day', 'month', 'last_3_hour_avg_rainfall', 'last_12_hour_avg_rainfall', 'last_day_avg_rainfall'])
            elif target_feature == 'wind_speed':
                X_pred = pd.DataFrame([prediction_features_wind], columns=['hour', 'day', 'month','last_3_hour_avg_wind_speed', 'last_12_hour_avg_wind_speed', 'last_day_avg_wind_speed'])
                
            prediction = model.predict(X_pred)
            predictions.append(float(prediction[0]))

        # Ensure rainfall and wind_speed predictions are non-negative
        predictions[1] = max(0, predictions[1])
        predictions[2] = max(0, predictions[2])

        insert_predictions(predictions, prediction_time.strftime('%Y-%m-%d %H:%M:%S'), location)

    print("Weather predictions for the next day inserted successfully")
