import requests
import pandas as pd
from datetime import datetime, timedelta

def fetch_monthly_weather_data(api_key, latitude, longitude, start_date, end_date):
    weather_data = []
    current_date = start_date
    while current_date <= end_date:
        for hour in range(24):
            date = current_date.strftime("%Y-%m-%d")
            url = f"https://api.weatherapi.com/v1/history.json?q={latitude},{longitude}&key={api_key}&dt={date}&hour={hour}"
            print(url)
            response = requests.get(url)
            if response.status_code == 200:
                data = response.json()
                if 'forecast' in data and 'forecastday' in data['forecast'] and len(data['forecast']['forecastday']) > 0:
                    hour_data = data['forecast']['forecastday'][0]['hour'][0]
                    weather_data.append({
                        'date': hour_data['time'],
                        'temperature': hour_data['temp_c'],
                        'wind_speed': hour_data['wind_kph'],  # Convert kph to m/s
                        'rainfall': hour_data['precip_mm']  # Convert mm to meters
                    })
            else:
                print(f"Failed to fetch weather data for {date} hour {hour}. Status code: {response.status_code}")
        current_date += timedelta(days=1)
    return weather_data

def save_to_csv(weather_data, filename):
    df = pd.DataFrame(weather_data)
    df.to_csv(filename, index=False)

if __name__ == "__main__":
    api_key = "f53317d9afcf4cce844123315242604"
    latitude = 18
    longitude = -4
    start_date = datetime(2023, 5, 16)  # Start date
    end_date = datetime(2024, 5, 16)  # End date
    filename = "monthly_weather.csv"

    weather_data = fetch_monthly_weather_data(api_key, latitude, longitude, start_date, end_date)
    save_to_csv(weather_data, filename)
