<?php

namespace App\Exceptions;

use RuntimeException;

/** NBI GenieACS tidak bisa dihubungi (koneksi gagal, timeout, atau respons 5xx). */
class GenieAcsTidakTerjangkau extends RuntimeException
{
}
