<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kata Sandi Sementara ForestGuard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 32px;
            border: 1px solid #e2e8f0;
        }
        .header {
            margin-bottom: 24px;
        }
        .header h1 {
            color: #14532d;
            font-size: 20px;
            margin: 0;
        }
        .content {
            line-height: 1.6;
            font-size: 14px;
        }
        .box {
            background-color: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
            text-align: center;
        }
        .password {
            font-family: monospace;
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .button {
            display: inline-block;
            background-color: #166534;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 12px;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ForestGuard</h1>
        </div>
        <div class="content">
            <p>Halo <strong>{{ $user->name }}</strong>,</p>
            <p>Akun Anda telah dibuat oleh Admin di sistem <strong>ForestGuard</strong> dengan peran sebagai <strong>{{ $user->role?->label() ?? $user->role?->name }}</strong>.</p>
            <p>Berikut adalah kata sandi sementara untuk masuk ke akun Anda:</p>
            
            <div class="box">
                <div class="password">{{ $temporaryPassword }}</div>
            </div>

            <p>Silakan gunakan tombol di bawah untuk menuju ke halaman login:</p>
            <p style="text-align: center;">
                <a href="{{ route('login') }}" class="button" target="_blank">Masuk ke ForestGuard</a>
            </p>

            <p>Demi keamanan, Anda disarankan untuk segera memperbarui kata sandi Anda setelah berhasil masuk.</p>
        </div>
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem ForestGuard. Jangan balas email ini.</p>
        </div>
    </div>
</body>
</html>
