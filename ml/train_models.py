import pandas as pd
from sklearn.linear_model import LinearRegression
import joblib

df = pd.read_csv('yearly_weather.csv')

# Filter data to start from 2023-05-18
df['date'] = pd.to_datetime(df['date'])
df = df[df['date'] >= '2023-05-18']
df.dropna(inplace=True
)

# Add month, day, and hour columns from the date column
df['month'] = df['date'].dt.month
df['day'] = df['date'].dt.day
df['hour'] = df['date'].dt.hour

# Train linear regression models
models = {}
for target_feature in ['temperature', 'wind_speed', 'rainfall']:
    # Select features
    features = ['hour','day','month']
    features.append(f'last_3_hour_avg_{target_feature}')
    features.append(f'last_12_hour_avg_{target_feature}')
    features.append(f'last_day_avg_{target_feature}')

    # Calculate rolling averages for the target feature
    df[f'last_3_hour_avg_{target_feature}'] = df[target_feature].shift(1).rolling(window=3, min_periods=1).mean()
    df[f'last_12_hour_avg_{target_feature}'] = df[target_feature].shift(1).rolling(window=12, min_periods=1).mean()
    df[f'last_day_avg_{target_feature}'] = df[target_feature].shift(24).rolling(window=24, min_periods=1).mean()
    df.dropna(inplace=True)
    print(df)
    # Train linear regression model
    X = df[features]
    y = df[target_feature]
    model = LinearRegression()
    model.fit(X, y)
    models[target_feature] = model

# Save the trained models to disk
for target_feature, model in models.items():
    joblib.dump(model, f'model_{target_feature}.pkl')
