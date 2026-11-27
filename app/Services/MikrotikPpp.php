<?php

namespace App\Services;

/**
 * Akses PPP MikroTik yang dipakai notifikasi Telegram, dibungkus supaya bisa diganti
 * tiruan di tes (app()->instance). Kegagalan koneksi dilempar sebagai \RuntimeException.
 */
class MikrotikPpp
{
  /** @return list<string> username yang sedang tersambung */
  public function aktif(int $idMikrotik): array
  {
    return array_values(array_filter(array_map(fn($r) => $r['name'] ?? null, $this->baca($idMikrotik, '/ppp/active/print'))));
  }

  /** @return array<string,bool> username => secret dinonaktifkan */
  public function secretNonaktif(int $idMikrotik): array
  {
    $hasil = [];
    foreach ($this->baca($idMikrotik, '/ppp/secret/print') as $r) {
      if (isset($r['name'])) {
        $hasil[$r['name']] = ($r['disabled'] ?? 'false') === 'true';
      }
    }
    return $hasil;
  }

  /** @return list<array{id:string,name:string,on-up:string,on-down:string}> */
  public function profil(int $idMikrotik): array
  {
    return array_map(fn($r) => [
      'id' => $r['.id'], 'name' => $r['name'] ?? '', 'on-up' => $r['on-up'] ?? '', 'on-down' => $r['on-down'] ?? '',
    ], $this->baca($idMikrotik, '/ppp/profile/print'));
  }

  public function setSkripProfil(int $idMikrotik, string $id, string $onUp, string $onDown): void
  {
    try {
      $q = (new \RouterOS\Query('/ppp/profile/set'))->equal('.id', $id)->equal('on-up', $onUp)->equal('on-down', $onDown);
      MikrotikMulti::connectionMikrotik($idMikrotik)->query($q)->read();
    } catch (\Throwable $e) {
      throw new \RuntimeException('Gagal mengubah PPP Profile: ' . $e->getMessage(), 0, $e);
    }
  }

  private function baca(int $idMikrotik, string $perintah): array
  {
    try {
      return MikrotikMulti::commandApi($idMikrotik, $perintah);
    } catch (\Throwable $e) {
      throw new \RuntimeException('MikroTik tidak terjangkau: ' . $e->getMessage(), 0, $e);
    }
  }
}
