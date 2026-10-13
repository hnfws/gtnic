import os
from pathlib import Path
import requests
from dotenv import load_dotenv

load_dotenv(dotenv_path=Path(__file__).with_name(".env"))

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000/api/scraped-events")
API_TOKEN = os.getenv("SCRAPER_API_TOKEN")

payload = {
    "title": "Bazaar Kuliner Depok Fest 2026",
    "description": "Calling all F&B Tenants! Open booth untuk kategori makanan dan minuman. Hubungi via WA.",
    "location_name": "Margo City Depok",
    "latitude": -6.3732,
    "longitude": 106.8346,
    "price": 1500000,
    "instagram_post_url": "https://www.instagram.com/p/C1234567890/",
    "registration_link": "https://forms.gle/samplelink"
}

headers = {
    "Authorization": f"Bearer {API_TOKEN}",
    "Content-Type": "application/json"
}

if not API_TOKEN:
    raise RuntimeError("SCRAPER_API_TOKEN belum diatur di scraper/.env.")

response = requests.post(LARAVEL_API_URL, json=payload, headers=headers)
print("Response Status:", response.status_code)
print("Response Body:", response.text)