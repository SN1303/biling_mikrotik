<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lamtim_paket extends Model
{
  protected $fillable = [
    'idKategori',
    'kode',
    'nama',
    'price',
    'picture',
    'description',
    'isActive',
    'speed_limit',
    'ip_pool',
    'address_list',
    'is_burst',
    'burst_rate',
    'burst_threshold',
    'burst_time',
    'priority',
  ];

  public function kategori()
  {
    return $this->belongsTo(Lamtim_kategori::class, 'idKategori', 'id');
  }
}
