import os
import json
import requests
import sys
import time
import random
import mysql.connector
from collections import defaultdict
from dotenv import load_dotenv
from flask import Flask, jsonify, request
from apscheduler.schedulers.background import BackgroundScheduler
import threading

# Load environment variables from .env file
load_dotenv()

app = Flask(__name__)

def get_db_connection():
    try:
        connection = mysql.connector.connect(
            host=os.getenv("DB_HOST", "localhost"),
            user=os.getenv("DB_USER", "root"),
            password=os.getenv("DB_PASSWORD", ""),
            database=os.getenv("DB_NAME", "test_db"),
            port=int(os.getenv("DB_PORT", 3306)),
        )
        return connection
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        return None

def normalize_phone_number(value):
    raw_number = str(value or "").strip()
    normalized_number = "".join(ch for ch in raw_number if ch.isdigit())
    if not normalized_number:
        return None
    if normalized_number.lstrip("0") == "":
        return None
    if normalized_number.startswith("0"):
        normalized_number = "62" + normalized_number.lstrip("0")
    if len(normalized_number) > 13:
        return None
    return normalized_number

def fetch_receivers(limit=10, offset=0):
    connection = get_db_connection()
    if not connection:
        return []
        
    cursor = connection.cursor(dictionary=True)

    query = (
        f"select b.tanggal_periksa, pt.nm_pasien , pl.nm_poli , b.no_rkm_medis , pt.no_tlp "
        f"from booking_registrasi as b "
        f"INNER JOIN pasien as pt on b.no_rkm_medis = pt.no_rkm_medis "
        f"INNER JOIN poliklinik as pl ON b.kd_poli = pl.kd_poli "
        f"where b.tanggal_periksa = DATE_ADD(CURDATE(), INTERVAL 1 DAY) "
        f"LIMIT {limit} OFFSET {offset}"
    )

    try:
        cursor.execute(query)
        receivers = cursor.fetchall()

        months = {
            1: "Januari", 2: "Februari", 3: "Maret", 4: "April", 5: "Mei", 6: "Juni",
            7: "Juli", 8: "Agustus", 9: "September", 10: "Oktober", 11: "November", 12: "Desember"
        }

        for receiver in receivers:
            if "tanggal_periksa" in receiver and receiver["tanggal_periksa"]:
                date_obj = receiver["tanggal_periksa"]
                formatted_date = f"{date_obj.day:02d} {months[date_obj.month]} {date_obj.year}"
                
                receiver["tanggal_periksa"] = formatted_date
                receiver["tanggal"] = formatted_date
            
            if "nm_pasien" in receiver:
                receiver["nama"] = receiver["nm_pasien"]
            if "nm_poli" in receiver:
                receiver["poli"] = receiver["nm_poli"]
            if "no_tlp" in receiver:
                normalized_number = normalize_phone_number(receiver["no_tlp"])
                receiver["no_tlp"] = normalized_number
                receiver["number"] = normalized_number
            if "no_rkm_medis" in receiver:
                receiver["no_rkm_medis"] = receiver["no_rkm_medis"]

        return receivers
    except mysql.connector.Error as err:
        print(f"Error fetching data: {err}")
        return []
    finally:
        cursor.close()
        connection.close()

def fetch_receive_count():
    connection = get_db_connection()
    if not connection:
        return 0
        
    cursor = connection.cursor(dictionary=True)

    query = "select count(b.no_rkm_medis) as count from booking_registrasi as b where b.tanggal_periksa = DATE_ADD(CURDATE(), INTERVAL 1 DAY)"

    try:
        cursor.execute(query)
        row = cursor.fetchone()
        if not row:
            return 0
        return int(row.get("count", 0) or 0)
    except mysql.connector.Error as err:
        print(f"Error fetching data: {err}")
        return 0
    finally:
        cursor.close()
        connection.close()

def load_json_file(filename):
    # Try reading from /data/ first for persistent Papuyu PaaS storage, fallback to app root
    paths = [f"/data/{filename}", filename]
    for path in paths:
        try:
            with open(path, 'r') as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            continue
    print(f"Error: {filename} file not found or invalid.")
    return None

def send_data_job():
    url = os.getenv("TARGET_URL")
    api_key = os.getenv("API_KEY")

    if not url or not api_key:
        print("Error: TARGET_URL or API_KEY environment variable is not set.")
        return

    messages = load_json_file('messages.json')
    if not messages:
        return

    senders = load_json_file('sender.json')
    if not isinstance(senders, list) or not senders:
        print("Error: sender.json must be a non-empty JSON array.")
        return

    print("Fetching data count from database...")
    count = fetch_receive_count()
    print(f"Total count to process: {count}")

    if count == 0:
        print("No receivers found.")
        return

    limit = 10
    headers = {"Content-Type": "application/json", "x-api-key": api_key}

    for offset in range(0, count, limit):
        if offset > 0:
            print("Waiting 10 seconds before next batch...")
            time.sleep(10)

        print(f"Fetching receivers (limit={limit}, offset={offset})...")
        receivers = fetch_receivers(limit=limit, offset=offset)
        print(f"Received {len(receivers)} receivers.")

        if not receivers:
            print(f"Warning: No receivers found at offset {offset}.")
            continue

        print(f"Sending batch (offset {offset}) to {url}...")
        sent_numbers = set()
        for receiver in receivers:
            number = normalize_phone_number(receiver.get("number") or receiver.get("no_tlp"))
            if not number:
                print(f"Warning: Receiver has invalid/no_tlp, skipping: {receiver}")
                continue
            if number in sent_numbers:
                print(f"Skip duplicate number={number}")
                continue
            sent_numbers.add(number)

            message_template = random.choice(messages)
            message = message_template.format_map(defaultdict(str, receiver))

            data = {
                "sender": random.choice(senders),
                "number": number,
                "message": message,
            }
            print(f"Prepared data: {data}")
            try:
                response = requests.post(url, json=data, headers=headers)
                response.raise_for_status()
                print(f"Success! number={number} status={response.status_code}")
            except requests.exceptions.RequestException as e:
                print(f"Error sending request for number={number}: {e}")
                continue
            time.sleep(10)
    print("Job Send Data completed.")

# API endpoints for health check and manual trigger
@app.route("/", methods=["GET"])
def health_check():
    return jsonify({
        "status": "ok", 
        "message": "WaKontrol Microservice is running securely on Papuyu PaaS."
    })

@app.route("/trigger", methods=["GET", "POST"])
def manual_trigger():
    # Run in background to avoid blocking API response
    thread = threading.Thread(target=send_data_job)
    thread.start()
    return jsonify({"status": "ok", "message": "Broadcast WhatsApp manually triggered."})

@app.route("/debug", methods=["GET", "POST"])
def debug_send():
    url = os.getenv("TARGET_URL")
    api_key = os.getenv("API_KEY")

    if not url or not api_key:
        return jsonify({"status": "error", "message": "TARGET_URL or API_KEY not set."}), 500

    messages = load_json_file('messages.json')
    if not messages:
        return jsonify({"status": "error", "message": "Failed to load messages.json"}), 500

    senders = load_json_file('sender.json')
    if not isinstance(senders, list) or not senders:
        return jsonify({"status": "error", "message": "Failed to load sender.json"}), 500

    # Dummy Data for Debugging
    number = request.args.get("number") or "081234567890"
    dummy_receiver = {
        "nama": "Pasien Dummy",
        "poli": "Poli Umum (Debug)",
        "tanggal": "01 Januari 2026",
        "number": number
    }

    message_template = random.choice(messages)
    message = message_template.format_map(defaultdict(str, dummy_receiver))

    sender = request.args.get("sender") or random.choice(senders)
    data = {
        "sender": sender,
        "number": number,
        "message": message,
    }

    headers = {"Content-Type": "application/json", "x-api-key": api_key}
    print(f"[DEBUG] Prepared data: {data}")

    try:
        response = requests.post(url, json=data, headers=headers)
        response.raise_for_status()
        return jsonify({
            "status": "ok", 
            "message": "Debug message sent successfully.",
            "data": data,
            "response_status": response.status_code
        })
    except requests.exceptions.RequestException as e:
        return jsonify({
            "status": "error", 
            "message": f"Error sending request: {str(e)}",
            "data": data
        }), 500

if __name__ == "__main__":
    # Setup Background Scheduler for Auto Run
    scheduler = BackgroundScheduler()
    
    # Read cron schedule from .env or use defaults (7:00 AM)
    cron_hour = os.getenv("CRON_HOUR", "7")
    cron_minute = os.getenv("CRON_MINUTE", "0")
    
    scheduler.add_job(func=send_data_job, trigger="cron", hour=cron_hour, minute=cron_minute)
    scheduler.start()
    print(f"Daily Background Job scheduled (Cron - Hour: {cron_hour}, Minute: {cron_minute}).")

    port = int(os.getenv("PORT", 8080))
    # Exposing 0.0.0.0 is needed in container environments like Papuyu PaaS
    app.run(host="0.0.0.0", port=port, use_reloader=False)
