<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

  'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    'scheme' => 'https',
  ],

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'mapping' => [
    'latitude-server'   => env('LATITUDE'),
    'longitude-server'  => env('LONGITUDE'),
  ],

  'google' => [
    'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
  ],

  // NBI GenieACS (port 7557). URL kosong = fitur ONT mati.
  'genieacs' => [
    'url' => env('GENIEACS_URL', ''),
    'user' => env('GENIEACS_USER'),
    'pass' => env('GENIEACS_PASS'),
    'online_menit' => (int) env('GENIEACS_ONLINE_MENIT', 10),
    // Subdomain untuk remote web ONT (mis. ont.domainmu.com). Kosong = remote mati.
    'remote_host' => env('GENIEACS_REMOTE_HOST', ''),
  ],

  // Bot Telegram untuk notifikasi (kosong = notifikasi mati)
  'telegram' => [
    'token' => env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
  ],

  // Laporan PPPoE up/down dari skrip MikroTik
  'ppp_notif' => [
    'secret' => env('PPP_NOTIF_SECRET', ''),
    'url' => env('PPP_NOTIF_URL') ?: env('APP_URL'),
    'jeda_menit' => (int) env('PPP_NOTIF_JEDA_MENIT', 10),
    'ambang_massal' => (int) env('PPP_NOTIF_AMBANG_MASSAL', 3),
    'ulang_detik' => 30, // jeda sebelum mencoba lagi MikroTik yang gagal dihubungi
  ],

];
