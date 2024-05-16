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
    cursor.execute("SELECT DATE(date) as date, AVG(temperature) as temperature, AVG(rainfall) as rainfall, AVG(wind_speed) as wind_speed FROM report GROUP BY DATE(date)")
    data = cursor.fetchall()
    conn.close()
    return data

def prepare_data(data):
    df = pd.DataFrame(data, columns=['date', 'temperature', 'rainfall', 'wind_speed'])
    df.dropna(inplace=True)
    
    df['avg_temp'] = df['temperature'].expanding().mean()
    df['avg_rain'] = df['rainfall'].expanding().mean()
    df['avg_wind'] = df['wind_speed'].expanding().mean()

    df.dropna(inplace=True)

    X = df[['avg_temp', 'avg_rain', 'avg_wind']]
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
    conn = mysql.connector.connect(
        host="sql.freedb.tech",
        user="freedb_meteouser",
        password="YdQnV8fT3%?wpZ&",
        database="freedb_meteodb"
    )
    cursor = conn.cursor()
    cursor.execute("INSERT INTO report (temperature, rainfall, wind_speed, date, location, isAlert) VALUES (%s, %s, %s, %s, %s, %d)",
                    (predictions[0], predictions[1], predictions[2], date, location, 0))
    conn.commit()
    conn.close()

if __name__ == "__main__":
    data = fetch_data()
    X_y_pairs = prepare_data(data)
    models = train_models(X_y_pairs)
    
    # Predict and insert data for the next 24 hours
    current_time = datetime.now()
    location = 'Araouan'
    for hour in range(24):
        prediction_time = current_time + timedelta(hours=hour)
        avg_temp = X_y_pairs[0][0]['avg_temp'].iloc[-1]
        avg_rain = X_y_pairs[0][0]['avg_rain'].iloc[-1]
        avg_wind = X_y_pairs[0][0]['avg_wind'].iloc[-1]
        
        X_pred = pd.DataFrame([[avg_temp, avg_rain, avg_wind]], columns=['avg_temp', 'avg_rain', 'avg_wind'])

        predictions = []
        for model in models:
            prediction = model.predict(X_pred)
            predictions.append(float(prediction[0]))

        insert_predictions(predictions, prediction_time.strftime('%Y-%m-%d %H:%M:%S'), location)
    
    print("Weather predictions for the next 24 hours inserted successfully!")
