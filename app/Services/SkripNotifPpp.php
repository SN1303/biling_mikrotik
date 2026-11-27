<?php

namespace App\Services;

/**
 * Blok skrip RouterOS untuk on-up/on-down PPP Profile: melapor ke billing
 * (POST /api/mikrotik/ppp-event). Blok diapit penanda, supaya bisa dipasang ulang
 * atau dicabut tanpa menyentuh skrip lain di profile yang sama.
 */
class SkripNotifPpp
{
  public const MULAI = '# mryes-notif:mulai';
  public const SELESAI = '# mryes-notif:selesai';

  public function __construct(private MikrotikPpp $ppp)
  {
  }

  /** @param 'up'|'down' $event */
  public function blok(int $idMikrotik, string $event): string
  {
    $url = $this->routerOs(rtrim((string) config('services.ppp_notif.url'), '/') . '/api/mikrotik/ppp-event');
    $token = $this->routerOs((string) config('services.ppp_notif.secret'));
    return self::MULAI . "\n"
      . ":do { /tool fetch url=\"$url\" http-method=post http-header-field=\"X-Notif-Token: $token\" "
      . "http-data=(\"m=$idMikrotik&e=$event&u=\" . \$user) output=none } on-error={}\n"
      . self::SELESAI;
  }

  /** Tambahkan blok di akhir skrip; blok lama (bila ada) dibuang dulu supaya tidak ganda. */
  public function sisipkan(string $skripLama, string $blok): string
  {
    $sisa = rtrim($this->buang($skripLama));
    return $sisa === '' ? $blok : "$sisa\n$blok";
  }

  /** Buang blok bertanda saja; isi lain dibiarkan. */
  public function buang(string $skrip): string
  {
    $pola = '/\r?\n?' . preg_quote(self::MULAI, '/') . '.*?' . preg_quote(self::SELESAI, '/') . '\r?\n?/s';
    return trim(preg_replace($pola, "\n", $skrip), "\r\n");
  }

  /** @return int jumlah profile yang dipasangi. @throws \RuntimeException MikroTik tidak terjangkau */
  public function pasang(int $idMikrotik): int
  {
    $profil = $this->ppp->profil($idMikrotik);
    foreach ($profil as $p) {
      $this->ppp->setSkripProfil($idMikrotik, $p['id'],
        $this->sisipkan($p['on-up'], $this->blok($idMikrotik, 'up')),
        $this->sisipkan($p['on-down'], $this->blok($idMikrotik, 'down')));
    }
    return count($profil);
  }

  /** @return int jumlah profile yang diperiksa. @throws \RuntimeException MikroTik tidak terjangkau */
  public function cabut(int $idMikrotik): int
  {
    $profil = $this->ppp->profil($idMikrotik);
    foreach ($profil as $p) {
      $this->ppp->setSkripProfil($idMikrotik, $p['id'], $this->buang($p['on-up']), $this->buang($p['on-down']));
    }
    return count($profil);
  }

  /** Escape untuk string RouterOS dalam tanda kutip ganda. */
  private function routerOs(string $teks): string
  {
    return str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $teks);
  }
}
