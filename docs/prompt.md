Saya ingin kamu mempelajari dan mengimplementasikan REST API Reports pada project Laravel:

`C:\laragon\www\api-santap`

Requirement laporan awal tersedia dalam file `REPORTS_API_DRAFT.md`.

File tersebut hanya menjadi acuan kebutuhan bisnis. Jangan menganggap nama tabel, nama kolom, enum, relasi, status, atau response di dalamnya sudah sesuai dengan codebase.

## Tujuan

Membuat REST API laporan yang:

* sesuai dengan arsitektur aktual `api-santap`;
* aman terhadap kebocoran data antarorganisasi;
* konsisten dengan lifecycle order, order item, open bill, dan payment Santap;
* menggunakan PostgreSQL-compatible query;
* memiliki hasil agregasi yang akurat;
* mudah digunakan oleh dashboard atau aplikasi mobile;
* terdokumentasi melalui Scramble;
* dilengkapi automated test.

## Tahap pertama: audit codebase

Sebelum melakukan perubahan, pelajari terlebih dahulu:

* model `Order`;
* model `OrderItem`;
* model produk/menu dan kategori;
* model `User`;
* enum order type;
* enum order status;
* enum order item status;
* enum payment status;
* enum bill status;
* metode pembayaran yang tersedia;
* field snapshot harga pada order item;
* field subtotal, discount, tax, service charge, total, dan grand total;
* relasi pembuat order atau kasir;
* flow table order;
* flow cashier order;
* flow open bill dan repeat order;
* flow QRIS Sekeco;
* middleware authentication;
* middleware multitenancy;
* organization scope;
* role, permission, policy, dan gate;
* struktur response API yang sudah digunakan;
* pola Form Request, Resource, Service, Query Object, dan Controller;
* konfigurasi timezone;
* route API `/v1`;
* konfigurasi dokumentasi Scramble;
* migration dan index yang sudah tersedia.

Cari nama kolom dan relasi yang benar dari codebase. Jangan membuat field baru hanya agar sama dengan draft tanpa memastikan bahwa field tersebut memang diperlukan.

Setelah audit, buat implementation plan singkat yang menjelaskan mapping antara requirement laporan dengan schema aktual. Setelah itu langsung lanjutkan implementasi.

## Ketentuan umum

Buat route di bawah prefix:

`/v1/reports`

Semua endpoint wajib:

* menggunakan authentication existing, kemungkinan Laravel Sanctum;
* menggunakan organisasi milik user yang login;
* tidak menerima `organization_id` dari query parameter sebagai sumber scope;
* memiliki authorization yang sesuai;
* mengikuti format response API existing;
* menerima `start_date` dan `end_date` dalam format `YYYY-MM-DD`;
* memiliki rentang maksimal 365 hari;
* memastikan `start_date <= end_date`;
* mengikuti standar validation error Laravel/API existing;
* mengembalikan semua nilai uang sebagai integer Rupiah;
* menggunakan query agregasi yang efisien;
* menghindari N+1 query;
* kompatibel dengan PostgreSQL;
* menggunakan enum dan constant yang sudah tersedia;
* tidak menggunakan string status secara tersebar jika enum tersedia.

Tentukan role atau permission yang boleh mengakses laporan berdasarkan sistem authorization existing. Laporan finansial tidak boleh otomatis dapat diakses oleh seluruh kasir atau kitchen staff.

## Aturan waktu dan rentang tanggal

Gunakan timezone organisasi apabila organisasi memiliki konfigurasi timezone. Jika belum ada, gunakan timezone aplikasi yang berlaku.

Interpretasikan:

* `start_date` sebagai awal hari pada timezone organisasi;
* `end_date` sebagai akhir hari pada timezone organisasi;
* lalu konversikan ke timezone penyimpanan database jika timestamp disimpan dalam UTC.

Untuk transaksi berhasil, gunakan `paid_at` sebagai referensi waktu.

Jangan menggunakan `created_at` untuk menentukan tanggal pendapatan.

Untuk transaksi batal, gunakan timestamp pembatalan resmi seperti `cancelled_at`. Jika field tersebut belum tersedia, cari sumber canonical lain seperti activity log atau transition log.

Jangan memfilter transaksi batal menggunakan `paid_at`, karena transaksi batal dapat memiliki `paid_at = null`.

Jika tidak terdapat sumber waktu pembatalan yang reliable, jelaskan masalah tersebut dalam implementation plan dan usulkan migration yang paling aman.

## Aturan dasar perhitungan

Pendapatan hanya berasal dari order dengan payment yang benar-benar sudah berhasil atau `paid`, mengikuti enum aktual.

Pastikan:

* order batal tidak masuk pendapatan;
* payment expired atau failed tidak masuk pendapatan;
* order item cancelled, rejected, voided, atau status sejenis tidak masuk penjualan produk;
* item gratis tetap dapat dihitung quantity-nya, tetapi revenue mengikuti nilai snapshot sebenarnya;
* harga menggunakan snapshot transaksi, bukan harga produk saat ini;
* produk yang telah berubah harga tidak mengubah laporan historis;
* open bill dihitung sebagai satu transaksi setelah pembayaran final;
* repeat order pada open bill tidak dihitung sebagai transaksi pembayaran terpisah;
* item tambahan pada open bill tetap masuk agregasi produk;
* addon, variant, modifier, discount, tax, dan service charge mengikuti perhitungan final order yang canonical.

Gunakan method atau accessor perhitungan yang sudah menjadi sumber kebenaran di model/service Santap apabila tersedia.

## Endpoint 1 — Financial Summary

Buat:

`GET /v1/reports/financial/summary`

Parameter:

* `start_date`
* `end_date`
* `group_by`: `daily`, `weekly`, atau `monthly`
* default `group_by`: `daily`

Response harus memuat:

* total revenue;
* total subtotal;
* total discount jika tersedia;
* total tax jika tersedia;
* total service charge jika tersedia;
* total transaksi paid;
* jumlah transaksi berdasarkan order type aktual;
* breakdown berdasarkan payment method aktual;
* jumlah dan nominal transaksi batal;
* breakdown berdasarkan periode.

Jangan memaksakan field `service_charge_total` jika struktur Santap menyimpan nilai tersebut menggunakan nama atau mekanisme berbeda. Mapping-kan dari sumber nilai final yang benar.

`transaction_count_by_type` harus menggunakan nilai enum order type aktual. Jangan berasumsi nilainya pasti:

* `cashier_order`;
* `open_bill`;
* `table_order`.

Untuk payment method:

* gunakan payment method canonical;
* QRIS Sekeco harus masuk kategori QRIS yang benar;
* cash harus masuk kategori cash;
* siapkan fallback seperti `unknown` hanya jika data historis memang tidak memiliki metode pembayaran;
* jangan menghitung payment attempt QRIS yang gagal sebagai transaksi.

Breakdown harus:

* menggunakan PostgreSQL `date_trunc`;
* mengembalikan periode secara urut;
* mengisi periode kosong dengan nilai nol jika pola response existing mendukungnya;
* untuk weekly, tetapkan secara konsisten awal minggu;
* untuk monthly, gunakan representasi tanggal awal bulan atau format periode yang jelas.

Cancelled transaction harus dihitung secara terpisah dari query paid transaction.

## Endpoint 2 — Product Bestsellers

Buat:

`GET /v1/reports/products/bestsellers`

Parameter:

* `start_date`
* `end_date`
* `limit`, default 10 dan maksimal 50.

Hitung produk berdasarkan:

* order paid;
* `paid_at` di dalam rentang;
* order item yang masih valid;
* total quantity terjual;
* total revenue item berdasarkan snapshot transaksi.

Pastikan revenue item tidak dihitung menggunakan harga produk terkini.

Tentukan secara konsisten apakah addon atau modifier:

* menjadi bagian revenue produk induk; atau
* memiliki agregasi sendiri.

Ikuti struktur canonical order item Santap dan dokumentasikan keputusan tersebut.

Apabila produk sudah dihapus tetapi snapshot item masih tersedia, laporan historis tetap harus dapat menampilkan nama produk dari snapshot jika memungkinkan.

Urutkan berdasarkan:

1. `total_qty` descending;
2. `total_revenue` descending;
3. identifier stabil sebagai tie-breaker.

## Endpoint 3 — Products With No Sales

Buat:

`GET /v1/reports/products/no-sales`

Parameter:

* `start_date`
* `end_date`

Tampilkan produk milik organisasi yang tidak memiliki penjualan valid dalam periode tersebut.

Produk yang dianggap tidak terjual adalah produk yang:

* tidak mempunyai order item valid;
* tidak berada di order paid dalam rentang `paid_at`;
* tidak hanya memiliki item cancelled atau voided.

Response memuat:

* product id;
* product name;
* current price;
* last sold date.

`last_sold_date` adalah tanggal penjualan paid terakhir sebelum atau sampai `end_date`, bukan hanya di dalam periode yang sedang dicari.

Tentukan dari codebase apakah produk nonaktif, archived, atau soft-deleted perlu disertakan. Secara default, gunakan produk aktif yang masih menjadi bagian katalog organisasi, kecuali convention existing menentukan hal berbeda.

Hindari query per produk untuk mendapatkan `last_sold_date`.

## Endpoint 4 — Product Sales By Category

Buat:

`GET /v1/reports/products/by-category`

Parameter:

* `start_date`
* `end_date`

Gunakan relasi kategori aktual pada codebase. Jangan mengambil kategori dari metadata kecuali codebase memang menggunakan metadata sebagai sumber canonical.

Response memuat:

* category id jika tersedia;
* category name;
* total quantity;
* total revenue;
* percentage.

Gunakan persentase berdasarkan kontribusi revenue kategori terhadap seluruh revenue produk dalam periode.

Jika total revenue nol, percentage harus `0`, bukan division by zero.

Tentukan penanganan produk tanpa kategori, misalnya bucket `Uncategorized`, hanya jika data aktual memungkinkan produk tanpa kategori.

## Endpoint 5 — Product Trend

Buat:

`GET /v1/reports/products/trend`

Parameter:

* `product_id`;
* `start_date`;
* `end_date`.

Validasi bahwa produk tersebut milik organisasi user yang login.

Response memuat:

* informasi produk;
* trend harian;
* tanggal;
* quantity;
* revenue.

Trend harus menggunakan order paid dan order item valid.

Isi tanggal yang tidak mempunyai penjualan dengan:

* `qty: 0`;
* `revenue: 0`.

Jangan membocorkan keberadaan produk dari organisasi lain. Gunakan response not found atau authorization behavior yang konsisten dengan API existing.

## Endpoint 6 — Operational By Cashier

Buat:

`GET /v1/reports/operational/by-cashier`

Parameter:

* `start_date`
* `end_date`

Pelajari terlebih dahulu siapa yang dianggap sebagai kasir dalam struktur aktual.

Jangan langsung mengasumsikan field relasinya bernama `created_by_id`.

Bedakan:

* staff yang membuat order;
* customer self-order dari QR meja;
* staff yang mengonfirmasi pembayaran;
* staff yang menutup open bill;
* user yang hanya mengubah status dapur.

Gunakan relasi yang secara bisnis benar untuk “performa kasir”.

Jika sistem mempunyai field khusus cashier atau paid/closed by, prioritaskan field tersebut. Jika hanya ada creator, dokumentasikan bahwa laporan ini merupakan performa berdasarkan pembuat order.

Response minimal memuat:

* user id;
* name;
* total paid transactions;
* total revenue;
* nominal cash;
* nominal QRIS.

Order customer self-service yang tidak memiliki cashier tidak boleh secara diam-diam dikaitkan ke user yang salah. Bila diperlukan, masukkan bucket `unassigned` atau keluarkan dari laporan dan dokumentasikan keputusan tersebut.

Jangan menghitung staff dari organisasi lain.

## Endpoint 7 — Operational Peak Hours

Buat:

`GET /v1/reports/operational/peak-hours`

Parameter:

* `start_date`
* `end_date`

Gunakan `paid_at` dan timezone organisasi.

Karena database Santap menggunakan PostgreSQL, jangan menggunakan fungsi MySQL `HOUR()`.

Gunakan pendekatan PostgreSQL seperti:

* `EXTRACT(HOUR FROM ...)`;
* atau expression timezone yang aman.

Response berisi jam `0` sampai `23`, termasuk jam tanpa transaksi dengan nilai nol jika sesuai format existing.

Untuk setiap jam tampilkan:

* hour;
* transactions;
* revenue.

Urutkan berdasarkan jam, bukan berdasarkan jumlah transaksi, agar frontend mudah membuat chart.

## Struktur implementasi

Gunakan struktur yang mengikuti codebase. Preferensi umum:

* controller tetap tipis;
* Form Request untuk validasi filter;
* service atau query class untuk agregasi;
* API Resource bila pola tersebut digunakan;
* enum existing untuk status;
* shared date range object atau helper untuk menghindari duplikasi;
* shared organization scope;
* query builder atau Eloquent aggregation yang tetap mudah diuji.

Jangan menaruh seluruh raw query dalam satu controller besar.

Jangan membuat abstraction berlebihan jika codebase saat ini memiliki pola yang lebih sederhana.

## Index database

Periksa index yang sudah ada sebelum membuat migration baru.

Index kandidat untuk `orders`:

* `organization_id`;
* `payment_status`;
* `paid_at`;
* kombinasi `(organization_id, payment_status, paid_at)`.

Periksa juga kebutuhan index pada:

* `order_items.order_id`;
* `order_items.product_id`;
* status order item;
* relasi category;
* relasi cashier atau creator.

Jangan menambahkan index duplikat.

Pastikan nama index kompatibel dengan PostgreSQL dan tidak melebihi batas identifier.

## Keamanan dan multitenancy

Buat test yang membuktikan bahwa:

* user organisasi A tidak dapat membaca data organisasi B;
* product trend organisasi lain tidak dapat diakses;
* filter query tidak dapat mengubah organization scope;
* role tanpa permission laporan ditolak;
* global scope dan explicit organization condition tidak saling bertentangan.

Jangan hanya mengandalkan parameter dari frontend.

## Automated test

Tambahkan feature test untuk seluruh endpoint.

Minimal uji:

1. authentication required;
2. authorization role/permission;
3. organization isolation;
4. valid date range;
5. invalid date format;
6. `start_date` setelah `end_date`;
7. range lebih dari 365 hari;
8. empty result;
9. paid order masuk revenue;
10. unpaid order tidak masuk revenue;
11. failed atau expired QRIS tidak masuk revenue;
12. cancelled order hanya masuk cancelled summary;
13. cancelled order tanpa `paid_at` tetap dapat dihitung berdasarkan timestamp pembatalan;
14. cancelled order item tidak masuk product sales;
15. open bill hanya dihitung satu transaksi;
16. repeat item open bill tetap masuk product aggregation;
17. harga historis menggunakan snapshot;
18. perbedaan timezone dekat pergantian hari;
19. grouping daily;
20. grouping weekly;
21. grouping monthly;
22. product milik organisasi lain ditolak;
23. tanggal tanpa penjualan menghasilkan zero-filled trend;
24. cashier performance tidak mencampur customer self-order;
25. percentage category tidak division by zero;
26. limit bestsellers maksimal 50.

Gunakan factory dan enum existing.

## Dokumentasi

Tambahkan dokumentasi Scramble untuk semua endpoint, termasuk:

* deskripsi;
* authentication;
* authorization;
* query parameters;
* validation;
* contoh response;
* kemungkinan error;
* definisi setiap nilai revenue;
* timezone yang digunakan;
* aturan transaksi paid;
* aturan cancelled transaction;
* aturan item yang tidak dihitung.

Pastikan endpoint muncul pada dokumentasi API yang sesuai dengan pengguna laporan, misalnya admin/mobile management API berdasarkan struktur docs existing.

## Response dan backward compatibility

Ikuti envelope response aktual API Santap, misalnya `data`, `meta`, atau format lain yang sudah digunakan.

Draft response hanyalah gambaran payload. Jangan membuat format endpoint reports berbeda sendiri dari endpoint API lain.

Bila frontend sudah membutuhkan key tertentu seperti:

* `summary`;
* `breakdown`;
* `products`;
* `categories`;
* `trend`;
* `cashiers`;
* `hours`;

pertahankan key tersebut di dalam envelope API yang konsisten.

## Hal yang tidak boleh dilakukan

* Jangan menggunakan `created_at` sebagai tanggal pendapatan.
* Jangan menggunakan `paid_at` untuk transaksi yang tidak pernah dibayar.
* Jangan menggunakan sintaks khusus MySQL.
* Jangan menghitung semua order item tanpa memeriksa statusnya.
* Jangan mengambil harga produk terkini untuk laporan historis.
* Jangan mempercayai `organization_id` dari request.
* Jangan mengubah lifecycle order atau payment hanya untuk kebutuhan laporan.
* Jangan membuat enum order type atau payment method baru tanpa alasan dari schema.
* Jangan menghitung QRIS attempt yang gagal.
* Jangan menganggap open bill repeat order sebagai transaksi finansial terpisah.
* Jangan membuat migration index sebelum memeriksa index existing.

## Hasil akhir yang diharapkan

Setelah selesai, berikan:

1. ringkasan hasil audit schema aktual;
2. mapping requirement ke model dan field aktual;
3. daftar file yang dibuat atau diubah;
4. endpoint final;
5. aturan perhitungan setiap laporan;
6. perubahan migration dan index;
7. authorization yang diterapkan;
8. dokumentasi Scramble;
9. hasil automated test;
10. hasil formatter dan static analysis yang tersedia;
11. catatan asumsi atau keterbatasan data historis.

Implementasi harus mengutamakan akurasi data finansial, keamanan multitenancy, konsistensi status, dan kompatibilitas dengan lifecycle order Santap.
