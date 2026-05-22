# Phase 11: Pasca MVP dan Ekspansi Produk

[Roadmap](../../roadmap.md)

---

## Tujuan

Menampung pengembangan setelah MVP stabil, tanpa memasukkan terlalu banyak kompleksitas ke fase awal.

## Referensi

- [Rencana Implementasi dan Batas MVP](../../santap-api/10-roadmap-and-mvp.md)
- [Keputusan Final dan Catatan Implementasi](../../santap-api/11-decisions-and-notes.md)

## Kandidat Fitur

- Subscription billing otomatis.
- Plan dan limit organisasi.
- Inventory/stok bahan baku.
- Multi-branch lebih kompleks.
- Printer thermal production integration.
- Advanced discount engine.
- Loyalty/customer account login.
- Payment gateway penuh.
- Marketplace integration.
- Database per tenant jika benar-benar diperlukan.
- Export report Excel/PDF.
- Push notification mobile.
- Offline-first/sync untuk Flutter.

## Prinsip Prioritas

1. Jangan ubah fondasi multi-organisasi tanpa migration plan yang matang.
2. Jangan membuat payment gateway penuh sebelum alur close bill manual stabil.
3. Jangan membuat inventory detail sebelum data menu/order/payment produksi cukup terbukti.
4. Jangan membuat multi-branch kompleks sebelum satu organization workflow matang.
5. Jangan membuat loyalty/customer account sebelum guest customer lifecycle stabil.

## Urutan Awal yang Disarankan

1. Subscription plan sederhana dan manual admin.
2. Printer thermal untuk receipt.
3. Export report.
4. Discount sederhana.
5. Inventory ringan per menu.
6. Payment gateway penuh.
7. Branching organisasi.
8. Loyalty/customer account.

## Acceptance Criteria

- Setiap fitur lanjutan memiliki migration plan.
- Setiap fitur lanjutan punya feature flag atau cara disable.
- Tidak mengganggu endpoint MVP yang sudah dipakai client.
- Dokumentasi dan test ditambahkan bersamaan dengan fitur.

---

[Roadmap](../../roadmap.md)
