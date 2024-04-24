-- Creating the 'report' table
CREATE TABLE IF NOT EXISTS report (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE,
    location VARCHAR(255),
    temperature FLOAT,
    humidity FLOAT,
    wind_speed FLOAT
);

-- Creating the 'alerts' table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME,
    location VARCHAR(255),
    alert_type VARCHAR(100),
    description TEXT
);