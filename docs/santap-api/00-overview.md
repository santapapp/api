# Overview Produk dan Konteks

[Indeks Santap API](../santap-api.md)

---

> Dokumen ini menjelaskan ketentuan sistem, alur bisnis, skema data, arsitektur Laravel, struktur API, dan aturan multi-organisasi untuk aplikasi **Santap**. Fokus dokumen ini adalah backend Laravel sebagai **API utama** dan **panel administrator Santap**.

---

## 1. Ringkasan Produk

**Santap** adalah sistem POS dan order management untuk restoran/kedai/cafe yang terdiri dari:

1. **Laravel API**  
   Backend utama untuk aplikasi Flutter, customer web, realtime order, autentikasi, role, organisasi, transaksi, dan laporan.

2. **Filament Admin Panel**  
   Panel internal untuk administrator Santap dalam mengelola keseluruhan aplikasi, organisasi/restoran, user, subscription/plan, audit, dan konfigurasi platform.

3. **Flutter App**  
   Aplikasi operasional restoran untuk owner, cashier, dan kitchen.

4. **Customer Web**  
   Halaman web tanpa login untuk customer. Customer dapat scan QR, membuat order, menyimpan session open bill sementara, dan melihat history order sementara selama bill masih aktif.

Database utama tetap menggunakan **Neon PostgreSQL**.

---

## 2. Prinsip Arsitektur

Santap menggunakan pendekatan:

```txt
Single Laravel Application
├── REST API untuk Flutter dan Customer Web
├── Filament Admin Panel untuk administrator Santap
├── Neon PostgreSQL sebagai database utama
├── Single database multi-organization
├── organization_id sebagai batas data tenant
├── Laravel Sanctum untuk token API
├── Laravel Reverb untuk realtime event
└── Redis Queue + Horizon untuk background jobs
```

Keputusan utama:

- Satu database untuk semua organisasi/restoran.
- Data bisnis restoran wajib memiliki `organization_id`.
- User dapat bergabung ke beberapa organisasi.
- Organisasi dapat memiliki banyak user.
- Role user bersifat per organisasi, bukan global.
- Customer tidak masuk ke tabel `users`.
- Customer web menggunakan guest session/token sementara.
- Saat open bill ditutup, session customer berakhir.
- Data order tetap tersimpan sebagai history transaksi restoran.

---

## 3. Tech Stack Laravel

### 3.1 Core Stack

```txt
Laravel latest stable
PHP 8.3+
Neon PostgreSQL
Laravel Sanctum
Filament
Spatie Laravel Permission
Laravel Reverb
Laravel Queue
Laravel Horizon
Laravel Notifications
Laravel Pulse
Spatie Laravel Activitylog
Laravel Storage / Spatie Media Library
```

### 3.2 Fungsi Tiap Stack

| Area | Package / Teknologi | Fungsi |
|---|---|---|
| Framework | Laravel | Backend API dan sistem utama |
| Database | Neon PostgreSQL | Database cloud PostgreSQL |
| API Auth | Laravel Sanctum | Token auth untuk Flutter dan customer/session API tertentu |
| Admin Panel | Filament | Panel administrator Santap |
| Role | Spatie Laravel Permission | Role dan permission, terutama dengan konsep teams/organization scope |
| Realtime | Laravel Reverb | Order masuk, status kitchen, panggil kasir, update meja |
| Queue | Laravel Queue | Background job |
| Queue Monitor | Horizon | Monitoring queue Redis |
| Notification | Laravel Notifications | Email invite, database notification, broadcast notification |
| Monitoring | Laravel Pulse | Monitoring performa Laravel |
| Audit | Spatie Activitylog | Log aktivitas sensitif |
| Media | Storage / Media Library | Logo restoran, foto menu, file pendukung |

---

## 4. Aktor Sistem

### 4.1 Administrator Santap

Administrator adalah user internal Santap yang mengelola platform secara keseluruhan.

Kemampuan administrator:

- Login ke admin panel Santap.
- Melihat semua organisasi/restoran.
- Mengelola status organisasi.
- Mengelola user platform jika diperlukan.
- Melihat statistik global aplikasi.
- Melihat audit log.
- Mengatur plan/subscription jika fitur billing sudah aktif.
- Suspend/activate organisasi.
- Melakukan support/debug data organisasi.

Role administrator bersifat **global**, bukan role organisasi.

```txt
administrator -> mengelola keseluruhan aplikasi Santap
```

### 4.2 User Aplikasi Flutter

User Flutter adalah user operasional restoran.

Role utama:

```txt
owner
cashier
kitchen
```

#### Owner

Owner adalah pemilik atau pengelola utama organisasi/restoran.

Kemampuan owner:

- Mengelola profil organisasi/restoran.
- Mengelola menu dan kategori.
- Mengelola meja dan QR code.
- Mengundang user ke organisasi.
- Mengatur role user dalam organisasinya.
- Melihat laporan penjualan.
- Melihat history order.
- Mengelola konfigurasi restoran.

#### Cashier

Cashier adalah user yang mengelola pembayaran dan transaksi.

Kemampuan cashier:

- Melihat daftar open bill.
- Membuka dan menutup bill.
- Konfirmasi pembayaran.
- Mengubah status pembayaran.
- Melihat order aktif.
- Void/cancel order sesuai permission.
- Mencetak struk jika fitur printer tersedia.

#### Kitchen

Kitchen adalah user dapur.

Kemampuan kitchen:

- Melihat order masuk.
- Mengubah status order item atau order.
- Menandai item sebagai cooking/ready/served.
- Tidak memiliki akses ke laporan finansial.
- Tidak mengatur user/organisasi.

### 4.3 Customer Web Tanpa Login

Customer adalah pengunjung restoran yang mengakses halaman order melalui QR code.

Ketentuan customer:

- Tidak login.
- Tidak masuk ke tabel `users`.
- Menggunakan guest session/token sementara.
- Bisa memiliki session open bill aktif.
- Bisa melihat order/history sementara selama bill/session masih aktif.
- Setelah bill ditutup, session berakhir.
- Data order tetap menjadi history transaksi restoran.

---

---

[Indeks Santap API](../santap-api.md)
