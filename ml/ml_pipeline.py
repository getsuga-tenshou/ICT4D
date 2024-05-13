import mysql.connector
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LinearRegression
from sklearn.metrics import accuracy_score
from datetime import datetime


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
    X_test_all, y_test_all = [], []
    for X, y in X_y_pairs:
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        model = LinearRegression()
        model.fit(X_train, y_train)
        models.append(model)
        X_test_all.append(X_test)
        y_test_all.append(y_test)
    return models, X_test_all, y_test_all

def insert_predictions(predictions, date, location):
    conn = mysql.connector.connect(
        host="sql.freedb.tech",
        user="freedb_meteouser",
        password="YdQnV8fT3%?wpZ&",
        database="freedb_meteodb"
    )
    cursor = conn.cursor()
    # print(predictions)
    cursor.execute("INSERT INTO predictions (predicted_temp, predicted_rainfall, predicted_wind_speed, date, location) VALUES (%s, %s, %s, %s, %s)",
                    (predictions[0], predictions[1], predictions[2], date, location))
    conn.commit()
    conn.close()

if __name__ == "__main__":
    data = fetch_data()
    X_y_pairs = prepare_data(data)
    models, X_test_all, y_test_all = train_models(X_y_pairs)
    predictions = []

    for model, X_test, y_test in zip(models, X_test_all, y_test_all):
        prediction = model.predict(X_test)
        predictions.append(int(prediction[0]))
    
    date = datetime.now().strftime('%Y-%m-%d')
    location = 'Araouan'
    insert_predictions(predictions, date, location)