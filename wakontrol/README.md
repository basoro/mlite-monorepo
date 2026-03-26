# WaKontrol Microservice

WaKontrol adalah sebuah microservice berbasis Python (Flask & APScheduler) yang dirancang khusus untuk berjalan di lingkungan **Papuyu mini PaaS**. Fungsi utamanya adalah secara otomatis mengirimkan pesan pengingat jadwal kontrol pemeriksaan kepada pasien via WhatsApp dengan membaca data dari database MySQL.

## Fitur Utama
1. **Otomasi Terjadwal (Cron)**  
   Dilengkapi dengan penjadwalan *background job* bawaan (APScheduler). Tidak perlu install cron tambahan di level OS Docker.
2. **Template Pesan Acak**  
   Pesan dapat dikonfigurasi melalui `messages.json` agar bervariasi setiap kali dikirim.
3. **Multi Sender**  
   Pengiriman pesan didistribusikan ke daftar nomor WhatsApp di `sender.json`.
4. **Endpoint API (Flask)**  
   - `GET /` : Endpoint *Health Check* yang dibutuhkan oleh sistem Traefik di Papuyu PaaS.
   - `GET|POST /trigger` : Endpoint untuk memicu *broadcast* pesan secara manual tanpa menunggu jadwal otomatis.
   - `GET|POST /debug?number=08123xxx&sender=device_1` : Endpoint khusus untuk testing/send dummy data ke WhatsApp gateway tanpa menyentuh database pasien aktual. (Parameter `number` dan `sender` bersifat *optional*).

## Persiapan & Konfigurasi

Project ini menggunakan variabel lingkungan (*environment variables*). Ganti nama file `.env.example` menjadi `.env` dan sesuaikan parameter berikut:

```env
# Konfigurasi Database MySQL
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=test_db
DB_PORT=3306

# Konfigurasi Endpoint WhatsApp API (Gateway)
TARGET_URL=https://wa.api.endpoint/send
API_KEY=your_secret_api_key_here

# Konfigurasi Port Aplikasi di dalam Container
PORT=8080

# Pengaturan Jadwal Eksekusi Otomatis (Cron Job)
# Default 7:00 => artinya berjalan setiap jam 7 pagi
CRON_HOUR=7
CRON_MINUTE=0
```

## Menyesuaikan Pesan dan Sender

File konfigurasi ini diletakkan pada folder `/data/` di dalam container jika Anda melakukan konfigurasi persisten (*persistent volume*) di Papuyu PaaS. Jika tidak ditemukan di `/data/`, aplikasi akan membaca ke *root folder* langsung.
- **`messages.json`**: Berisi *array* template pesan yang memuat variabel seperti `{nama}`, `{tanggal}`, dan `{poli}`.
- **`sender.json`**: Berisi *array* daftar nomor WhatsApp gateway yang akan digunakan secara acak setiap kali pesan dikirim.

## Menjalankan dengan Docker

Secara ideal aplikasi ini berjalan dengan Docker untuk memudahkan *deployment* ke Papuyu PaaS.

1. **Build Image:**
   ```bash
   docker build -t wakontrol-service .
   ```

2. **Run Container:**
   Menjalankan container dan me-mapping volume `/data` apabila diperlukan persitensi JSON:
   ```bash
   docker run -d \
     --name wakontrol \
     --env-file .env \
     -p 8080:8080 \
     -v $(pwd)/messages.json:/data/messages.json \
     -v $(pwd)/sender.json:/data/sender.json \
     wakontrol-service
   ```

Atau jika berjalan langsung di Papuyu PaaS, platform tersebut akan secara dinamis memberikan dan routing port sesuai environment variabel `$PORT` Anda. 
