"""
SCNHS Dropout Risk Prediction API
----------------------------------
Model features:
  - attendance_rate  : float (0–100)
  - grades_average   : float (0–100)

Output:
  - dropout_risk: "High" | "Low"
  - confidence  : float (0.0–1.0)

Deploy this file to Render.com (see DEPLOYMENT_GUIDE.md)
"""

import os
import pickle
import logging

from flask import Flask, request, jsonify
from flask_cors import CORS

# ─────────────────────────────────────────
# App Setup
# ─────────────────────────────────────────
app = Flask(__name__)

# Allow cross-origin requests from any host (InfinityFree PHP → Render API)
CORS(app, resources={r"/*": {"origins": "*"}})

# Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# ─────────────────────────────────────────
# Load Model (once at startup)
# ─────────────────────────────────────────
MODEL_PATH = os.path.join(os.path.dirname(__file__), "dropout_model.pkl")

model = None
if os.path.exists(MODEL_PATH):
    with open(MODEL_PATH, "rb") as f:
        model = pickle.load(f)
    logger.info("✅ dropout_model.pkl loaded successfully.")
else:
    logger.warning("⚠️  dropout_model.pkl not found. Run train_ai.py first.")

# Expected feature columns (must match train_ai.py)
FEATURES = ["attendance_rate", "grades_average"]


# ─────────────────────────────────────────
# Helper: Validate Input
# ─────────────────────────────────────────
def validate_input(data: dict) -> tuple[dict | None, str | None]:
    """
    Validates that all required features are present and within range.
    Returns (clean_data, None) on success, or (None, error_message) on failure.
    """
    cleaned = {}
    for feature in FEATURES:
        if feature not in data:
            return None, f"Missing required field: '{feature}'"
        try:
            value = float(data[feature])
        except (ValueError, TypeError):
            return None, f"Field '{feature}' must be a number."
        if not (0.0 <= value <= 100.0):
            return None, f"Field '{feature}' must be between 0 and 100."
        cleaned[feature] = value
    return cleaned, None


# ─────────────────────────────────────────
# Routes
# ─────────────────────────────────────────

@app.route("/", methods=["GET"])
def index():
    """Root endpoint — basic API info."""
    return jsonify({
        "api": "SCNHS Dropout Risk Prediction API",
        "version": "1.0.0",
        "status": "online",
        "endpoints": {
            "GET  /health": "Health check",
            "POST /predict": "Predict dropout risk"
        }
    })


@app.route("/health", methods=["GET"])
def health():
    """
    Health check endpoint.
    Render uses this to confirm the service is alive.
    """
    return jsonify({
        "status": "healthy",
        "model_loaded": model is not None
    }), 200


@app.route("/predict", methods=["POST"])
def predict():
    """
    Predict dropout risk for a student.

    Request body (JSON):
    {
        "attendance_rate": 75,
        "grades_average": 60
    }

    Response:
    {
        "dropout_risk": "High",
        "confidence": 0.83,
        "attendance_rate": 75,
        "grades_average": 60
    }
    """
    # 1. Model availability check
    if model is None:
        return jsonify({
            "error": "Model not loaded. Please run train_ai.py first."
        }), 503

    # 2. Parse JSON body
    if not request.is_json:
        return jsonify({
            "error": "Content-Type must be application/json."
        }), 415

    data = request.get_json(silent=True)
    if data is None:
        return jsonify({
            "error": "Invalid or empty JSON body."
        }), 400

    # 3. Validate
    cleaned, error = validate_input(data)
    if error:
        return jsonify({"error": error}), 400

    # 4. Predict
    try:
        import pandas as pd
        df = pd.DataFrame([cleaned], columns=FEATURES)
        prediction = model.predict(df)[0]
        proba = model.predict_proba(df)[0]

        risk_label = "High" if prediction == 1 else "Low"
        # Confidence = probability of the predicted class
        confidence = round(float(proba[int(prediction)]), 4)

        logger.info(
            f"Prediction: {risk_label} "
            f"(attendance={cleaned['attendance_rate']}, "
            f"grades={cleaned['grades_average']}, "
            f"confidence={confidence})"
        )

        return jsonify({
            "dropout_risk": risk_label,
            "confidence": confidence,
            "attendance_rate": cleaned["attendance_rate"],
            "grades_average": cleaned["grades_average"]
        }), 200

    except Exception as e:
        logger.error(f"Prediction error: {e}")
        return jsonify({"error": f"Prediction failed: {str(e)}"}), 500


# ─────────────────────────────────────────
# Run (local dev only — Render uses Gunicorn)
# ─────────────────────────────────────────
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
