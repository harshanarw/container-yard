<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass Link Expired</title>
    <style>
        body {
            margin: 0; min-height: 100vh; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px;
        }
        .card {
            background: #fff; max-width: 420px; width: 100%; border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0,0,0,.12); padding: 32px 26px; text-align: center;
        }
        .icon { font-size: 46px; line-height: 1; margin-bottom: 12px; }
        h1 { font-size: 18px; margin: 0 0 8px; color: #b45309; }
        p { font-size: 14px; color: #475569; line-height: 1.5; margin: 0 0 6px; }
        .mono { font-family: monospace; font-weight: 700; color: #0f172a; }
        .foot { margin-top: 18px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏰</div>
        <h1>Gate Pass Link Expired</h1>
        <p>This gate pass link is no longer active.</p>
        @if(isset($movement) && $movement->container_no)
        <p>Container <span class="mono">{{ $movement->container_no }}</span></p>
        @endif
        <p>Please ask the yard office to send you a fresh link.</p>
        <div class="foot">{{ optional(\App\Models\CompanySetting::current())->company_name ?? 'Container Yard' }}</div>
    </div>
</body>
</html>
