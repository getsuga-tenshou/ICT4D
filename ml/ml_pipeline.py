import mysql.connector
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LinearRegression
from datetime import datetime, timedelta


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
    df['hour_of_day'] = df['date'].dt.hour

    df['last_3_hour_avg_temp'] = df['temperature'].shift(1).rolling(window=3, min_periods=1).mean()
    df['last_12_hour_avg_temp'] = df['temperature'].shift(1).rolling(window=12, min_periods=1).mean()
    df['last_day_avg_temp'] = df['temperature'].shift(24).rolling(window=24, min_periods=1).mean()

    df.dropna(inplace=True)

    X = df[['last_3_hour_avg_temp', 'last_12_hour_avg_temp', 'last_day_avg_temp', 'hour_of_day']]
    y_temp = df['temperature']
    y_rain = df['rainfall']
    y_wind = df['wind_speed']

    return (X, y_temp), (X, y_rain), (X, y_wind)



def train_models(X_y_pairs):
    models = []
    for X, y in X_y_pairs:
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        model = LinearRegression()
        model.fit(X_train, y_train)
        models.append(model)
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
        "INSERT INTO report (temperature, rainfall, wind_speed, date, location) VALUES (%s, %s, %s, %s, %s)",
        (predictions[0], predictions[1], predictions[2], date, location))
    conn.commit()
    conn.close()


if __name__ == "__main__":
    data = fetch_data()
    # print(data)
    X_y_pairs = prepare_data(data)
    # print('debug')
    # print(X_y_pairs)
    models = train_models(X_y_pairs)

    current_time = datetime.now().replace(minute=0, second=0, microsecond=0)
    location = 'Araouan'
    for hour in range(24):
        prediction_time = current_time + timedelta(hours=hour)

        prediction_features = [
            X_y_pairs[0][0]['last_3_hour_avg_temp'].iloc[-1],
            X_y_pairs[0][0]['last_12_hour_avg_temp'].iloc[-1],
            X_y_pairs[0][0]['last_day_avg_temp'].iloc[-1],
            prediction_time.hour
        ]

        X_pred = pd.DataFrame([prediction_features],
                              columns=[ 'last_3_hour_avg_temp',
                                       'last_12_hour_avg_temp', 'last_day_avg_temp', 'hour_of_day'])

        predictions = []
        for model in models:
            prediction = model.predict(X_pred)
            predictions.append(float(prediction[0]))

        # Ensure rainfall and wind_speed predictions are non-negative
        predictions[1] = max(0, predictions[1])
        predictions[2] = max(0, predictions[2])

        insert_predictions(predictions, prediction_time.strftime('%Y-%m-%d %H:%M:%S'), location)

    print("Weather predictions for the next 24 hours inserted successfully!")
