# Phase 4: Master Data Restoran

[Roadmap](../../roadmap.md)

---

## Tujuan

Membangun data operasional dasar restoran: profil organisasi, kategori menu, menu, meja, QR code, dan media upload.

## Referensi

- [Alur Sistem Utama](../../santap-api/03-core-workflows.md)
- [Skema Database Awal](../../santap-api/04-database-schema.md)
- [API Design](../../santap-api/05-api-design.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Model dan migration:
  - `MenuCategory`
  - `Menu`
  - `DiningTable`
  - `TableQrCode`
- Enum:
  - `MenuStatus`
  - `TableStatus`
  - QR status bila diperlukan.
- API owner/cashier optional:
  - `/menu-categories`
  - `/menus`
  - `/dining-tables`
  - `/dining-tables/{table}/regenerate-qr`
- Upload logo/menu image.
- Generate QR token dan QR URL.
- Organization settings dasar.

## Urutan Pengerjaan

1. Buat migration kategori menu, menu, dining tables, dan table QR codes.
2. Tambahkan relationship antar model.
3. Terapkan `BelongsToOrganization` di semua model bisnis.
4. Buat FormRequest untuk create/update kategori, menu, dan meja.
5. Buat API Resource untuk response Flutter.
6. Implement CRUD menu category.
7. Implement CRUD menu.
8. Implement upload image menu dan logo organisasi.
9. Implement CRUD dining table.
10. Implement generate dan regenerate QR token.
11. Tambahkan policy permission:
    - `menu.*`
    - `category.*`
    - `table.*`
12. Tambahkan feature test untuk scoping organisasi dan permission.

## Deliverables

- Owner bisa mengelola kategori menu.
- Owner bisa mengelola menu dan status ketersediaan.
- Owner bisa mengelola meja.
- Sistem bisa membuat QR URL per meja.
- Customer web nanti bisa memvalidasi `organization_slug`, `table_code`, dan `qr_token`.

## Acceptance Criteria

- Data menu/meja organisasi A tidak bisa dibaca organisasi B.
- Menu menyimpan harga dengan tipe numeric/decimal yang aman.
- QR token random dan bisa diregenerate.
- QR lama bisa direvoke jika token diganti.
- Delete data yang punya transaksi nanti harus dibatasi atau memakai inactive status.

## Out of Scope

- Customer session.
- Open bill.
- Order.
- Variant dan addon menu kompleks.

---

[Roadmap](../../roadmap.md)
