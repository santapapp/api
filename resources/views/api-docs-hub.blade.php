<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santap API Documentation Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0f19;
            --bg-card: rgba(17, 24, 39, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-purple: #8b5cf6;
            --accent-cyan: #06b6d4;
            --accent-purple-glow: rgba(139, 92, 246, 0.15);
            --accent-cyan-glow: rgba(6, 182, 212, 0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Abstract Background Gradients */
        body::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(0,0,0,0) 70%);
            top: -200px;
            left: -200px;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.12) 0%, rgba(0,0,0,0) 70%);
            bottom: -200px;
            right: -200px;
            z-index: 0;
        }

        .container {
            max-width: 1000px;
            width: 90%;
            padding: 40px 20px;
            z-index: 10;
            position: relative;
            text-align: center;
        }

        header {
            margin-bottom: 50px;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 40%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.03em;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 300;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: left;
            text-decoration: none;
            color: inherit;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 380px;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(800px circle at var(--x, 0px) var(--y, 0px), rgba(255,255,255,0.06), transparent 40%);
            z-index: 1;
            pointer-events: none;
        }

        .card.purple {
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .card.purple:hover {
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 40px var(--accent-purple-glow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transform: translateY(-8px);
        }

        .card.cyan:hover {
            border-color: rgba(6, 182, 212, 0.4);
            box-shadow: 0 10px 40px var(--accent-cyan-glow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transform: translateY(-8px);
        }

        .icon-container {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            font-size: 1.75rem;
            position: relative;
        }

        .purple .icon-container {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.25);
        }

        .cyan .icon-container {
            background: rgba(6, 182, 212, 0.15);
            color: #22d3ee;
            border: 1px solid rgba(6, 182, 212, 0.25);
        }

        .card-content {
            margin-bottom: 24px;
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: -0.01em;
        }

        .purple .card-title {
            color: #ddd6fe;
        }

        .cyan .card-title {
            color: #cffafe;
        }

        .card-desc {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            font-weight: 300;
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 100px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
        }

        .purple .badge {
            color: #c4b5fd;
            border-color: rgba(139, 92, 246, 0.15);
            background: rgba(139, 92, 246, 0.05);
        }

        .cyan .badge {
            color: #67e8f9;
            border-color: rgba(6, 182, 212, 0.15);
            background: rgba(6, 182, 212, 0.05);
        }

        .btn-link {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            margin-top: auto;
            transition: gap 0.2s;
        }

        .purple .btn-link {
            color: #a78bfa;
        }

        .purple:hover .btn-link {
            gap: 12px;
        }

        .cyan .btn-link {
            color: #22d3ee;
        }

        .cyan:hover .btn-link {
            gap: 12px;
        }

        footer {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.3);
            margin-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 24px;
            width: 100%;
            max-width: 600px;
        }

        footer a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            transition: color 0.2s;
        }

        footer a:hover {
            color: var(--text-primary);
        }

        @media (max-width: 640px) {
            h1 {
                font-size: 2.25rem;
            }
            .grid {
                grid-template-columns: 1fr;
            }
            .card {
                height: auto;
                min-height: 320px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>Santap API Platform</h1>
            <p class="subtitle">Pusat dokumentasi API untuk sistem POS Kasir Santap. Pilih dokumentasi yang sesuai dengan kebutuhan pengembangan Anda.</p>
        </header>

        <div class="grid">
            <!-- Mobile API Card -->
            <a href="/docs/api/mobile" class="card purple">
                <div>
                    <div class="icon-container">
                        <!-- Mobile Icon SVG -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                    </div>
                    <div class="card-content">
                        <h2 class="card-title">Mobile POS & Staff API</h2>
                        <p class="card-desc">Dokumentasi API untuk aplikasi kasir (cashier), dapur (kitchen), dan pemilik (owner). Mengatur manajemen meja, pemesanan kasir, menu, antrean dapur, dan otentikasi staff.</p>
                        <div class="badge-list">
                            <span class="badge">Bearer Token</span>
                            <span class="badge">X-Org-ID</span>
                            <span class="badge">Staff Roles</span>
                        </div>
                    </div>
                </div>
                <div class="btn-link">
                    Buka Dokumentasi Staff
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
            </a>

            <!-- Customer Web API Card -->
            <a href="/docs/api/web-customer" class="card cyan">
                <div>
                    <div class="icon-container">
                        <!-- Globe/Web Icon SVG -->
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    </div>
                    <div class="card-content">
                        <h2 class="card-title">Customer Web API</h2>
                        <p class="card-desc">Dokumentasi API publik tanpa login untuk pelanggan di meja makan. Mengatur pemindaian kode QR meja, pengambilan menu restoran, pemesanan mandiri, dan pembayaran QRIS.</p>
                        <div class="badge-list">
                            <span class="badge">X-Public-Token</span>
                            <span class="badge">Tanpa Login</span>
                            <span class="badge">Public QRIS</span>
                        </div>
                    </div>
                </div>
                <div class="btn-link">
                    Buka Dokumentasi Customer
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
            </a>
        </div>

        <footer>
            <p>&copy; 2026 Santap POS. Powered by <a href="https://scramble.dedoc.co/" target="_blank" rel="noopener noreferrer">Scramble</a> & Laravel.</p>
        </footer>
    </div>

    <script>
        // Hover micro-animation script for card mouse movements (spotlight effect)
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--x', `${x}px`);
                card.style.setProperty('--y', `${y}px`);
            });
        });
    </script>
</body>
</html>
