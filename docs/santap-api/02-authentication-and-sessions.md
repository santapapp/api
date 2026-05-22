# Autentikasi dan Session

[Indeks Santap API](../santap-api.md)

---

## 7. Autentikasi dan Session

### 7.1 Auth Administrator

Administrator login melalui Filament Admin Panel.

```txt
/admin
```

Ketentuan:

- Hanya user dengan role global `administrator` yang boleh masuk.
- Gunakan policy/gate untuk membatasi akses panel.
- Semua aksi penting di panel wajib masuk audit log.

### 7.2 Auth Flutter User

Flutter menggunakan Laravel Sanctum token.

Flow:

```txt
POST /api/v1/auth/login
→ validasi email/password
→ generate Sanctum token
→ return user + list organisasi + role per organisasi
```

Token disimpan aman di device Flutter.

Endpoint umum:

```txt
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/me
GET  /api/v1/me/organizations
POST /api/v1/context/switch-organization
```

### 7.3 Organization Context

Setelah login, user memilih organisasi aktif.

Client dapat mengirim organisasi aktif melalui header:

```http
X-Organization-Id: {organization_id}
```

Middleware Laravel akan:

1. Membaca `X-Organization-Id`.
2. Mengecek user adalah member aktif organisasi tersebut.
3. Mengecek role/permission user pada organisasi tersebut.
4. Menyimpan organization context ke request.
5. Query data berdasarkan organisasi aktif.

### 7.4 Customer Guest Session

Customer tidak login.

Flow:

```txt
Customer scan QR meja
→ buka customer web
→ customer web call API start session
→ Laravel membuat/mengambil customer_session aktif
→ session token dikirim ke client
→ customer bisa order selama session aktif
```

Session dapat disimpan dengan:

- HTTP-only cookie jika customer web satu domain dengan API/proxy.
- LocalStorage jika perlu sederhana untuk MVP, tetapi tetap perlu token random yang aman.

Endpoint umum:

```txt
POST /api/v1/customer/sessions/start
GET  /api/v1/customer/sessions/current
POST /api/v1/customer/orders
GET  /api/v1/customer/orders
GET  /api/v1/customer/open-bill
POST /api/v1/customer/call-cashier
```

Ketentuan:

- Customer session memiliki `session_token` random.
- Customer hanya bisa melihat data milik session tersebut.
- Customer tidak boleh mengirim `organization_id` sembarangan tanpa validasi QR/table.
- Saat bill ditutup, session menjadi `closed`.
- Token customer tidak lagi berlaku setelah session closed/expired.

---

---

[Indeks Santap API](../santap-api.md)
