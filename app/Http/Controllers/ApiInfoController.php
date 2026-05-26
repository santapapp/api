<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApiInfoController extends Controller
{
    /**
     * Root landing page — informasi API Santap.
     */
    public function index()
    {
        return view('api-landing');
    }

    /**
     * Health check — cek koneksi DB dan cache.
     */
    public function health(): JsonResponse
    {
        $checks = [];
        $allOk  = true;

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'error';
            $allOk = false;
        }

        // Cache check
        try {
            cache()->put('health_check', 1, 5);
            $checks['cache'] = 'ok';
        } catch (\Throwable) {
            $checks['cache'] = 'error';
            $allOk = false;
        }

        return response()->json([
            'status'    => $allOk ? 'ok' : 'degraded',
            'app'       => 'Santap API',
            'version'   => 'v1',
            'checks'    => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }
}
