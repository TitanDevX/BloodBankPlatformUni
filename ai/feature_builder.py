
import numpy as np
import pandas as pd

FEATURES = ["blood_type", "lag_1", "lag_7", "rolling_7", "day_of_week", "month"]

def make_prediction_features(blood_type, prediction_date, history):
    target_date = pd.Timestamp(prediction_date).normalize()
    frame = pd.DataFrame(history)
    if set(frame.columns) != {"date", "units_requested"} or len(frame) != 7:
        raise ValueError("Provide exactly seven date/units_requested records for this blood type.")
    frame["date"] = pd.to_datetime(frame["date"], errors="raise").dt.normalize()
    frame = frame.sort_values("date").reset_index(drop=True)
    expected = pd.date_range(target_date - pd.Timedelta(days=7), periods=7, freq="D")
    if not pd.DatetimeIndex(frame["date"]).equals(expected):
        raise ValueError("History must contain each of the seven calendar days immediately before prediction_date.")
    units = pd.to_numeric(frame["units_requested"], errors="raise").to_numpy(dtype=float)
    if not np.isfinite(units).all() or (units < 0).any() or not np.equal(units, np.floor(units)).all():
        raise ValueError("Historical units must be finite nonnegative integer counts.")
    row = {"blood_type": blood_type, "lag_1": float(units[-1]), "lag_7": float(units[0]),
           "rolling_7": float(units.mean()), "day_of_week": target_date.dayofweek,
           "month": target_date.month}
    return pd.DataFrame([row], columns=FEATURES)
