// backend/server.js
const express = require("express");
const sequelize = require("./sequelize");
const cors = require("cors");
const authRoutes = require("./routes/authRoutes");
const userRoutes = require("./routes/userRoutes");
const detailRoutes = require("./routes/detailRoutes");
require("dotenv").config();

const app = express();
app.use(express.json());
app.use(cors());

sequelize
  .sync({ force: false }) // If set to true, it will drop and re-create all tables on every startup
  .then(() => console.log("Database synced"))
  .catch((err) => console.log("Error: " + err));

app.use("/api/auth", authRoutes);
app.use("/api/user", userRoutes);
app.use("/api/details", detailRoutes);

const PORT = process.env.PORT || 5000;
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
