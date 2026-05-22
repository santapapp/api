# Alur Sistem Utama

[Indeks Santap API](../santap-api.md)

---

## 8. Alur Sistem Utama

## 8.1 Alur Onboarding Organisasi

```txt
Administrator/Owner membuat organisasi
→ organisasi dibuat di database
→ owner menjadi member organisasi
→ role owner diberikan untuk organization tersebut
→ owner dapat login Flutter
→ owner mengatur menu, meja, QR, dan user
```

Data yang dibuat:

```txt
organizations
organization_members
role assignment owner
```

## 8.2 Alur Invite User ke Organisasi

```txt
Owner memilih invite user
→ input email + role
→ Laravel membuat invitation token
→ Laravel mengirim email invitation
→ user membuka signed URL
→ jika belum punya akun, user register
→ jika sudah punya akun, user accept invite
→ user masuk ke organization_members
→ role diberikan sesuai invite
```

Status invite:

```txt
pending
accepted
expired
cancelled
```

Ketentuan:

- Invite hanya boleh dibuat oleh owner/admin yang punya permission.
- Invite harus memiliki expiry.
- Invite accept harus idempotent.
- Email yang diundang harus sesuai dengan email user yang accept.

## 8.3 Alur Setup Menu

```txt
Owner login
→ pilih organisasi aktif
→ buat kategori menu
→ buat menu item
→ upload foto menu
→ atur harga, status tersedia, dan varian bila ada
```

Status menu:

```txt
active
inactive
out_of_stock
```

## 8.4 Alur Setup Meja dan QR

```txt
Owner membuat data meja
→ Laravel generate QR token / QR URL
→ QR ditempel di meja
→ customer scan QR
→ customer web tahu organization + table
```

Format QR URL contoh:

```txt
https://santap.id/o/{organization_slug}/t/{table_code}?qr={qr_token}
```

Ketentuan:

- QR token tidak boleh mudah ditebak.
- QR bisa diregenerate jika bocor.
- QR mengarah ke customer web, bukan langsung ke API.

## 8.5 Alur Customer Scan QR dan Open Bill

```txt
Customer scan QR meja
→ Customer web memvalidasi table QR
→ API cek apakah meja punya open bill aktif
→ Jika belum ada open bill, buat customer_session dan open_bill
→ Jika sudah ada open bill, customer bisa join session bill sesuai aturan restoran
→ Customer melihat menu
→ Customer membuat order
```

Ada dua opsi kebijakan join meja:

### Opsi A — Satu Session per Meja

Semua customer di meja yang sama masuk ke open bill yang sama.

Cocok untuk restoran dine-in umum.

### Opsi B — Session per Device

Setiap device punya session sendiri, tetapi tetap masuk open bill meja yang sama.

Cocok jika ingin tracking siapa pesan apa.

Rekomendasi MVP:

```txt
Gunakan satu open_bill per meja, tetapi boleh banyak customer_session di dalam open bill.
```

## 8.6 Alur Customer Order

```txt
Customer pilih menu
→ customer submit order
→ API validasi session aktif
→ API validasi open bill aktif
→ API validasi menu tersedia
→ API membuat order dan order_items
→ Reverb broadcast order masuk ke cashier/kitchen
→ Kitchen melihat order baru
```

Status order:

```txt
pending
accepted
cooking
ready
served
cancelled
```

Status order item bisa dibuat lebih detail:

```txt
pending
cooking
ready
served
cancelled
```

## 8.7 Alur Kitchen

```txt
Kitchen login Flutter
→ pilih organisasi aktif
→ buka kitchen screen
→ menerima order realtime
→ ubah status item/order ke cooking
→ ubah ke ready
→ cashier/owner/customer menerima update status
```

Ketentuan:

- Kitchen hanya bisa melihat order organisasi aktif.
- Kitchen tidak boleh mengubah pembayaran.
- Kitchen tidak boleh melihat laporan finansial.

## 8.8 Alur Cashier dan Pembayaran

```txt
Cashier login Flutter
→ melihat open bill aktif
→ customer minta bayar / cashier pilih meja
→ cashier review order
→ cashier input metode pembayaran
→ cashier close bill
→ payment tercatat
→ open bill status closed
→ customer_session status closed
→ meja kembali available
```

Status bill:

```txt
open
closed
cancelled
```

Status payment:

```txt
pending
paid
failed
refunded
void
```

Metode pembayaran awal:

```txt
cash
qris
bank_transfer
card
other
```

## 8.9 Alur Close Bill dan Session Berakhir

```txt
Bill ditutup oleh cashier
→ semua customer_sessions terkait bill diubah ke closed
→ session_token customer tidak valid lagi
→ open_bill menjadi closed
→ table status menjadi available
→ order tetap tersimpan sebagai history
```

Ketentuan penting:

- Setelah bill closed, customer tidak bisa menambah order.
- Customer tidak bisa melihat active open bill lagi.
- History order tetap tersimpan untuk restoran.
- Jika ingin customer melihat receipt, gunakan receipt token terpisah dengan expiry.

## 8.10 Alur Cancel/Void

```txt
Cashier/Owner memilih cancel order/item
→ sistem cek permission
→ wajib isi alasan
→ status berubah cancelled/void
→ activity log tercatat
→ realtime update dikirim
```

Ketentuan:

- Cancel setelah paid sebaiknya butuh permission khusus.
- Void/refund harus masuk audit log.
- Kitchen tidak boleh void pembayaran.

---

---

[Indeks Santap API](../santap-api.md)
