// backend/routes/detailRoutes.js
const express = require("express");
const Details = require("../models/Details");
const authMiddleware = require("../middleware/authMiddleware");

const router = express.Router();

// Submit weather details
router.post("/submit", authMiddleware, async (req, res) => {
  const { temperature, wind_speed, rainfall } = req.body;

  const newDetail = {
    userId: req.user,
    temperature,
    wind_speed,
    rainfall,
  };

  try {
    await Details.create(newDetail);
    res.status(201).json({ message: "Details submitted successfully" });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

// Get user's past details
router.get("/details", authMiddleware, async (req, res) => {
  try {
    const details = await Details.findAll({ where: { userId: req.user } });
    res.status(200).json(details);
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

module.exports = router;
