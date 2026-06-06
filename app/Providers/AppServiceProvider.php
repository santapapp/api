<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response as ScrambleResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OrganizationContext::class);
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
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

                    **Open bill & QRIS kasir/owner:**
                    - Endpoint aktif memakai `/v1/cashier/orders`. Role `kitchen` tidak boleh mutasi item/payment open bill.
                    - `POST /v1/cashier/orders/{id}/pay-qris` mengunci order terkait saja. Jika order yang sama masih punya QRIS pending aktif, API mengembalikan QRIS existing; order lain tetap bisa membuat QRIS masing-masing.
                    - QRIS hanya bisa dibuat saat total > 0. Selama QRIS pending/paid, item tidak bisa ditambah, diubah, atau dihapus. Cancel QRIS atau tunggu expired lalu regenerate.
                    - Expired adalah derived state dari `payment_expires_at` dan/atau status provider. Attempt lama disimpan ringkas di `orders.metadata.qris_attempts[]`; attempt aktif di `orders.metadata.qris_active`.
                    - Payload item kanonik memakai `selected_options[{group_id, option_id}]`; `selected_variants[{variant_group_id, variant_id}]` tetap didukung untuk backward compatibility.

                    **Format error:** semua error mengembalikan JSON `{ "message": string }`; error validasi (422) menambahkan `{ "errors": { field: [pesan] } }`.
                    MD,
                'version'     => '1.0.0',
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
                Str::startsWith($uri, 'v1/auth')           => 'Mobile Auth',
                Str::startsWith($uri, 'v1/organizations')  => 'Mobile Organization',
                Str::startsWith($uri, 'v1/menus')          => 'Mobile Menu',
                Str::startsWith($uri, 'v1/dining-tables')  => 'Mobile Table',
                Str::startsWith($uri, 'v1/kitchen')        => 'Mobile Kitchen',
                Str::startsWith($uri, 'v1/cashier')        => 'Mobile Cashier Order',
                default                                    => null,
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

                    **Alur pembayaran & polling (QRIS):**
                    - Status pembayaran disinkronkan dari gateway Sekeco/Midtrans — *gateway adalah source of truth*. Frontend cukup membaca status dari API ini, bukan memutuskan sendiri.
                    - Order pending punya `payment_expires_at` (now + 15 menit). Gunakan bersama `server_time` untuk countdown yang akurat (hitung offset waktu server↔klien).
                    - Polling status table order: `GET /v1/customer/orders/{order}/payment-status`. Endpoint ini cek provider lebih dulu, baru memutuskan paid/expired — idempotent, dan bisa merekonsiliasi order yang terlanjur cancelled jika ternyata sudah dibayar.
                    - Timeout lokal TIDAK pernah membatalkan order sebelum sinkronisasi final ke gateway.
                    - Open bill memakai service QRIS yang sama dengan cashier: duplicate QRIS dibatasi per order, bukan per organisasi/meja/provider. Jika order yang sama masih pending aktif, endpoint mengembalikan QRIS existing.
                    - Selama QRIS open bill pending/paid, tambah item ditolak. Cancel QRIS atau tunggu expired lalu regenerate.
                    - Payload item kanonik memakai `selected_options[{group_id, option_id}]`; `selected_variants[{variant_group_id, variant_id}]` tetap didukung untuk backward compatibility.
                    - Attempt QRIS open bill disimpan ringkas di `orders.metadata.qris_active` dan `orders.metadata.qris_attempts[]`, tanpa raw provider response penuh.
                    MD,
                'version'     => '1.0.0',
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
                $this->addApiError($operation, 403, 'Sesi open bill tidak valid / sudah berakhir, atau waktu pembayaran sudah habis (response sertakan `status` & `can_retry`).');
            } elseif ($hasPathParams) {
                $this->addApiError($operation, 404, 'Order atau resource tidak ditemukan.');
            }

            // Tag eksplisit agar dokumentasi memisahkan flow table order publik
            // dari flow open bill yang berbasis token.
            $uri = $routeInfo->route->uri();
            $tag = match (true) {
                $hasCustomerToken                               => 'Customer Open Bill',
                Str::contains($uri, 'payment-status')           => 'Customer Payment',
                Str::contains($uri, 'v1/customer/orders')       => 'Customer Order Tracking',
                Str::contains($uri, 'v1/customer/order')        => 'Customer Table Order',
                Str::contains($uri, 'v1/customer/menu')         => 'Customer Menu',
                Str::contains($uri, 'v1/customer/table')        => 'Customer Table',
                Str::contains($uri, 'v1/customer/organization') => 'Customer Organization',
                default                                         => null,
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

                    **Autentikasi staff/owner:** `Authorization: Bearer <token>` (dari `POST /v1/auth/login`); endpoint scoped organisasi wajib menyertakan header `X-Org-ID`.

                    **Autentikasi pelanggan (open bill):** header `X-Public-Token` (dari scan QR meja). Endpoint table order publik tidak butuh token.

                    **Upload media (`multipart/form-data`):** `POST /v1/auth/profile/avatar`, `POST /v1/menus/{id}/image`, `POST /v1/organizations/current/logo`, `POST /v1/organizations/current/banner` — field file jpeg/png/webp maks 2 MB; gambar lama otomatis diganti, response mengembalikan URL gambar terbaru.

                    **Open bill & QRIS:** flow staff aktif berada di `/v1/cashier/orders`, sedangkan flow pelanggan open bill berada di `/v1/customer/order/*` dengan `X-Public-Token`. Duplicate QRIS dibatasi per order saja. Saat order yang sama masih punya QRIS pending aktif, API mengembalikan QRIS existing; order lain tetap bisa membuat QRIS. Attempt QRIS lama diarsipkan ringkas ke `orders.metadata.qris_attempts[]`.

                    **Payload item:** gunakan `selected_options[{group_id, option_id}]` untuk variant/addon berdasarkan tree menu organisasi yang sama. `selected_variants[{variant_group_id, variant_id}]` masih diterima sebagai legacy payload.

                    **Format error:** JSON `{ "message": string }`; validasi (422) menambah `{ "errors": { field: [pesan] } }`.
                    MD,
                'version'     => '1.0.0',
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
            $uri        = $routeInfo->route->uri();

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
                $this->addApiError($operation, 403, 'Sesi open bill tidak valid / sudah berakhir, atau waktu pembayaran sudah habis (response sertakan `status` & `can_retry`).');
            } elseif ($hasPathParams) {
                $this->addApiError($operation, 404, 'Resource tidak ditemukan.');
            }

            // ── Tag: pisahkan flow customer dari staff agar dokumentasi rapi ──
            if (Str::startsWith($uri, 'v1/customer')) {
                $tag = match (true) {
                    $hasCustomerToken                               => 'Customer Open Bill',
                    Str::contains($uri, 'payment-status')           => 'Customer Payment',
                    Str::contains($uri, 'v1/customer/orders')       => 'Customer Order Tracking',
                    Str::contains($uri, 'v1/customer/order')        => 'Customer Table Order',
                    Str::contains($uri, 'v1/customer/menu')         => 'Customer Menu',
                    Str::contains($uri, 'v1/customer/table')        => 'Customer Table',
                    Str::contains($uri, 'v1/customer/organization') => 'Customer Organization',
                    default                                         => null,
                };
            } else {
                $tag = match (true) {
                    Str::contains($uri, ['pay-cash', 'pay-qris', 'qris-status', 'qris-cancel']) => 'Staff Payment',
                    Str::startsWith($uri, 'v1/auth')           => 'Staff Auth',
                    Str::startsWith($uri, 'v1/organizations')  => 'Staff Organization',
                    Str::startsWith($uri, 'v1/menus')          => 'Staff Menu',
                    Str::startsWith($uri, 'v1/dining-tables')  => 'Staff Table',
                    Str::startsWith($uri, 'v1/kitchen')        => 'Staff Kitchen',
                    Str::startsWith($uri, 'v1/cashier')        => 'Staff Cashier Order',
                    default                                    => null,
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
            $uri === 'v1/cashier/orders' && $method === 'get' => [
                'List cashier/open bill orders',
                'Mengambil order hari ini dalam organisasi aktif. Filter `order_type=open_bill&bill_status=open` untuk daftar open bill aktif.',
                null,
            ],
            $uri === 'v1/cashier/orders' && $method === 'post' => [
                'Create cashier/open bill order',
                'Membuat order kasir atau open bill. Untuk open bill, meja harus milik organisasi aktif dan tidak boleh sudah memiliki open bill aktif.',
                'Meja tidak valid atau sudah memiliki open bill aktif.',
            ],
            $uri === 'v1/cashier/orders/{id}' && $method === 'get' => [
                'Show cashier/open bill order',
                'Mengambil detail order beserta item dan metadata QRIS ringkas (`qris.active`, `qris.attempts_count`, `qris.is_expired`).',
                null,
            ],
            $uri === 'v1/cashier/orders/{id}/items' && $method === 'post' => [
                'Add items to open bill',
                'Menambah item ke order. Gunakan `selected_options[{group_id, option_id}]` untuk variant/addon; `selected_variants` masih diterima sebagai legacy payload. Menu, group, option, dan meja harus berada dalam organisasi yang sama.',
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
                'Menutup bill tanpa memaksa status kitchen menjadi completed. Gunakan setelah alur pembayaran/kitchen sesuai kebutuhan operasional.',
                'Order sudah ditutup atau state order tidak valid.',
            ],
            $uri === 'v1/cashier/orders/{id}/cancel' && $method === 'post' => [
                'Cancel order',
                'Membatalkan order yang belum paid/completed. Jika ada QRIS pending, sistem mencoba cancel provider dan mengarsipkan attempt sebelum order dicancel.',
                'Order sudah paid/completed/cancelled atau alasan cancel tidak valid.',
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
                'Mengambil open bill aktif dari `X-Public-Token`, termasuk item dan metadata QRIS ringkas.',
                null,
            ],
            $uri === 'v1/customer/order/items' && $method === 'post' => [
                'Add items to active open bill',
                'Menambah item ke open bill aktif. Gunakan `selected_options[{group_id, option_id}]` untuk variant/addon; `selected_variants` tetap diterima sebagai legacy payload. Pending/paid QRIS memblokir tambah item.',
                'Item tidak bisa ditambahkan saat QRIS pending aktif, payment paid, bill closed/cancelled, atau menu/options tidak sesuai organisasi/order.',
            ],
            $uri === 'v1/customer/order/pay-qris' && $method === 'post' => [
                'Create or reuse open bill QRIS',
                'Membuat QRIS untuk open bill aktif. Duplicate guard berlaku per order: jika open bill ini masih punya QRIS pending aktif, API mengembalikan QRIS existing. QRIS baru bisa dibuat setelah cancelled, failed, atau expired yang sudah disinkronkan.',
                'QRIS tidak bisa dibuat saat total 0, order sudah paid/closed/cancelled, atau order tidak punya item aktif.',
            ],
            $uri === 'v1/customer/order/qris-status' && $method === 'get' => [
                'Poll open bill QRIS status',
                'Menyinkronkan status QRIS open bill ke provider. Jika paid, bill ditutup; jika expired/cancelled/failed, attempt aktif diarsipkan agar bisa regenerate.',
                null,
            ],
            $uri === 'v1/customer/order/qris-cancel' && $method === 'delete' => [
                'Cancel active open bill QRIS',
                'Membatalkan QRIS pending open bill, mengarsipkan attempt aktif ke `orders.metadata.qris_attempts[]`, lalu membuka jalan untuk edit item atau regenerate QRIS.',
                'QRIS tidak bisa dibatalkan jika tidak ada attempt pending aktif atau provider menolak cancel.',
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
