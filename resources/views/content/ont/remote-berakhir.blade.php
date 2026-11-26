<!DOCTYPE html>
{{-- Halaman polos di dalam kotak remote ONT (tanpa layout billing; host remote tidak punya sesi billing) --}}
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $judul }}</title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 15px/1.5 system-ui, sans-serif; color: #384551; background: #f5f5f9; }
    main { max-width: 26rem; padding: 1.5rem; text-align: center; }
    h1 { font-size: 1.1rem; margin: 0 0 .5rem; }
    p { margin: 0; color: #6d7a89; }
    @media (prefers-color-scheme: dark) { body { background: #232333; color: #d0d0d0; } p { color: #a3a4bb; } }
  </style>
</head>
<body>
  <main>
    <h1>{{ $judul }}</h1>
    <p>{{ $pesan }}</p>
  </main>
</body>
</html>
