<?php

namespace App\Services;

class GoogleMapsService
{
    /**
     * Ekstrak Latitude dan Longitude dari input string (link Google Maps atau teks koordinat)
     * Mendukung:
     * - Teks koordinat langsung: "-5.123456, 105.123456"
     * - Link Google Maps standar (@lat,lng)
     * - Shortlink maps.app.goo.gl atau goo.gl/maps
     * - Query URL ?q=lat,lng, ?q=loc:lat,lng, &ll=lat,lng
     * - Format pencarian /search/lat,+lng atau /place/lat,+lng
     * - URL-encoded string (misal %2C untuk koma)
     * - Format internal Google Maps !3dlat!4dlng
     * 
     * @param string|null $input
     * @return array{lat: float|null, lng: float|null}
     */
    public static function extractCoordinates(?string $input): array
    {
        if (empty($input)) {
            return ['lat' => null, 'lng' => null];
        }

        $input = trim($input);

        // 1. Coba ekstraksi langsung dari teks/input sebelum network request
        $coords = self::parseCoordsFromString($input);
        if ($coords['lat'] !== null && $coords['lng'] !== null) {
            return $coords;
        }

        // 2. Ambil URL jika ada kata pengantar teks (misal dari pesan WhatsApp: "Lokasi: https://...")
        $targetUrl = $input;
        if (preg_match('/(https?:\/\/[^\s]+)/', $input, $urlMatches)) {
            $targetUrl = $urlMatches[1];
        }

        // 3. Jika merupakan link pendek (goo.gl / maps.app.goo.gl) atau URL Google Maps yang perlu di-expand
        if (str_contains($targetUrl, 'goo.gl') || str_contains($targetUrl, 'maps.app')) {
            $unshortened = self::unshortenUrl($targetUrl);

            // Coba ekstrak dari URL hasil redirect
            if (!empty($unshortened['final'])) {
                $coords = self::parseCoordsFromString($unshortened['final']);
                if ($coords['lat'] !== null && $coords['lng'] !== null) {
                    return $coords;
                }
            }

            // Coba ekstrak dari body response jika di URL belum ada koordinat eksplisit
            if (!empty($unshortened['body'])) {
                $coords = self::parseCoordsFromString($unshortened['body']);
                if ($coords['lat'] !== null && $coords['lng'] !== null) {
                    return $coords;
                }
            }
        } else {
            // Coba unshorten/follow redirects untuk URL umum lainnya
            if (filter_var($targetUrl, FILTER_VALIDATE_URL)) {
                $unshortened = self::unshortenUrl($targetUrl);
                if (!empty($unshortened['final'])) {
                    $coords = self::parseCoordsFromString($unshortened['final']);
                    if ($coords['lat'] !== null && $coords['lng'] !== null) {
                        return $coords;
                    }
                }
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Parsing pola koordinat dari string atau URL
     */
    public static function parseCoordsFromString(string $str): array
    {
        // Decode URL entities dan %2C menjadi koma
        $decoded = urldecode($str);

        // Pola 1: Format langsung angka lat,lng (misal "-5.123456, 105.123456" atau "-5.123456,+105.123456")
        if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*\+?(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Pola 2: Format @lat,lng dalam URL (misal: @-5.123456,105.123456,17z)
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Pola 3: Format /search/lat,+lng atau /place/lat,+lng
        if (preg_match('/(?:search|place)\/(-?\d{1,2}\.\d+)(?:,|\+|\s)+\+?(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Pola 4: Format query string ?q=lat,lng atau &ll=lat,lng atau ?q=loc:lat,lng
        if (preg_match('/(?:[?&](?:q|ll|destination)=(?:loc:)?)(-?\d{1,2}\.\d+)(?:,|\+|\s)+(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // Pola 5: Format data=!3dlat!4dlng (Internal Google Maps place URL)
        if (preg_match('/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Unshorten URL menggunakan cURL dengan penanganan follow redirects & SSL bypass
     */
    private static function unshortenUrl(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_COOKIEJAR, '');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');

        $body = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: '';
        curl_close($ch);

        return [
            'final' => $finalUrl,
            'body'  => is_string($body) ? $body : '',
        ];
    }

    /**
     * Validasi rentang koordinat bumi
     */
    private static function isValidCoordinate(float $lat, float $lng): bool
    {
        return ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0);
    }
}
