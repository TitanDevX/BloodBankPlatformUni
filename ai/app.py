
from contextlib import asynccontextmanager
from datetime import date
from pathlib import Path
import json
import joblib
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, ConfigDict, Field
from feature_builder import make_prediction_features

BASE = Path(__file__).resolve().parent

@asynccontextmanager
async def lifespan(app: FastAPI):
    app.state.model = joblib.load(BASE / "blood_demand_model.joblib")
    app.state.metadata = json.loads((BASE / "metadata.json").read_text(encoding="utf-8"))
    yield
    del app.state.model

app = FastAPI(title="Daily Blood Demand Prototype", version="1.0.0", lifespan=lifespan)

class DailyDemand(BaseModel):
    model_config = ConfigDict(extra="forbid", allow_inf_nan=False)
    date: date
    units_requested: int = Field(ge=0, strict=True)

class PredictionRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")
    blood_type: str
    prediction_date: date
    history: list[DailyDemand] = Field(min_length=7, max_length=7)

class PredictionResponse(BaseModel):
    blood_type: str
    prediction_date: date
    predicted_units: float
    rounded_units: int
    model_name: str
    scope: str

@app.get("/health")
def health():
    return {"status": "ok", "model": app.state.metadata["selected_model"]}

@app.post("/predict", response_model=PredictionResponse)
def predict(request: PredictionRequest):
    metadata = app.state.metadata
    if request.blood_type not in metadata["blood_types"]:
        raise HTTPException(status_code=422, detail="Blood type was not represented in training.")
    try:
        row = make_prediction_features(request.blood_type, request.prediction_date,
                                       [item.model_dump() for item in request.history])
    except (ValueError, TypeError, OverflowError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    value = max(0.0, float(app.state.model.predict(row)[0]))
    return {"blood_type": request.blood_type, "prediction_date": request.prediction_date,
            "predicted_units": value, "rounded_units": int(value + 0.5),
            "model_name": metadata["selected_model"], "scope": metadata["scope"]}
