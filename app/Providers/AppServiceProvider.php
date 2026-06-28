<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\OrganizationContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response as ScrambleResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrganizationContext::class);
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        Auth::viaRequest('customer-token', function (Request $request) {
            $token = $request->header('X-Public-Token');
            if (! $token) {
                return null;
            }

            $user = new User([
                'name' => 'Customer',
            ]);
            $user->exists = false;

            return $user;
        });

        Gate::define('viewApiDocs', function ($user = null) {
            return (bool) config('app.scramble_docs_enabled', false);
        });

        // ── Scramble: Mobile / Staff API ─────────────────────────────
        // Routes: /v1/* MINUS /v1/customer/*
        Scramble::registerApi('mobile', [
            'ui' => [
                'title' => 'Santap Mobile POS & Staff API',
            ],
            'info' => [
                'description' => <<<'MD'
                    API untuk aplikasi staff kasir, dapur, dan owner.

                    **Autentikasi:** kirim `Authorization: Bearer <token>` (token dari `POST /v1/auth/login`).

                    **Konteks organisasi:** endpoint owner/cashier/kitchen wajib menyertakan header `X-Org-ID` (id organisasi aktif milik user).

                    **Gambar/media:** field `image` (menu), `logo`/`banner` (organisasi), dan `avatar` (profil) berisi **URL string** ke gambar pada response. Untuk meng-unggah file, tersedia endpoint upload khusus (`multipart/form-data`): `POST /v1/auth/profile/avatar`, `POST /v1/menus/{id}/image`, `POST /v1/organizations/current/logo`, dan `POST /v1/organizations/current/banner` — kirim field file (jpeg/png/webp, maks 2 MB); gambar lama otomatis diganti dan response mengembalikan resource dengan URL gambar terbaru.

                    **Konsep Sesi Open Bill:**
                    - Open Bill adalah sesi order meja yang diinisiasi oleh staff/kasir dari mobile app (bukan dibuat langsung oleh pelanggan di customer web).
                    - Begitu Open Bill dibuat, API menghasilkan data order dengan `order_type = open_bill`, status sesi awal `bill_status = open`, status order `order_status = pending`, status pembayaran `payment_status = unpaid`, dan `public_token` unik untuk QR session.
                    - QR session ini bisa discan pelanggan untuk masuk ke sesi open bill aktif (`EnsureCustomerToken` middleware memverifikasi status `bill_status = open` dan order tidak dibatalkan).
                    - Selama sesi aktif (`bill_status = open`), pelanggan diperbolehkan menambah item. Setiap submit repeat order membuat batch item baru di `order_items` (`batch_uuid`, `batch_number`, `submitted_at`) tanpa membuat row baru di `orders`.
                    - Item yang ditambahkan otomatis memajukan status order menjadi `confirmed` dan langsung masuk antrian dapur (`v1/kitchen/orders`) untuk dimasak tanpa harus bayar dulu. Response detail menyertakan `item_batches` untuk tampilan "Pesanan #1", "Pesanan #2", dan seterusnya.
                    - Pembayaran diselesaikan di akhir. Setelah lunas (tunai/QRIS) atau ditutup secara manual oleh kasir, status bill menjadi `bill_status = closed` dan sesi berakhir. Jika open bill dibatalkan, status order & payment diset `cancelled`, status bill diset `closed`, dan sesi customer otomatis tidak aktif/valid lagi.

                    **Siklus Hidup Status Open Bill:**
                    - **A. Saat Dibuat:** `order_type = open_bill`, `bill_status = open`, `order_status = pending`, `payment_status = unpaid`, `opened_at = now()`.
                    - **B. Saat Item Ditambahkan:** `bill_status = open`, `order_status` minimal `confirmed`, `payment_status` tetap `unpaid` atau `pending` (saat checkout).
                    - **C. Saat Sukses Bayar:** `payment_status = paid`, `bill_status = closed`, `paid_at = now()`, `closed_at = now()`.
                    - **D. Saat Dibatalkan:** `order_status = cancelled`, `payment_status = cancelled`, `bill_status = closed`, `cancelled_at = now()`, `closed_at = now()`.
                    - **E. Saat QRIS Attempt Dibatalkan:** `payment_status = cancelled` (atau `failed`), reference dibersihkan, namun `bill_status` tetap `open` dan `order_status` **TIDAK** menjadi `cancelled` (pelanggan masih bisa lanjut sesi/order item).

                    **QRIS & Mutasi Item:**
                    - `POST /v1/cashier/orders/{id}/pay-qris` mengunci order terkait saja. Jika order yang sama masih punya QRIS pending aktif, API mengembalikan QRIS existing.
                    - QRIS hanya bisa dibuat saat total > 0. Selama QRIS pending, penambahan/perubahan item diblokir.

                    **Notifikasi Real-Time (Event Broadcasting):**
                    Sistem menggunakan Laravel Reverb untuk notifikasi real-time tanpa delay.

                    ---

                    ### 📡 Real-Time WebSockets: Staff Channel
                    
                    Gunakan panduan berikut untuk menghubungkan WebSocket client di aplikasi staff (Flutter/Mobile POS) guna memantau pesanan masuk secara real-time.

                    #### **Detail Channel**
                    * **Tujuan**: Memantau pesanan baru, repeat order, pembaruan item dapur, dan pelunasan transaksi dari semua meja dalam organisasi secara real-time.
                    * **Nama Channel**: `private-organization.{orgId}` (Ganti `{orgId}` dengan ID organisasi aktif).
                    * **Daftar Event & Kegunaan**:
                      1. `order-placed` (Class event: `App\Events\OrderPlaced`) — Notifikasi saat ada pesanan baru masuk dari meja (Table Order).
                      2. `repeat-order-created` (Class event: `App\Events\OpenBillRepeatOrderCreated`) — Notifikasi saat pelanggan menambahkan menu baru ke sesi Open Bill aktif.
                      3. `item-status-updated` (Class event: `App\Events\OrderItemStatusUpdated`) — Notifikasi saat dapur memperbarui status menu (Dimasak, Siap, Tersaji).
                      4. `order-paid` (Class event: `App\Events\OrderPaid`) — Notifikasi saat pesanan meja berhasil dibayar lunas.
                    * **Metode Autentikasi**: Otentikasi menggunakan token Bearer staff di header `Authorization: Bearer <staff_token>` dan header `X-Org-ID: <org_id>`. Sistem memverifikasi apakah staff memiliki role aktif di organisasi tersebut.

                    #### **Cara Kerja di Aplikasi Staff**
                    
                    1. **Konfigurasikan WebSocket client** untuk mengirimkan token Bearer saat melakukan otentikasi ke `/broadcasting/auth`:
                       ```text
                       Authorization: Bearer <token_staff>
                       X-Org-ID: <org_id>
                       ```
                    
                    2. **Lakukan subscribe ke:**
                       ```javascript
                       const channel = Echo.private('organization.' + orgId);

                       // Listen pesanan baru dari meja
                       channel.listen('.order-placed', (e) => {
                           console.log('Pesanan meja baru masuk:', e);
                       });

                       // Listen tambahan menu ke open bill aktif
                       channel.listen('.repeat-order-created', (e) => {
                           console.log('Tambahan menu masuk:', e);
                       });

                       // Listen update status menu dapur
                       channel.listen('.item-status-updated', (e) => {
                           console.log('Status item dapur berubah:', e);
                       });

                       // Listen pelunasan pembayaran
                       channel.listen('.order-paid', (e) => {
                           console.log('Pesanan telah dibayar lunas:', e);
                       });
                       ```
                    MD,
                'version' => '1.0.0',
            ],
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'v1')
                && ! Str::startsWith($route->uri(), 'v1/customer');
        })->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
                    ->as('Sanctum')
                    ->setDescription('Gunakan token Bearer untuk otentikasi. Dapatkan dari endpoint login.')
            );
        })->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
            $middleware = $routeInfo->route->gatherMiddleware();
            $hasSanctum = collect($middleware)->contains(function ($m) {
                return $m === 'auth:sanctum' || (is_string($m) && str_starts_with($m, 'auth:'));
            });

            if (! $hasSanctum) {
                $operation->security = [];
            }

            // Endpoint yang scoped per organisasi memerlukan header X-Org-ID.
            $needsOrgHeader = collect($middleware)->contains(function ($m) {
                return is_string($m) && (
                    str_contains($m, 'resolve.organization')
                    || str_contains($m, 'ResolveOrganization')
                );
            });

            if ($needsOrgHeader) {
                $operation->addParameters([
                    Parameter::make('X-Org-ID', 'header')
                        ->setSchema(Schema::fromType(new StringType))
                        ->required(true)
                        ->description('ID organisasi aktif. Wajib untuk endpoint owner/cashier/kitchen yang terikat ke satu organisasi.'),
                ]);
            }

            // ── Error responses standar ──────────────────────────────────
            // Didokumentasikan sesuai middleware & handler global (bootstrap/app.php),
            // bukan asumsi. Hanya ditambahkan bila Scramble belum mendeteksinya.
            $hasPathParams = count($routeInfo->route->parameterNames()) > 0;

            if ($hasSanctum) {
                $this->addApiError($operation, 401, 'Belum terautentikasi — Bearer token tidak ada / tidak valid / sudah dicabut.');
            }

            if ($needsOrgHeader) {
                // ResolveOrganization + EnsureOrganizationMember.
                $this->addApiError($operation, 400, 'Header X-Org-ID tidak disertakan.');
                $this->addApiError($operation, 403, 'Organisasi tidak aktif, atau Anda bukan member organisasi ini (sebagian aksi hanya untuk owner).');
                $this->addApiError($operation, 404, 'Organisasi atau resource (mis. menu/meja/order pada {id}) tidak ditemukan.');
            } elseif ($hasPathParams) {
                $this->addApiError($operation, 404, 'Resource tidak ditemukan.');
            }

            // Tag eksplisit per endpoint berdasarkan URI route.
            // Scramble mengabaikan @tags level-method dan menambah tag nama class
            // sebagai tag kedua, jadi kita set ulang tag tunggal yang bersih di sini.
            $uri = $routeInfo->route->uri();
            $tag = match (true) {
                Str::contains($uri, ['pay-cash', 'pay-qris', 'qris-status', 'qris-cancel']) => 'Mobile Payment',
                Str::startsWith($uri, 'v1/auth') => 'Mobile Auth',
                Str::startsWith($uri, 'v1/organizations') => 'Mobile Organization',
                Str::startsWith($uri, 'v1/menus') => 'Mobile Menu',
                Str::startsWith($uri, 'v1/dining-tables') => 'Mobile Table',
                Str::startsWith($uri, 'v1/kitchen') => 'Mobile Kitchen',
                Str::startsWith($uri, 'v1/cashier') => 'Mobile Cashier Order',
                Str::startsWith($uri, 'v1/reports') => 'Mobile Reports',
                default => null,
            };

            if ($tag !== null) {
                $operation->setTags([$tag]);
            }

            $this->describeStaffOperation($operation, $uri);
        });

        // ── Scramble: Customer Web API ────────────────────────────────
        // Routes: /v1/customer/*
        Scramble::registerApi('web-customer', [
            'ui' => [
                'title' => 'Santap Customer Web API',
            ],
            'info' => [
                'description' => <<<'MD'
                    API publik tanpa login untuk pelanggan di meja (Nuxt web app).

                    **Akses Sesi Open Bill:**
                    - Pelanggan **TIDAK** membuat open bill secara langsung. Sesi open bill harus dibuat terlebih dahulu oleh kasir/staff di mobile app POS.
                    - Pelanggan men-scan QR code meja yang berisi link sesi (`/o/{slug}/orders?bill={public_token}`).
                    - Token `public_token` dikirimkan oleh browser customer lewat header `X-Public-Token`.
                    - Middleware `EnsureCustomerToken` memvalidasi token ini. Token dianggap valid jika dan hanya jika:
                      * `order_type = open_bill`
                      * `bill_status = open`
                      * `order_status != cancelled`
                      * `cancelled_at IS NULL` dan `closed_at IS NULL`
                    - Jika tidak memenuhi kriteria di atas, server mengembalikan error `403 Forbidden` dengan pesan `"Sesi open bill tidak valid atau sudah berakhir."`.

                    **Siklus Hidup & Alur Kerja:**
                    - **Tambah Item:** Selama sesi aktif, customer bebas menambah item lewat `POST /v1/customer/order/items`. Item akan langsung terkirim ke dapur untuk dimasak tanpa perlu membayar di awal.
                    - **Kunci Pembayaran (QRIS):** Saat melakukan checkout/payment QRIS (`POST /v1/customer/order/pay-qris`), status pembayaran menjadi `pending` dan item dikunci (tidak bisa ditambah/diubah/dihapus) untuk mencegah ketidaksesuaian nominal.
                    - **Cancel QRIS Attempt vs Cancel Open Bill:** Jika pelanggan membatalkan QRIS attempt (`DELETE /v1/customer/order/qris-cancel`), status pembayaran diset `cancelled` tapi sesi open bill tetap `open` dan `order_status` **TIDAK** dibatalkan (customer bisa memesan lagi atau generate QRIS baru).
                    - **Pelunasan:** Setelah QRIS sukses terbayar (settlement), status pembayaran otomatis disinkronkan, status pembayaran menjadi `paid`, status bill menjadi `closed`, dan sesi berakhir.

                    **Notifikasi Real-Time (Event Broadcasting):**
                    Sistem menggunakan Laravel Reverb untuk notifikasi real-time tanpa delay.

                    ---

                    ### 📡 Real-Time WebSockets: Open Bill & Customer Channel
                    
                    Gunakan panduan berikut untuk menghubungkan WebSocket client di aplikasi pelanggan guna mendengarkan pembaruan pesanan secara real-time.

                    #### **Detail Channel**
                    * **Tujuan**: Mendengarkan update status open bill, pesanan berulang, update status masak, atau pelunasan miliknya sendiri di meja makan.
                    * **Nama Channel**: `private-open-bill.{billId}` (Ganti `{billId}` dengan ID Order open bill aktif).
                    * **Daftar Event & Kegunaan**:
                      1. `repeat-order-created` (Class event: `App\Events\OpenBillRepeatOrderCreated`) — Untuk memperbarui data tagihan dan badge jumlah item di layar pelanggan.
                      2. `item-status-updated` (Class event: `App\Events\OrderItemStatusUpdated`) — Untuk memberi notifikasi toast/audio saat dapur menyelesaikan masakan.
                      3. `order-paid` (Class event: `App\Events\OrderPaid`) — Untuk mengalihkan layar pelanggan ke halaman tanda terima/receipt ketika pesanan lunas.
                    * **Metode Autentikasi**: Otentikasi otomatis menggunakan header `X-Public-Token` yang berisi token publik unik sesi meja tersebut. Sistem memverifikasi token ini terhadap database untuk memastikan pelanggan hanya bisa mendengarkan data mejanya sendiri tanpa perlu login.

                    #### **Cara Kerja di Aplikasi Pelanggan**
                    
                    1. **Konfigurasikan WebSocket client** (Laravel Echo / Pusher) untuk mengirimkan header berikut saat melakukan otentikasi ke `/broadcasting/auth`:
                       ```javascript
                       X-Public-Token: <token_publik_sesi_meja>
                       ```
                    
                    2. **Lakukan subscribe ke:**
                       ```javascript
                       const channel = Echo.private('open-bill.' + billId);

                       // Listen tambahan pesanan
                       channel.listen('.repeat-order-created', (e) => {
                           console.log('Update pesanan meja:', e);
                       });

                       // Listen update status masak dari dapur
                       channel.listen('.item-status-updated', (e) => {
                           console.log('Notifikasi dapur:', e);
                       });

                       // Listen pelunasan order
                       channel.listen('.order-paid', (e) => {
                           console.log('Pembayaran terverifikasi lunas:', e);
                       });
                       ```
                    MD,
                'version' => '1.0.0',
            ],
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'v1/customer');
        })->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::apiKey('header', 'X-Public-Token')
                    ->as('X-Public-Token')
                    ->setDescription('Gunakan public_token yang didapat dari scan QR meja (X-Public-Token header).')
            );
        })->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
            $middleware = $routeInfo->route->gatherMiddleware();
            $hasCustomerToken = collect($middleware)->contains(function ($m) {
                return $m === 'ensure.customer.token' || (is_string($m) && str_contains($m, 'EnsureCustomerToken'));
            });

            // Endpoint publik table order TIDAK memerlukan X-Public-Token.
            // Hanya endpoint open bill (ensure.customer.token) yang di-secure.
            if (! $hasCustomerToken) {
                $operation->security = [];
            }

            // ── Error responses standar ──────────────────────────────────
            // Sesuai EnsureCustomerToken & handler global (bootstrap/app.php).
            $hasPathParams = count($routeInfo->route->parameterNames()) > 0;

            if ($hasCustomerToken) {
                $this->addApiError($operation, 401, 'Header X-Public-Token tidak disertakan.');
                $this->addApiError($operation, 403, 'Sesi open bill tidak valid atau sudah berakhir (karena status bill closed atau order cancelled).');
            } elseif ($hasPathParams) {
                $this->addApiError($operation, 404, 'Order atau resource tidak ditemukan.');
            }

            // Tag eksplisit agar dokumentasi memisahkan flow table order publik
            // dari flow open bill yang berbasis token.
            $uri = $routeInfo->route->uri();
            $tag = match (true) {
                $hasCustomerToken => 'Customer Open Bill',
                Str::contains($uri, 'payment-status') => 'Customer Payment',
                Str::contains($uri, 'receipt/download') => 'Customer Receipt',
                Str::contains($uri, 'v1/customer/orders') => 'Customer Order Tracking',
                Str::contains($uri, 'v1/customer/order') => 'Customer Table Order',
                Str::contains($uri, 'v1/customer/menu') => 'Customer Menu',
                Str::contains($uri, 'v1/customer/table') => 'Customer Table',
                Str::contains($uri, 'v1/customer/organization') => 'Customer Organization',
                default => null,
            };

            if ($tag !== null) {
                $operation->setTags([$tag]);
            }

            $this->describeCustomerOperation($operation, $uri, $hasCustomerToken);
        });

        // ── Scramble: Admin / Full API ────────────────────────────────
        // Dokumentasi paling lengkap untuk admin/dashboard: mencakup SELURUH
        // endpoint v1 (staff/owner + customer + upload media). Keamanan & header
        // diset per-operasi sesuai middleware masing-masing route.
        Scramble::registerApi('admin', [
            'ui' => [
                'title' => 'Santap Admin / Full API',
            ],
            'info' => [
                'description' => <<<'MD'
                    Dokumentasi **lengkap** seluruh endpoint Santap (staff/owner, kasir, dapur, dan pelanggan) dalam satu tempat — untuk admin/superadmin dashboard.

                    **Struktur Sesi Open Bill:**
                    - Open Bill dibuat oleh cashier/mobile app (`order_type = open_bill`, `bill_status = open`).
                    - Customer mengakses via QR session menggunakan `public_token` yang disuplai di header `X-Public-Token`.
                    - Halaman admin memantau riwayat sesi open bill aktif (`bill_status = open` dan `order_status != cancelled`).
                    - Ketika open bill dibatalkan (baik via API cashier maupun aksi di Filament backoffice), status order dan payment diset `cancelled`, status bill ditutup (`bill_status = closed`), dan sesi customer diblokir secara real-time.
                    - Detail siklus hidup status, alur QRIS, dan payload item sama dengan dokumentasi pada group Mobile dan Web Customer.

                    ---

                    ### 📡 Real-Time WebSockets: Channels Summary
                    
                    Sistem menggunakan Laravel Reverb untuk notifikasi real-time tanpa delay.

                    #### **1. Customer Open Bill Channel**
                    * **Tujuan**: Mendengarkan update status open bill atau pesanan berulang miliknya sendiri di meja makan.
                    * **Nama Channel**: `private-open-bill.{billId}`
                    * **Event**: `repeat-order-created`
                    * **Autentikasi**: Otentikasi otomatis menggunakan header `X-Public-Token` yang berisi token publik unik sesi meja tersebut ke `/broadcasting/auth`.

                    #### **2. Staff Organization Channel**
                    * **Tujuan**: Memantau repeat order dan pesanan baru dari semua meja dalam organisasi.
                    * **Nama Channel**: `private-organization.{orgId}`
                    * **Event**: `repeat-order-created`
                    * **Autentikasi**: Otentikasi menggunakan token Bearer staff di header `Authorization: Bearer <staff_token>` dan header `X-Org-ID: <org_id>` ke `/broadcasting/auth`.
                    MD,
                'version' => '1.0.0',
            ],
        ])->routes(function (Route $route) {
            return Str::startsWith($route->uri(), 'v1');
        })->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
                    ->as('Sanctum')
                    ->setDescription('Token Bearer staff/owner. Dapatkan dari endpoint login.')
            );
            $openApi->secure(
                SecurityScheme::apiKey('header', 'X-Public-Token')
                    ->as('X-Public-Token')
                    ->setDescription('public_token pelanggan dari scan QR meja (untuk endpoint open bill).')
            );
        })->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
            $middleware = $routeInfo->route->gatherMiddleware();
            $uri = $routeInfo->route->uri();

            $hasSanctum = collect($middleware)->contains(function ($m) {
                return $m === 'auth:sanctum' || (is_string($m) && str_starts_with($m, 'auth:'));
            });
            $hasCustomerToken = collect($middleware)->contains(function ($m) {
                return $m === 'ensure.customer.token' || (is_string($m) && str_contains($m, 'EnsureCustomerToken'));
            });
            $needsOrgHeader = collect($middleware)->contains(function ($m) {
                return is_string($m) && (
                    str_contains($m, 'resolve.organization')
                    || str_contains($m, 'ResolveOrganization')
                );
            });

            // ── Keamanan per-operasi (skema digabung di document transformer) ──
            if ($hasSanctum) {
                $operation->security = [new SecurityRequirement(['Sanctum' => []])];
            } elseif ($hasCustomerToken) {
                $operation->security = [new SecurityRequirement(['X-Public-Token' => []])];
            } else {
                $operation->security = [];
            }

            // Header X-Org-ID untuk endpoint yang scoped per organisasi.
            if ($needsOrgHeader) {
                $operation->addParameters([
                    Parameter::make('X-Org-ID', 'header')
                        ->setSchema(Schema::fromType(new StringType))
                        ->required(true)
                        ->description('ID organisasi aktif. Wajib untuk endpoint owner/cashier/kitchen yang terikat ke satu organisasi.'),
                ]);
            }

            // ── Error responses standar ───────────────────────────────────
            $hasPathParams = count($routeInfo->route->parameterNames()) > 0;

            if ($hasSanctum) {
                $this->addApiError($operation, 401, 'Belum terautentikasi — Bearer token tidak ada / tidak valid / sudah dicabut.');
            }

            if ($needsOrgHeader) {
                $this->addApiError($operation, 400, 'Header X-Org-ID tidak disertakan.');
                $this->addApiError($operation, 403, 'Organisasi tidak aktif, atau Anda bukan member organisasi ini (sebagian aksi hanya untuk owner).');
                $this->addApiError($operation, 404, 'Organisasi atau resource (mis. menu/meja/order pada {id}) tidak ditemukan.');
            } elseif ($hasCustomerToken) {
                $this->addApiError($operation, 401, 'Header X-Public-Token tidak disertakan.');
                $this->addApiError($operation, 403, 'Sesi open bill tidak valid atau sudah berakhir (karena status bill closed atau order cancelled).');
            } elseif ($hasPathParams) {
                $this->addApiError($operation, 404, 'Resource tidak ditemukan.');
            }

            // ── Tag: pisahkan flow customer dari staff agar dokumentasi rapi ──
            if (Str::startsWith($uri, 'v1/customer')) {
                $tag = match (true) {
                    $hasCustomerToken => 'Customer Open Bill',
                    Str::contains($uri, 'payment-status') => 'Customer Payment',
                    Str::contains($uri, 'receipt/download') => 'Customer Receipt',
                    Str::contains($uri, 'v1/customer/orders') => 'Customer Order Tracking',
                    Str::contains($uri, 'v1/customer/order') => 'Customer Table Order',
                    Str::contains($uri, 'v1/customer/menu') => 'Customer Menu',
                    Str::contains($uri, 'v1/customer/table') => 'Customer Table',
                    Str::contains($uri, 'v1/customer/organization') => 'Customer Organization',
                    default => null,
                };
            } else {
                $tag = match (true) {
                    Str::contains($uri, ['pay-cash', 'pay-qris', 'qris-status', 'qris-cancel']) => 'Staff Payment',
                    Str::startsWith($uri, 'v1/auth') => 'Staff Auth',
                    Str::startsWith($uri, 'v1/organizations') => 'Staff Organization',
                    Str::startsWith($uri, 'v1/menus') => 'Staff Menu',
                    Str::startsWith($uri, 'v1/dining-tables') => 'Staff Table',
                    Str::startsWith($uri, 'v1/kitchen') => 'Staff Kitchen',
                    Str::startsWith($uri, 'v1/cashier') => 'Staff Cashier Order',
                    Str::startsWith($uri, 'v1/reports') => 'Staff Reports',
                    default => null,
                };
            }

            if ($tag !== null) {
                $operation->setTags([$tag]);
            }

            if (Str::startsWith($uri, 'v1/customer')) {
                $this->describeCustomerOperation($operation, $uri, $hasCustomerToken);
            } else {
                $this->describeStaffOperation($operation, $uri);
            }
        });

        // ── Scramble UI & JSON Spec Routes ────────────────────────────
        Scramble::registerUiRoute('docs/api/mobile', api: 'mobile');
        Scramble::registerJsonSpecificationRoute('docs/api/mobile/api.json', api: 'mobile');

        Scramble::registerUiRoute('docs/api/web-customer', api: 'web-customer');
        Scramble::registerJsonSpecificationRoute('docs/api/web-customer/api.json', api: 'web-customer');

        Scramble::registerUiRoute('docs/api/admin', api: 'admin');
        Scramble::registerJsonSpecificationRoute('docs/api/admin/api.json', api: 'admin');

        // ── Rate Limiters ─────────────────────────────────────────────
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('customer-order', function (Request $request) {
            $token = $request->header('X-Public-Token') ?: $request->ip();

            return Limit::perMinute(10)->by($token);
        });

        RateLimiter::for('qris-check', function (Request $request) {
            $token = $request->header('X-Public-Token') ?: $request->ip();

            return Limit::perMinute(20)->by($token);
        });
    }

    private function describeStaffOperation(Operation $operation, string $uri): void
    {
        $method = strtolower($operation->method);

        [$summary, $description, $validationError] = match (true) {

            // ── Auth ─────────────────────────────────────────────────────────
            $uri === 'v1/auth/login' && $method === 'post' => [
                'Login staff',
                'Autentikasi staff (owner / cashier / kitchen). Response menyertakan data user, daftar organisasi beserta `role` per organisasi, dan Bearer token. '.
                'Masukkan token ke tombol **Authorize** sebagai Bearer Token untuk mengakses endpoint lain. '.
                'Token tidak memiliki masa berlaku bawaan — gunakan `POST /v1/auth/logout` untuk mencabutnya.',
                'Email atau password salah.',
            ],
            $uri === 'v1/auth/logout' && $method === 'post' => [
                'Logout staff',
                'Mencabut (revoke) Bearer token yang sedang dipakai. Setelah logout, token tidak bisa dipakai lagi. '.
                'Untuk multi-device logout, lakukan request dari masing-masing perangkat atau hapus semua token via Filament admin.',
                null,
            ],
            $uri === 'v1/auth/me' && $method === 'get' => [
                'Get current user profile',
                'Mengembalikan data user yang sedang login beserta daftar organisasi dan `role`-nya (owner/cashier/kitchen) dari tabel `organization_members`.',
                null,
            ],
            $uri === 'v1/auth/profile' && $method === 'put' => [
                'Update user profile',
                'Update data profil user yang sedang login: nama, nomor telepon, dan/atau avatar URL. '.
                'Untuk upload file avatar, gunakan endpoint terpisah `POST /v1/auth/profile/avatar`.',
                'Field tidak valid (mis. email duplikat, format telepon salah).',
            ],
            $uri === 'v1/auth/profile/avatar' && $method === 'post' => [
                'Upload user avatar',
                'Upload foto profil user (multipart/form-data). Field file: `avatar` (jpeg/jpg/png/webp, maks 2 MB). '.
                'File lama otomatis dihapus dari storage. Response berisi data user terbaru dengan field `avatar` berisi URL siap pakai.',
                'File tidak ada, bukan gambar, atau ukuran > 2 MB.',
            ],

            // ── Organization ──────────────────────────────────────────────────
            $uri === 'v1/organizations' && $method === 'get' => [
                'List my organizations',
                'Mengembalikan semua organisasi yang diikuti user yang sedang login. '.
                'Setiap item menyertakan field `role` (owner/cashier/kitchen) yang menunjukkan peran user pada organisasi tersebut.',
                null,
            ],
            $uri === 'v1/organizations' && $method === 'post' => [
                'Create organization',
                'Membuat organisasi baru. User pembuat otomatis menjadi `owner` dari organisasi yang dibuat. '.
                '`slug` harus unik dan hanya boleh mengandung huruf kecil, angka, dan tanda hubung.',
                'Nama atau slug tidak valid, atau slug sudah dipakai organisasi lain.',
            ],
            $uri === 'v1/organizations/current' && $method === 'get' => [
                'Get current organization',
                'Mengambil detail organisasi aktif berdasarkan header `X-Org-ID`. '.
                'Response menyertakan konfigurasi lengkap termasuk pajak, service charge, dan field `order_marker` untuk fitur Nomor Penanda Pesanan: '.
                '`mode` (disabled/optional/required), `max_number` (batas atas nomor yang diizinkan), dan `label`.',
                null,
            ],
            $uri === 'v1/organizations/current' && $method === 'put' => [
                'Update organization settings',
                'Update pengaturan organisasi aktif. Hanya member dengan role `owner` yang diizinkan.'."\n\n".
                '**Nomor Penanda Pesanan:** Atur via field `order_marker_mode` (`disabled`/`optional`/`required`) dan `order_marker_max_number` (integer, min 1, maks 9999). '.
                'Jika `order_marker_mode` diset ke `disabled`, `order_marker_max_number` dikosongkan otomatis.'."\n\n".
                '**Gambar:** Gunakan endpoint upload khusus (`POST /logo`, `POST /banner`) — field ini menerima URL string, bukan file.',
                'Field tidak valid, atau Anda bukan owner organisasi ini.',
            ],
            $uri === 'v1/organizations/current/logo' && $method === 'post' => [
                'Upload organization logo',
                'Upload logo organisasi aktif (multipart/form-data). Hanya owner. '.
                'Field file: `logo` (jpeg/jpg/png/webp, maks 2 MB). Logo lama otomatis dihapus dari storage.',
                'File tidak ada, bukan gambar, ukuran > 2 MB, atau Anda bukan owner.',
            ],
            $uri === 'v1/organizations/current/banner' && $method === 'post' => [
                'Upload organization banner',
                'Upload banner/foto sampul organisasi aktif (multipart/form-data). Hanya owner. '.
                'Field file: `banner` (jpeg/jpg/png/webp, maks 2 MB). Banner lama otomatis dihapus dari storage.',
                'File tidak ada, bukan gambar, ukuran > 2 MB, atau Anda bukan owner.',
            ],

            // ── Dining Table ──────────────────────────────────────────────────
            $uri === 'v1/dining-tables' && $method === 'get' => [
                'List dining tables',
                'Mengembalikan semua meja organisasi aktif diurutkan berdasarkan nama. '.
                'Setiap meja menyertakan `qr_token` yang dipakai customer untuk mengakses menu via QR code. '.
                'Endpoint ini **berbeda** dari fitur Nomor Penanda Pesanan (`order_marker_number`) — `dining_tables` adalah data master meja fisik.',
                null,
            ],
            $uri === 'v1/dining-tables' && $method === 'post' => [
                'Create dining table',
                'Membuat meja baru. `qr_token` digenerate otomatis (32 karakter random). '.
                'URL QR untuk customer: `{APP_URL}/t/{qr_token}`. '.
                'Gunakan `POST /{id}/regenerate-qr` untuk mereset QR token jika diperlukan.',
                'Field tidak valid atau nama/kode meja sudah dipakai di organisasi ini.',
            ],
            $uri === 'v1/dining-tables/{id}' && $method === 'put' => [
                'Update dining table',
                'Update data meja (nama, kode, kapasitas, lokasi, status aktif). QR token tidak berubah saat update biasa — gunakan `POST /{id}/regenerate-qr` untuk mereset QR.',
                'Field tidak valid atau meja tidak ditemukan.',
            ],
            $uri === 'v1/dining-tables/{id}' && $method === 'delete' => [
                'Delete dining table',
                'Menghapus meja dari organisasi. Hati-hati: order yang sudah ada dengan `dining_table_id` ini tidak ikut terhapus (relasi di-nullify atau dipertahankan sesuai constraint).',
                null,
            ],
            $uri === 'v1/dining-tables/{id}/regenerate-qr' && $method === 'post' => [
                'Regenerate QR token',
                'Mereset `qr_token` meja dengan token baru (32 karakter random). Token lama otomatis tidak berlaku — semua link QR lama yang sudah dicetak tidak akan berfungsi lagi. '.
                'Gunakan ini jika QR code bocor atau meja berpindah tempat.',
                null,
            ],

            // ── Menu ──────────────────────────────────────────────────────────
            $uri === 'v1/menus' && $method === 'get' => [
                'List menus (tree)',
                'Mengembalikan semua produk beserta variant/addon dalam struktur tree 2 level. '.
                'Struktur: `product` → `variant_group`/`addon_group` → `variant`/`addon`. '.
                'Urutan berdasarkan `sort_order`. Hanya produk root (`type = product`) yang dikembalikan sebagai root item — children di-load via eager loading.',
                null,
            ],
            $uri === 'v1/menus' && $method === 'post' => [
                'Create menu item',
                'Membuat menu baru. Hierarki yang diizinkan:'."\n".
                '- `product` → tidak boleh punya parent'."\n".
                '- `variant_group` / `addon_group` → parent harus `product`'."\n".
                '- `variant` → parent harus `variant_group`'."\n".
                '- `addon` → parent harus `addon_group`'."\n\n".
                'Field `is_required`, `min_select`, `max_select` hanya relevan untuk grup (variant_group/addon_group). '.
                'Untuk upload gambar, gunakan `POST /v1/menus/{id}/image` setelah menu dibuat.',
                'Tipe tidak valid, hierarki parent-child tidak sesuai, atau parent bukan milik organisasi ini.',
            ],
            $uri === 'v1/menus/{id}' && $method === 'put' => [
                'Update menu item',
                'Update data menu (nama, harga, deskripsi, SKU, sort order, ketersediaan, dll). '.
                'Response menyertakan children 2 level. Untuk pindah parent atau ganti gambar, gunakan endpoint terpisah.',
                'Field tidak valid atau menu tidak ditemukan di organisasi ini.',
            ],
            $uri === 'v1/menus/{id}' && $method === 'delete' => [
                'Delete menu item',
                'Menghapus menu beserta seluruh children-nya secara cascade (via foreign key). '.
                'Menghapus `product` akan otomatis menghapus semua `variant_group`, `addon_group`, `variant`, dan `addon` di bawahnya.',
                null,
            ],
            $uri === 'v1/menus/{id}/image' && $method === 'post' => [
                'Upload menu image',
                'Upload gambar produk (multipart/form-data). Field file: `image` (jpeg/jpg/png/webp, maks 2 MB). '.
                'Gambar lama otomatis dihapus dari storage. Response berisi data menu terbaru dengan field `image` berisi URL siap pakai.',
                'File tidak ada, bukan gambar, atau ukuran > 2 MB.',
            ],
            $uri === 'v1/menus/{id}/toggle' && $method === 'patch' => [
                'Toggle menu availability',
                'Toggle ketersediaan menu (`is_available`) antara aktif dan nonaktif. '.
                'Menu yang nonaktif tidak akan muncul di halaman pelanggan (`GET /v1/customer/menu`). '.
                'Idempotent — bisa dipanggil berulang untuk flip status.',
                null,
            ],

            // ── Kitchen ───────────────────────────────────────────────────────
            $uri === 'v1/kitchen/orders' && $method === 'get' => [
                'Kitchen order queue',
                'Antrian order untuk layar dapur. Mengembalikan order dengan `order_status` `confirmed` atau `preparing`, '.
                'diurutkan berdasarkan waktu masuk (FIFO). Cocok untuk polling layar dapur setiap beberapa detik. '.
                'Setiap order menyertakan item dan data meja.',
                null,
            ],
            $uri === 'v1/kitchen/order-items/{id}/status' && $method === 'patch' => [
                'Update order item status',
                'Update status satu item order dari dapur. Nilai `item_status` yang valid: `preparing`, `ready`, `served`, `cancelled`.'."\n\n".
                '**Item-driven status rollup:** setelah item diupdate, `order_status` parent diturunkan otomatis dari agregat semua item:'."\n".
                '- Semua `served` → `completed`'."\n".
                '- Semua `ready` → `ready`'."\n".
                '- Ada yang `preparing` → `preparing`'."\n".
                '- Sisanya → `confirmed`',
                'Item tidak ditemukan atau status tidak valid.',
            ],

            // ── Cashier ───────────────────────────────────────────────────────
            $uri === 'v1/cashier/orders' && $method === 'get' => [
                'List cashier/open bill orders',
                'Mengambil order hari ini dalam organisasi aktif. Filter `order_type=open_bill&bill_status=open` untuk daftar open bill aktif. Response list menyertakan `batch_count` dan `latest_batch` agar mobile cashier/kitchen dapat menandai repeat order terbaru.',
                null,
            ],
            $uri === 'v1/cashier/orders' && $method === 'post' => [

                'Create cashier/open bill order',
                'Membuat order kasir atau open bill. Untuk open bill, pastikan menyuplai body request: '."\n".
                '`{ "order_type": "open_bill", "dining_table_id": 1, "customer_name": "Ilham", "customer_phone": "087xxxx", "note": "Opsional" }`. '."\n".
                'API akan menghasilkan order dengan `bill_status = open` dan `public_token` sebagai penanda QR Session bagi customer.'."\n\n".
                '**Nomor Penanda Pesanan (`order_marker_number`):** Field integer opsional untuk nomor fisik (akrilik, table tent, nomor panggilan). '.
                'Perilaku bergantung pada `order_marker_mode` di konfigurasi organisasi: '.
                '`disabled` — field diabaikan (disimpan null); '.
                '`optional` — boleh diisi angka 1 s.d. `order_marker_max_number`; '.
                '`required` — wajib diisi. '.
                'Baca konfigurasi dari `GET /v1/organizations/current` → field `order_marker`. '.
                'Field ini **tidak berhubungan** dengan `dining_table_id` dan tidak ada master data nomor di database.',
                'Meja tidak valid, meja sudah memiliki open bill aktif, atau `order_marker_number` tidak sesuai aturan konfigurasi organisasi.',
            ],
            $uri === 'v1/cashier/orders/{id}' && $method === 'get' => [
                'Show cashier/open bill order',
                'Mengambil detail order beserta item, grouping `item_batches`, summary batch, dan metadata QRIS ringkas (`qris.active`, `qris.attempts_count`, `qris.is_expired`). Open Bill tetap satu order; repeat order hanya menambah batch item.',
                null,
            ],
            $uri === 'v1/cashier/orders/{id}/items' && $method === 'post' => [
                'Add items to open bill',
                'Menambah item ke order. Untuk Open Bill, setiap submit membuat batch baru di `order_items` dengan `batch_uuid`, `batch_number`, dan `submitted_at`; tidak membuat order/child order baru. Gunakan `selected_options[{group_id, option_id}]` untuk variant/addon; `selected_variants` masih diterima sebagai legacy payload. Menu, group, option, dan meja harus berada dalam organisasi yang sama.',
                'Item tidak bisa dimutasi saat QRIS pending aktif, payment paid, bill closed/cancelled, atau menu/options bukan milik organisasi/order yang sama.',
            ],
            $uri === 'v1/cashier/orders/{id}/items/{itemId}' && $method === 'patch' => [
                'Update item quantity',
                'Mengubah quantity root item pending pada order. Pending/paid QRIS memblokir perubahan agar nominal QRIS tidak berbeda dari bill.',
                'Item tidak bisa dimutasi saat QRIS pending aktif, payment paid, bill closed/cancelled, atau item bukan milik order ini.',
            ],
            $uri === 'v1/cashier/orders/{id}/items/{itemId}' && $method === 'delete' => [
                'Remove item',
                'Menghapus root item pending dari order. Pending/paid QRIS memblokir perubahan agar nominal QRIS tidak berbeda dari bill.',
                'Item tidak bisa dimutasi saat QRIS pending aktif, payment paid, bill closed/cancelled, atau item bukan milik order ini.',
            ],
            $uri === 'v1/cashier/orders/{id}/pay-qris' && $method === 'post' => [
                'Create or reuse QRIS for this order',
                'Membuat QRIS untuk order ini saja dengan row lock pada order terkait. Jika order yang sama masih punya QRIS pending aktif, API mengembalikan QRIS existing. Order lain tetap bebas membuat QRIS masing-masing. QRIS hanya bisa dibuat saat total > 0; regenerate diizinkan setelah cancelled, failed, atau expired yang sudah disinkronkan.',
                'QRIS tidak bisa dibuat saat total 0, order sudah paid/closed/cancelled, atau order tidak punya item aktif.',
            ],
            $uri === 'v1/cashier/orders/{id}/qris-status' && $method === 'get' => [
                'Sync QRIS status',
                'Menyinkronkan status QRIS order ini ke provider. Jika provider menyatakan paid, payment menjadi paid dan bill ditutup tanpa mengubah status kitchen/item secara salah. Jika expired/cancelled/failed, attempt aktif diarsipkan.',
                null,
            ],
            $uri === 'v1/cashier/orders/{id}/qris-cancel' && $method === 'delete' => [
                'Cancel active QRIS',
                'Membatalkan QRIS pending order ini, mengarsipkan attempt aktif ke `orders.metadata.qris_attempts[]`, lalu mengosongkan reference/expiry aktif agar item bisa diedit atau QRIS bisa diregenerate.',
                'QRIS tidak bisa dibatalkan jika tidak ada attempt pending aktif atau provider menolak cancel.',
            ],
            $uri === 'v1/cashier/orders/{id}/pay-cash' && $method === 'post' => [
                'Pay by cash and close bill',
                'Mencatat pembayaran tunai dan menutup bill. Status order kitchen tetap dipertahankan kecuali order masih pending/cancelled dan perlu dikonfirmasi.',
                'Jumlah uang kurang atau order sudah dibayar.',
            ],
            $uri === 'v1/cashier/orders/{id}/close' && $method === 'post' => [
                'Close bill',
                'Menutup bill secara manual untuk tipe open_bill (bill_status = closed, closed_at = now). Sesi berakhir sehingga QR session tidak valid lagi, tetapi order tidak dibatalkan (order_status tidak berubah menjadi cancelled).',
                'Order sudah ditutup atau state order tidak valid.',
            ],
            $uri === 'v1/cashier/orders/{id}/confirm' && $method === 'post' => [
                'Confirm order',
                'Memajukan order dari status `pending` ke `confirmed`. Setelah dikonfirmasi, item masuk antrian dapur (`GET /v1/kitchen/orders`). '.
                'Tidak bisa dilakukan jika order sudah bukan `pending`.',
                'Order tidak dalam status pending.',
            ],
            $uri === 'v1/cashier/orders/{id}/cancel' && $method === 'post' => [
                'Cancel order',
                'Membatalkan order yang belum paid/completed. Jika ada QRIS pending, sistem mencoba cancel provider dan mengarsipkan attempt sebelum order dicancel. Khusus tipe open_bill, pembatalan order ini akan otomatis menutup sesi bill (bill_status = closed, closed_at = now) sehingga QR session tidak valid lagi dan customer tidak bisa menambah item.',
                'Order sudah paid/completed/cancelled atau alasan cancel tidak valid.',
            ],

            // Reports
            $uri === 'v1/reports/financial/summary' && $method === 'get' => [
                'Financial report summary',
                'Ringkasan finansial organisasi aktif. Hanya role `owner` yang diizinkan. '.
                'Filter wajib `start_date` dan `end_date` memakai format `YYYY-MM-DD`, maksimal 365 hari, dan dibaca dalam timezone organisasi. '.
                'Revenue hanya dari order `payment_status = paid` dengan basis tanggal `paid_at`; failed/expired/pending QRIS tidak dihitung. '.
                'Cancelled summary dihitung terpisah memakai `cancelled_at`, sehingga order batal tanpa `paid_at` tetap dapat masuk ringkasan batal. '.
                'Response mengembalikan integer Rupiah untuk subtotal, discount, tax, service charge, total revenue, breakdown payment method, order type, dan periode zero-filled.',
                '`start_date`, `end_date`, atau `group_by` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/products/bestsellers' && $method === 'get' => [
                'Product bestsellers report',
                'Top produk berdasarkan root `order_items` yang valid pada order paid dalam rentang `paid_at`. '.
                'Item `cancelled` dikecualikan. Revenue produk memakai snapshot `order_items.subtotal`, bukan harga menu terkini. '.
                'Variant/addon yang dipilih masuk ke revenue produk induk karena subtotal root item menyimpan base price plus option delta. `limit` default 10 dan maksimal 50.',
                '`start_date`, `end_date`, atau `limit` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/products/no-sales' && $method === 'get' => [
                'Products with no sales report',
                'Daftar produk root yang masih tersedia di katalog organisasi aktif tetapi tidak punya item valid pada order paid dalam periode. '.
                '`last_sold_date` dihitung dari paid sale terakhir sampai `end_date`, bukan hanya dari periode filter, dan dilakukan tanpa query per produk.',
                '`start_date` atau `end_date` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/products/by-category' && $method === 'get' => [
                'Product sales by category report',
                'Agregasi penjualan produk per kategori. Schema Santap saat ini tidak memiliki relasi kategori menu setelah restructure, sehingga semua produk digabung dalam bucket `Uncategorized`. '.
                'Percentage berdasarkan revenue produk dan bernilai 0 saat total revenue nol.',
                '`start_date` atau `end_date` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/products/trend' && $method === 'get' => [
                'Product daily trend report',
                'Trend harian satu produk pada organisasi aktif. Produk divalidasi dengan scope organisasi sehingga produk organisasi lain akan terlihat sebagai not found. '.
                'Tanggal tanpa penjualan diisi `qty: 0` dan `revenue: 0`. Revenue memakai snapshot order item pada order paid.',
                '`product_id`, `start_date`, atau `end_date` tidak valid, rentang lebih dari 365 hari, produk tidak ditemukan, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/operational/by-cashier' && $method === 'get' => [
                'Operational report by cashier',
                'Performa operasional berdasarkan `orders.created_by` karena schema orders saat ini tidak memiliki `paid_by` atau `closed_by`. '.
                'Hanya order paid dalam rentang `paid_at` yang dihitung. Order self-service atau creator yang bukan member organisasi digabung ke bucket `Unassigned`, tidak dikaitkan diam-diam ke staff lain.',
                '`start_date` atau `end_date` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            $uri === 'v1/reports/operational/peak-hours' && $method === 'get' => [
                'Operational peak hours report',
                'Distribusi transaksi paid per jam lokal organisasi berdasarkan `paid_at`. Query PostgreSQL memakai `EXTRACT(HOUR FROM timezone(...))`, bukan fungsi MySQL. '.
                'Response selalu berisi jam 0 sampai 23 agar chart frontend stabil.',
                '`start_date` atau `end_date` tidak valid, rentang lebih dari 365 hari, atau user bukan owner organisasi.',
            ],
            default => [null, null, null],
        };

        $this->applyOperationCopy($operation, $summary, $description);

        if ($validationError !== null) {
            $this->addApiError($operation, 422, $validationError, appendIfExists: true);
        }
    }

    private function describeCustomerOperation(Operation $operation, string $uri, bool $hasCustomerToken): void
    {
        $method = strtolower($operation->method);

        [$summary, $description, $validationError] = match (true) {
            // ── Customer Public ────────────────────────────────────────────────
            $uri === 'v1/customer/organization/{slug}' && $method === 'get' => [
                'Get organization info by slug',
                'Mengambil informasi publik organisasi berdasarkan `slug`. Dipakai customer web untuk menampilkan nama, logo, dan konfigurasi restoran sebelum meja di-scan. '.
                'Response berisi field `order_marker` yang memuat mode Nomor Penanda Pesanan. Tidak memerlukan autentikasi.',
                'Organisasi dengan slug tersebut tidak ditemukan atau tidak aktif.',
            ],
            $uri === 'v1/customer/table/{qrToken}' && $method === 'get' => [
                'Scan QR table',
                'Memvalidasi QR token meja dan mengembalikan informasi meja beserta menu restoran. Dipakai customer web/mobile saat memindai QR code di meja. '.
                'Jika meja memiliki open bill aktif (`bill_status = open`), response menyertakan `public_token` untuk dipakai di endpoint open bill. '.
                'Tidak memerlukan autentikasi.',
                'QR token tidak valid, meja tidak aktif, atau meja tidak ditemukan.',
            ],
            $uri === 'v1/customer/menu' && $method === 'get' => [
                'Get public menu',
                'Mengambil daftar menu yang tersedia untuk customer berdasarkan organisasi dari QR token meja. '.
                'Hanya menampilkan menu dengan `is_available = true` dalam struktur tree (product → variant/addon). Tidak memerlukan autentikasi.',
                null,
            ],

            // ── Customer Table Order & Open Bill ──────────────────────────────
            $uri === 'v1/customer/order' && $method === 'post' => [
                'Create table order with QRIS',
                'Checkout table order publik. Order dan QRIS dibuat atomik dalam satu transaksi; jika create QRIS gagal, order dan item di-rollback. Gunakan `selected_options` untuk variant/addon; `selected_variants` tetap legacy.',
                'Payload item/menu/options tidak valid, meja tidak aktif, atau QRIS gagal dibuat.',
            ],
            $uri === 'v1/customer/orders/{order}' && $method === 'get' => [
                'Show public order tracking',
                'Melihat order publik berdasarkan order_number atau public_token. Untuk table order, API sinkron ke provider QRIS lebih dulu sebelum mengembalikan status.',
                null,
            ],
            $uri === 'v1/customer/orders/{order}/payment-status' && $method === 'get' => [
                'Poll table order payment status',
                'Polling status pembayaran table order. Provider adalah source of truth: API cek provider dulu, lalu menandai paid/expired/cancelled secara idempotent.',
                null,
            ],
            $hasCustomerToken && $uri === 'v1/customer/order' && $method === 'get' => [
                'Show active open bill',
                'Mengambil open bill aktif berdasarkan header `X-Public-Token: {public_token}` yang dikirim customer. Menyertakan detail item, `summary`, dan `item_batches` untuk grouping "Pesanan #1", "Pesanan #2", dan seterusnya.',
                null,
            ],
            $uri === 'v1/customer/order/items' && $method === 'post' => [
                'Add items to active open bill',
                'Menambah item ke open bill aktif menggunakan header `X-Public-Token: {public_token}`. Endpoint ini dipakai untuk repeat order: Open Bill tetap satu row `orders`, sedangkan setiap submit membuat batch item baru di `order_items` (`batch_uuid`, `batch_number`, `submitted_at`). Pending/paid QRIS memblokir tambah item.',
                'Item tidak bisa ditambahkan saat QRIS pending aktif, payment paid, bill closed/cancelled, atau menu/options tidak sesuai.',
            ],
            $uri === 'v1/customer/order/pay-qris' && $method === 'post' => [
                'Create or reuse open bill QRIS',
                'Membuat QRIS untuk open bill aktif menggunakan header `X-Public-Token: {public_token}`. Jika open bill masih punya QRIS pending aktif, API mengembalikan QRIS existing.',
                'QRIS tidak bisa dibuat saat total 0, order sudah paid/closed/cancelled, atau order tidak punya item aktif.',
            ],
            $uri === 'v1/customer/order/qris-status' && $method === 'get' => [
                'Poll open bill QRIS status',
                'Menyinkronkan status QRIS open bill ke provider menggunakan header `X-Public-Token: {public_token}`. Jika paid, bill ditutup; jika expired/cancelled/failed, attempt aktif diarsipkan.',
                null,
            ],
            $uri === 'v1/customer/order/qris-cancel' && $method === 'delete' => [
                'Cancel active open bill QRIS',
                'Membatalkan QRIS pending open bill menggunakan header `X-Public-Token: {public_token}`. Membatalkan attempt pembayaran tidak membatalkan/menutup open bill.',
                'QRIS tidak bisa dibatalkan jika tidak ada attempt pending aktif.',
            ],
            $uri === 'v1/customer/orders/{order}/receipt/download' && $method === 'get' => [
                'Download payment receipt PDF',
                'Mengunduh struk pembayaran dalam format PDF untuk order yang sudah lunas (`payment_status = paid`). '.
                'Struk berisi detail pesanan (item, harga, pajak, service charge), data pembayaran, informasi meja, dan branding organisasi. '.
                'Gunakan `order_number` (contoh: `ORD-20260609-0001`) atau `public_token` sebagai parameter `{order}`. '.
                'Tidak memerlukan autentikasi — endpoint publik. Response bertipe `application/pdf`.',
                'Order tidak ditemukan atau belum lunas (payment_status bukan paid).',
            ],
            default => [null, null, null],
        };

        $this->applyOperationCopy($operation, $summary, $description);

        if ($validationError !== null) {
            $this->addApiError($operation, 422, $validationError, appendIfExists: true);
        }
    }

    private function applyOperationCopy(Operation $operation, ?string $summary, ?string $description): void
    {
        if ($summary !== null) {
            $operation->summary($summary);
        }

        if ($description !== null) {
            $operation->description($description);
        }
    }

    /**
     * Tambahkan satu response error standar ke operation bila kode tersebut
     * belum didokumentasikan. Semua error API memakai shape `{ "message": string }`
     * (lihat handler global di bootstrap/app.php) sehingga skemanya konsisten.
     */
    private function addApiError(Operation $operation, int $code, string $description, bool $appendIfExists = false): void
    {
        $alreadyDocumented = collect($operation->responses)->first(
            fn ($response) => $response instanceof ScrambleResponse && $response->code === $code
        );

        if ($alreadyDocumented) {
            if (
                $appendIfExists
                && $alreadyDocumented instanceof ScrambleResponse
                && ! Str::contains($alreadyDocumented->description, $description)
            ) {
                $alreadyDocumented->setDescription(trim($alreadyDocumented->description."\n\n".$description));
            }

            return;
        }

        $schema = Schema::fromType(
            (new ObjectType)
                ->addProperty('message', (new StringType)->setDescription('Pesan error yang bisa ditampilkan ke pengguna.'))
                ->setRequired(['message'])
        );

        $operation->addResponse(
            ScrambleResponse::make($code)
                ->setDescription($description)
                ->setContent('application/json', $schema)
        );
    }
}
