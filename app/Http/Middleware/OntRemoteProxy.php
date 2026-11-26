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
  private const HEADER_DITERUSKAN = ['Content-Type', 'User-Agent', 'Accept', 'Accept-Language', 'X-Requested-With'];
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
    $id = $this->remote->tukarTiket((string) $request->query('tiket'));
    if (!$id) {
      return $this->halaman('Sesi remote berakhir', 'Tautan remote sudah kedaluwarsa. Buka lagi dari halaman ONT pelanggan di billing.', 403);
    }
    return redirect('/')->withCookie(new Cookie(self::COOKIE, Crypt::encryptString($id), 0, '/', null,
      $request->isSecure(), true, false, Cookie::SAMESITE_LAX));
  }

  private function teruskan(Request $request, string $ip): Response
  {
    $asal = "http://$ip";
    $header = array_filter(array_map(fn($h) => $request->headers->get($h), array_combine(self::HEADER_DITERUSKAN, self::HEADER_DITERUSKAN)));
    $cookieOnt = collect($request->cookies->all())->except(self::COOKIE)
      ->map(fn($v, $k) => "$k=$v")->implode('; ');
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

    // Hanya path + query yang diteruskan: request berbentuk absolut ("GET http://lain/…")
    // tidak boleh bisa mengganti host tujuan
    $uri = $request->getRequestUri();
    $tujuan = $asal . '/' . ltrim((string) parse_url($uri, PHP_URL_PATH), '/')
      . (($q = parse_url($uri, PHP_URL_QUERY)) !== null ? "?$q" : '');

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
        $nilai = array_map(fn($v) => preg_replace('#^https?://' . preg_quote($ip, '#') . '(:\d+)?#i', '', $v) ?: '/', $nilai);
      }
      $balasan->headers->set($nama, $nilai);
    }
    foreach ($ont->headers()['Set-Cookie'] ?? $ont->headers()['set-cookie'] ?? [] as $c) {
      $balasan->headers->setCookie(Cookie::fromString(preg_replace('/;\s*domain=[^;]*/i', '', $c)));
    }
    return $balasan;
  }

  private function halaman(string $judul, string $pesan, int $status): Response
  {
    return response()->view('content.ont.remote-berakhir', compact('judul', 'pesan'), $status);
  }
}
