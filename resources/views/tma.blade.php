<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #0b0f7e; color: #fff; }
        .box { text-align: center; padding: 24px; }
        .dot { width: 14px; height: 14px; background: #f7941d; transform: rotate(45deg); margin: 0 auto 16px; }
    </style>
</head>
<body>
<div class="box">
    <div class="dot"></div>
    <p id="status">{{ __('Signing in…') }}</p>
</div>
<script>
    const tg = window.Telegram?.WebApp;
    tg?.ready();
    tg?.expand();
    fetch(@json(route('tma.login')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        credentials: 'same-origin',
        body: JSON.stringify({ init_data: tg?.initData ?? '' }),
    }).then(async (response) => {
        const data = await response.json();
        if (data.redirect) { window.location.href = data.redirect; return; }
        document.getElementById('status').textContent = data.message ?? 'Error';
    }).catch(() => { document.getElementById('status').textContent = 'Error'; });
</script>
</body>
</html>
