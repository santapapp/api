# Santap API Specification

> Dokumen utama ini sekarang menjadi indeks modular untuk spesifikasi Santap API. Detail lengkapnya dipisah ke folder `docs/santap-api/` agar lebih mudah dibaca, dibagikan, dan dikerjakan per area.

## Cara Pakai

- Mulai dari overview jika ingin memahami konteks produk dan aktor sistem.
- Buka file berdasarkan domain ketika sedang implementasi fitur tertentu.
- Nomor section asli tetap dipertahankan di setiap file supaya referensi lama masih mudah dilacak.

## Daftar Dokumen

| Urutan | File | Isi Utama |
|---:|---|---|
| 00 | [Overview Produk dan Konteks](santap-api/00-overview.md) | Ringkasan produk, prinsip arsitektur, tech stack, dan aktor sistem. |
| 01 | [Multi-Organisasi, Role, dan Permission](santap-api/01-multi-organization-and-permissions.md) | Model tenant, relasi organisasi, role global/organisasi, dan permission matrix. |
| 02 | [Autentikasi dan Session](santap-api/02-authentication-and-sessions.md) | Auth admin, auth Flutter, organization context, dan guest session customer. |
| 03 | [Alur Sistem Utama](santap-api/03-core-workflows.md) | Onboarding, invite user, setup menu/meja, customer order, kitchen, cashier, close bill, dan void. |
| 04 | [Skema Database Awal](santap-api/04-database-schema.md) | Skema konseptual tabel utama, status, constraint, dan catatan snapshot transaksi. |
| 05 | [API Design](santap-api/05-api-design.md) | Base URL, header, endpoint auth, organisasi, menu, meja, customer, order, bill, payment, dan report. |
| 06 | [Middleware dan Data Scoping](santap-api/06-middleware-and-data-scoping.md) | Middleware Laravel, organization context, customer session guard, dan aturan scoping data. |
| 07 | [Realtime, Queue, dan Admin Panel](santap-api/07-realtime-queue-and-admin.md) | Event Reverb, channel realtime, queue/job, Horizon, dan resource Filament admin. |
| 08 | [Struktur Laravel, Enum, Validasi, dan Security](santap-api/08-laravel-implementation-rules.md) | Struktur folder, enum awal, validasi bisnis, dan security rule. |
| 09 | [Neon PostgreSQL dan Instalasi Awal](santap-api/09-neon-and-installation.md) | Environment Neon, direct/pooled connection, package, dan command instalasi. |
| 10 | [Rencana Implementasi dan Batas MVP](santap-api/10-roadmap-and-mvp.md) | Phase implementasi, fitur yang ditunda, dan fokus MVP. |
| 11 | [Keputusan Final dan Catatan Implementasi](santap-api/11-decisions-and-notes.md) | Keputusan teknis final dan urutan aman implementasi. |
| 12 | [Dokumentasi Alur API (Flutter & Web)](santap-api/12-client-api-flows.md) | Panduan langkah demi langkah integrasi API untuk Flutter dan Web Customer. |

## Alur Baca yang Disarankan

1. Baca [Overview Produk dan Konteks](santap-api/00-overview.md).
2. Lanjut ke [Multi-Organisasi, Role, dan Permission](santap-api/01-multi-organization-and-permissions.md).
3. Pahami [Autentikasi dan Session](santap-api/02-authentication-and-sessions.md).
4. Gunakan [Dokumentasi Alur API (Flutter & Web)](santap-api/12-client-api-flows.md) untuk memahami siklus hidup request client.
5. Gunakan [API Design](santap-api/05-api-design.md) dan [Skema Database Awal](santap-api/04-database-schema.md) saat mulai implementasi endpoint/migration.
6. Jadikan [Rencana Implementasi dan Batas MVP](santap-api/10-roadmap-and-mvp.md) sebagai urutan kerja.
7. Gunakan [Roadmap Eksekusi Santap API](roadmap.md) untuk checklist phase-by-phase yang lebih detail.

## Catatan

File ini sengaja dibuat pendek sebagai entry point. Jika ada perubahan besar di salah satu domain, edit file modular terkait agar dokumen tetap ringan dan tidak kembali menjadi satu spesifikasi panjang.
