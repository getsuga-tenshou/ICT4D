// backend/models/Details.js
const { Sequelize, DataTypes } = require("sequelize");
const sequelize = require("../sequelize");
const User = require("./User");

const Details = sequelize.define("Details", {
  temperature: {
    type: DataTypes.FLOAT,
    allowNull: false,
  },
  wind_speed: {
    type: DataTypes.FLOAT,
    allowNull: false,
  },
  rainfall: {
    type: DataTypes.FLOAT,
    allowNull: false,
  },
});

Details.belongsTo(User, { foreignKey: "userId" });

module.exports = Details;
