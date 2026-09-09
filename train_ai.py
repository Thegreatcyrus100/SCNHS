"""
SCNHS Dropout Risk Model Trainer
----------------------------------
Trains a Random Forest model on student data and saves it as dropout_model.pkl.

Features used:
  - attendance_rate  : % of classes attended (0–100)
  - grades_average   : average grade across subjects (0–100)

Label:
  - dropout_risk     : 1 = High risk, 0 = Low risk

Run this BEFORE starting ai_api.py or deploying to Render.
"""

import pickle
import os
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, accuracy_score

# ─────────────────────────────────────────
# Synthetic Training Data
# More rows = better generalization
# ─────────────────────────────────────────
data = {
    "attendance_rate": [
        95, 90, 88, 92, 85, 80, 78, 75, 70, 65,
        60, 55, 50, 45, 40, 35, 30, 25, 20, 15,
        93, 87, 83, 79, 74, 68, 62, 57, 48, 38,
        96, 91, 86, 81, 76, 71, 66, 61, 56, 51,
    ],
    "grades_average": [
        92, 88, 85, 90, 82, 78, 75, 70, 68, 65,
        60, 55, 50, 48, 45, 40, 38, 35, 30, 25,
        91, 84, 80, 76, 72, 67, 63, 58, 52, 42,
        94, 89, 83, 79, 74, 69, 64, 59, 54, 49,
    ],
    "dropout_risk": [
        # 0 = Low risk (good attendance + grades)
        0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        # 1 = High risk (poor attendance + grades)
        1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
        # Mixed
        0, 0, 0, 0, 1, 1, 1, 1, 1, 1,
        0, 0, 0, 0, 1, 1, 1, 1, 1, 1,
    ]
}

df = pd.DataFrame(data)

# ─────────────────────────────────────────
# Train / Test Split
# ─────────────────────────────────────────
X = df[["attendance_rate", "grades_average"]]
y = df["dropout_risk"]

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

# ─────────────────────────────────────────
# Train Model
# ─────────────────────────────────────────
model = RandomForestClassifier(
    n_estimators=100,
    max_depth=5,
    random_state=42
)
model.fit(X_train, y_train)

# ─────────────────────────────────────────
# Evaluate
# ─────────────────────────────────────────
y_pred = model.predict(X_test)
accuracy = accuracy_score(y_test, y_pred)

print("\n" + "="*50)
print("  SCNHS Dropout Risk Model — Training Report")
print("="*50)
print(f"  Training samples : {len(X_train)}")
print(f"  Test samples     : {len(X_test)}")
print(f"  Accuracy         : {accuracy * 100:.1f}%")
print("\n  Classification Report:")
print(classification_report(y_test, y_pred, target_names=["Low Risk", "High Risk"]))

# ─────────────────────────────────────────
# Save Model
# ─────────────────────────────────────────
output_path = os.path.join(os.path.dirname(__file__), "dropout_model.pkl")
with open(output_path, "wb") as f:
    pickle.dump(model, f)

print(f"✅ Model saved to: {output_path}")
print("   You can now start ai_api.py or deploy to Render.\n")
