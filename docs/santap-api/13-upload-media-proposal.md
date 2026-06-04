# Proposal Teknis: Endpoint Upload Media (Image)

> Status: **PROPOSAL — belum diimplementasikan.** Dokumen ini dibuat sesuai permintaan
> untuk direview lebih dulu. Tidak ada kode/endpoint yang ditambahkan sampai disetujui.

## 1. Latar Belakang (kondisi aktual)

API Santap saat ini **tidak memiliki endpoint upload file sama sekali**. Setelah
penelusuran menyeluruh (`UploadedFile`, `->file()`, `mimes:`, `hasFile`, `Storage::`,
Spatie Media Library), tidak ditemukan satu pun handler upload di lapisan API.

Semua field gambar adalah **string URL/path** (maks 500 karakter), bukan file:

| Field    | Endpoint                          | Aturan validasi                   |
|----------|-----------------------------------|-----------------------------------|
| `image`  | `POST/PUT /v1/menus`              | `nullable, string, max:500`       |
| `logo`   | `PUT /v1/organizations/current`   | `nullable, string, max:500`       |
| `banner` | `PUT /v1/organizations/current`   | `nullable, string, max:500`       |
| `avatar` | `PUT /v1/auth/profile`            | `nullable, string, max:500`       |

Artinya: frontend/mobile harus meng-host gambar sendiri (mis. ke storage/CDN eksternal)
lalu mengirim URL-nya ke API. Upload file fisik hanya terjadi di **Filament admin panel**
(server-side), tidak diekspos lewat API.

Package yang relevan: hanya `spatie/laravel-permission`. **Tidak ada** media library.
Disk default `local`; disk `public` tersedia (`storage/app/public`).

## 2. Tujuan Proposal

Menyediakan satu endpoint upload generik yang:

- Menerima file gambar via `multipart/form-data`.
- Menyimpan ke disk publik dan mengembalikan **URL** yang bisa langsung dipakai sebagai
  nilai field `image`/`logo`/`banner`/`avatar` yang sudah ada — **tanpa mengubah** kontrak
  field tersebut (tetap string URL). Ini membuat perubahan bersifat aditif & non-breaking.

## 3. Desain Endpoint (usulan)

```
POST /v1/media/upload
```

- **Auth:** `auth:sanctum` (Bearer). 
- **Org scope:** opsional `X-Org-ID` bila ingin folder per-organisasi.
- **Content-Type:** `multipart/form-data`
- **Body:**
  - `file` (required, file) — gambar yang diupload.
  - `purpose` (optional, string, in: `menu`,`logo`,`banner`,`avatar`) — untuk menentukan
    subfolder/penamaan; default `general`.
- **Validasi usulan:**
  - `file`: `required, file, image, mimes:jpeg,jpg,png,webp, max:2048` (2 MB).
  - `purpose`: `sometimes, in:menu,logo,banner,avatar,general`.
- **Response 201:**
  ```json
  {
    "data": {
      "url": "https://api.santap.app/storage/menu/abc123.webp",
      "path": "menu/abc123.webp",
      "mime": "image/webp",
      "size": 184320
    },
    "message": "File berhasil diupload."
  }
  ```
- **Error:** `401` (belum login), `422` (bukan gambar / kelebihan ukuran / mime salah).

### Alur pemakaian oleh frontend/mobile

1. `POST /v1/media/upload` (multipart, field `file`) → dapat `data.url`.
2. Kirim `data.url` sebagai nilai `image`/`logo`/`banner`/`avatar` di endpoint create/update
   yang sudah ada (JSON biasa, seperti sekarang).

Pendekatan dua langkah ini menjaga endpoint resource tetap JSON murni & idempotent,
serta memisahkan kepedulian upload dari logika bisnis.

## 4. Komponen yang Perlu Dibuat (jika disetujui)

- `routes/api.php`: tambah route di grup `auth:sanctum`.
- `App\Http\Controllers\Api\V1\MediaController` (`store`).
- `App\Http\Requests\Media\UploadMediaRequest` (validasi di atas).
- Konfigurasi storage:
  - Pastikan `FILESYSTEM_DISK`/disk `public` aktif dan `php artisan storage:link` dijalankan,
    **atau** gunakan disk S3/cloud (mis. `s3`) bila produksi tidak melayani file lokal.
  - `APP_URL` benar agar `Storage::url()` menghasilkan URL absolut yang valid.
- Anotasi Scramble: FormRequest dengan field bertipe file otomatis terdokumentasi sebagai
  `multipart/form-data` oleh Scramble.

## 5. Hal yang Perlu Diputuskan

1. **Disk penyimpanan:** lokal (`public` + symlink) atau cloud (S3-compatible)? Untuk
   produksi multi-instance, cloud lebih aman.
2. **Batas ukuran & tipe:** usulan 2 MB & `jpeg/png/webp`. Sesuaikan bila perlu.
3. **Pembersihan file lama:** saat `image` di-update/null, apakah file lama dihapus?
   (Butuh kebijakan; default proposal: tidak menghapus otomatis demi kesederhanaan.)
4. **Otorisasi org:** apakah upload perlu terikat membership organisasi tertentu?

## 6. Dampak & Risiko

- **Non-breaking:** field `image`/`logo`/`banner`/`avatar` tetap string URL; endpoint baru
  hanya menambah cara mendapatkan URL tersebut.
- **Butuh konfigurasi infrastruktur** (disk/symlink/cloud) — di luar perubahan kode murni.
- **Tidak memerlukan migrasi database.**

---

Jika Anda setuju dengan arah ini, beri tahu keputusan untuk poin di Bagian 5, dan
implementasi bisa dilakukan sebagai langkah terpisah.
