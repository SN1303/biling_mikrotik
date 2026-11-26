<?php

namespace App\Http\Middleware;

use App\Services\RemoteOntService;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host remote (GENIEACS_REMOTE_HOST) seluruhnya milik web ONT: setiap request di host itu
 * diteruskan ke http://<ip ONT> lewat server billing, tanpa routing, sesi, atau CSRF billing.
 * Host lain lewat tanpa diubah. IP berasal dari sesi remote (lihat RemoteOntService).
 */
class OntRemoteProxy
{
  private const COOKIE = 'ont_remote';
  // Authorization: untuk ONT yang login lewat HTTP Basic/Digest (billing tidak memakainya di host ini)
  private const HEADER_DITERUSKAN = ['Content-Type', 'User-Agent', 'Accept', 'Accept-Language', 'X-Requested-With', 'Authorization'];
  private const HEADER_DIBUANG = ['x-frame-options', 'content-security-policy', 'transfer-encoding', 'connection',
    'content-encoding', 'content-length', 'keep-alive', 'set-cookie'];

  public function __construct(private RemoteOntService $remote)
  {
  }

  public function handle(Request $request, Closure $next): Response
  {
    $host = strtolower((string) config('services.genieacs.remote_host'));
    if ($host === '' || strtolower($request->getHost()) !== $host) {
      return $next($request);
    }

    if ($request->getPathInfo() === '/_remote/mulai') {
      return $this->mulai($request);
    }

    try {
      $id = Crypt::decryptString((string) $request->cookies->get(self::COOKIE));
    } catch (\Throwable) {
      $id = null;
    }
    $sesi = $this->remote->sesi($id);
    if (!$sesi) {
      return $this->halaman('Sesi remote berakhir', 'Buka lagi dari halaman ONT pelanggan di billing.', 403);
    }

    return $this->teruskan($request, $sesi['ip']);
  }

  private function mulai(Request $request): Response
  {
    $tiket = $request->query('tiket');
    $id = is_string($tiket) ? $this->remote->tukarTiket($tiket) : null;
    if (!$id) {
      return $this->halaman('Sesi remote berakhir', 'Tautan remote sudah kedaluwarsa. Buka lagi dari halaman ONT pelanggan di billing.', 403);
    }
    // Strict: tetap terkirim di iframe dari billing (satu situs), tapi tidak dari tautan situs lain (CSRF ke ONT).
    // Secure juga bila HTTPS-nya diakhiri Cloudflare (PHP sendiri melihat http).
    $aman = $request->isSecure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    return redirect('/')->withCookie(new Cookie(self::COOKIE, Crypt::encryptString($id), 0, '/', null,
      $aman, true, false, Cookie::SAMESITE_STRICT));
  }

  private function teruskan(Request $request, string $ip): Response
  {
    $asal = "http://$ip";
    $header = array_filter(array_map(fn($h) => $request->headers->get($h), array_combine(self::HEADER_DITERUSKAN, self::HEADER_DITERUSKAN)));
    // Header Cookie mentah (bukan $_COOKIE yang sudah di-url-decode PHP), tanpa cookie remote
    $cookieOnt = collect(explode(';', (string) $request->headers->get('Cookie')))->map(fn($c) => trim($c))
      ->reject(fn($c) => $c === '' || str_starts_with($c, self::COOKIE . '='))->implode('; ');
    if ($cookieOnt !== '') {
      $header['Cookie'] = $cookieOnt;
    }
    if ($referer = $request->headers->get('Referer')) {
      $header['Referer'] = $asal . (parse_url($referer, PHP_URL_PATH) ?? '/');
    }

    $http = Http::withHeaders($header)->withoutRedirecting()->timeout(15)->withOptions(['decode_content' => true]);
    if (($isi = $request->getContent()) !== '') {
      $http = $http->withBody($isi, $request->headers->get('Content-Type', 'application/x-www-form-urlencoded'));
    }

    // Host tujuan selalu IP sesi; dari request hanya path + query mentah yang diambil
    $q = (string) $request->server->get('QUERY_STRING');
    $tujuan = $asal . '/' . ltrim($request->getPathInfo(), '/') . ($q !== '' ? "?$q" : '');

    try {
      $ont = $http->send($request->getMethod(), $tujuan);
    } catch (ConnectionException) {
      return $this->halaman('ONT tidak bisa dihubungi', "Server billing tidak mendapat jawaban dari $ip. ONT mungkin mati, atau web-nya tidak dibuka di sisi WAN.", 502);
    }

    $balasan = response($ont->body(), $ont->status());
    foreach ($ont->headers() as $nama => $nilai) {
      if (in_array(strtolower($nama), self::HEADER_DIBUANG, true)) {
        continue;
      }
      if (strtolower($nama) === 'location') {
        // Alamat absolut ke IP ONT dijadikan relatif, supaya tetap di host remote
        $nilai = array_map(fn($v) => preg_replace('#^https?://' . preg_quote($ip, '#') . '(:\d+)?(?=[/?\#]|$)#i', '', $v) ?: '/', $nilai);
      }
      $balasan->headers->set($nama, $nilai);
    }
    foreach ($ont->headers() as $nama => $nilai) {
      if (strtolower($nama) !== 'set-cookie') {
        continue;
      }
      foreach ($nilai as $c) {
        try {
          $cookie = Cookie::fromString(preg_replace('/;\s*domain=[^;]*/i', '', $c));
        } catch (\InvalidArgumentException) {
          continue; // Set-Cookie rusak dari ONT: lewati, jangan jadi error 500
        }
        if ($cookie->getName() !== self::COOKIE) { // ONT tidak boleh menimpa cookie sesi remote
          $balasan->headers->setCookie($cookie);
        }
      }
    }
    return $balasan;
  }

  private function halaman(string $judul, string $pesan, int $status): Response
  {
    return response()->view('content.ont.remote-berakhir', compact('judul', 'pesan'), $status);
  }
}
