<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santap API</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.5);
            --card-bg: rgba(30, 41, 59, 0.7);
            --border: rgba(255, 255, 255, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(56, 189, 248, 0.15), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15), transparent 25%);
        }
        .container {
            max-width: 600px;
            width: 90%;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 8px; background: linear-gradient(to right, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header p { color: var(--text-secondary); font-size: 1.1rem; }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid rgba(16, 185, 129, 0.2);
            margin-bottom: 20px;
        }
        .status-dot { width: 8px; height: 8px; background-color: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .links-grid { display: grid; gap: 16px; margin-top: 20px; }
        .link-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            padding: 16px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        .link-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .link-title { font-weight: 600; font-size: 1.1rem; }
        .link-desc { color: var(--text-secondary); font-size: 0.875rem; margin-top: 4px; }
        .link-icon { color: var(--text-secondary); font-size: 1.2rem; transition: transform 0.3s ease, color 0.3s ease; }
        .link-card:hover .link-icon { color: var(--accent); transform: translateX(4px); }
        
        .footer { text-align: center; margin-top: 30px; font-size: 0.875rem; color: var(--text-secondary); }
        .health-link { color: var(--text-secondary); text-decoration: none; transition: color 0.3s; border-bottom: 1px dashed var(--text-secondary); padding-bottom: 2px;}
        .health-link:hover { color: var(--accent); border-color: var(--accent); }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="status-badge">
            <div class="status-dot"></div>
            Status: Online
        </div>
        <h1>Santap API</h1>
        <p>API Version: <strong>v1</strong></p>
    </div>

    <div class="links-grid">
        <a href="/docs/api" class="link-card">
            <div>
                <div class="link-title">Main Hub Documentation</div>
                <div class="link-desc">Pilih dokumentasi API yang tersedia</div>
            </div>
            <div class="link-icon">→</div>
        </a>
        <a href="/docs/api/mobile" class="link-card">
            <div>
                <div class="link-title">Mobile & Staff API</div>
                <div class="link-desc">Endpoint untuk kasir, dapur, dan manajer</div>
            </div>
            <div class="link-icon">→</div>
        </a>
        <a href="/docs/api/web-customer" class="link-card">
            <div>
                <div class="link-title">Customer Web API</div>
                <div class="link-desc">Endpoint publik untuk pemesanan di meja</div>
            </div>
            <div class="link-icon">→</div>
        </a>
    </div>

    <div class="footer">
        Cek <a href="/health" class="health-link">/health</a> endpoint untuk status sistem server.
    </div>
</div>

</body>
</html>
