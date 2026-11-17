<?php

namespace App\Services;

class GoogleMapsService
{
    /**
     * Ekstrak Latitude dan Longitude dari input string (link Google Maps atau teks koordinat)
     * Mendukung:
     * - Teks koordinat langsung: "-5.123456, 105.123456" / "-5.123456 105.123456" / "-5.123456; 105.123456"
     * - Arah mata angin: "5.123456S, 105.123456E" / "S 5.123456, E 105.123456"
     * - Derajat Menit Detik (DMS): 5°22'55.6"S 105°46'52.4"E
     * - Link Google Maps standar (@lat,lng,z)
     * - Shortlink maps.app.goo.gl atau goo.gl/maps (dengan multi-hop redirect tracing)
     * - Query URL: ?q=, &ll=, ?query=, ?daddr=, ?saddr=, ?center=, ?destination=
     * - Format pencarian /search/lat,lng, /place/lat,lng, /dir/.../lat,lng
     * - URL-encoded string (misal %2C untuk koma)
     * - Format internal Google Maps !3dlat!4dlng
     * - Embed & OpenGraph metadata staticmap
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

        // 1. Ekstrak URL jika ada kata pengantar teks (misal dari pesan WhatsApp: "Lokasi: https://...")
        $targetUrl = $input;
        if (preg_match('/(https?:\/\/[^\s]+)/i', $input, $m)) {
            $targetUrl = rtrim($m[1], '.,;:()[]"\'<>');
        } elseif (preg_match('/((?:maps\.app\.goo\.gl|goo\.gl\/maps|maps\.google\.com|google\.com\/maps)[^\s]*)/i', $input, $m)) {
            $targetUrl = 'https://' . rtrim($m[1], '.,;:()[]"\'<>');
        }

        // 2. Coba ekstraksi langsung dari target URL atau string
        $coords = self::parseCoordsFromString($targetUrl);
        if ($coords['lat'] !== null && $coords['lng'] !== null) {
            return $coords;
        }

        // Jika targetUrl berbeda dari input, coba juga pada input asli
        if ($targetUrl !== $input) {
            $coords = self::parseCoordsFromString($input);
            if ($coords['lat'] !== null && $coords['lng'] !== null) {
                return $coords;
            }
        }

        // 3. Jika merupakan shortlink atau URL Google Maps yang butuh follow redirects
        if (filter_var($targetUrl, FILTER_VALIDATE_URL) || str_contains($targetUrl, 'goo.gl') || str_contains($targetUrl, 'google')) {
            $unshortened = self::traceRedirectsAndExtract($targetUrl);
            if ($unshortened['lat'] !== null && $unshortened['lng'] !== null) {
                return $unshortened;
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Parsing pola koordinat dari string atau URL
     */
    public static function parseCoordsFromString(string $str): array
    {
        $decoded = urldecode($str);

        // Pola 1: Format @lat,lng dalam URL (misal: @-5.123456,105.123456,17z)
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 2: Format query string ?q=, &ll=, query=, daddr=, saddr=, center=, destination=
        if (preg_match('/(?:[?&](?:q|query|ll|daddr|saddr|center|destination)=(?:loc:)?)(-?\d{1,2}\.\d+)(?:,|\+|\s)+(-?\d{1,3}\.\d+)/i', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 3: Format /search/lat,+lng, /place/lat,+lng, atau /dir/.../lat,lng
        if (preg_match('/(?:\/search|\/place|\/dir(?:\/[^\/]*)*)\/(-?\d{1,2}\.\d+)(?:,|\+|\s)+\+?(-?\d{1,3}\.\d+)/i', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 4: Format data=!3dlat!4dlng (Internal Google Maps place URL)
        if (preg_match('/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 5: Static map center=lat,lng
        if (preg_match('/center=(-?\d{1,2}\.\d+)(?:%2C|,)(-?\d{1,3}\.\d+)/i', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 6: Format standar lat, lng angka langsung (-5.123456, 105.123456 atau -5.123456 105.123456)
        if (preg_match('/(-?\d{1,2}\.\d+)[\s,;]+\+?(-?\d{1,3}\.\d+)/', $decoded, $m)) {
            if (self::isValidCoordinate($m[1], $m[2])) {
                return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
            }
        }

        // Pola 7: Format Derajat Menit Detik (DMS): 5°22'55.6"S 105°46'52.4"E
        if (preg_match('/(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["\s]*([NS])[,;\s]+(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["\s]*([EW])/i', $decoded, $m)) {
            $lat = (float)$m[1] + ((float)$m[2] / 60.0) + ((float)$m[3] / 3600.0);
            if (strtoupper($m[4]) === 'S') $lat = -$lat;

            $lng = (float)$m[5] + ((float)$m[6] / 60.0) + ((float)$m[7] / 3600.0);
            if (strtoupper($m[8]) === 'W') $lng = -$lng;

            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
            }
        }

        // Pola 8: Format angka dengan indikator mata angin: 5.123456S, 105.123456E atau S5.123456, E105.123456
        if (preg_match('/([NS])?\s*([+-]?\d{1,2}\.\d+)\s*([NS])?[\s,;]+([EW])?\s*([+-]?\d{1,3}\.\d+)\s*([EW])?/i', $decoded, $m)) {
            $dirLat = strtoupper(($m[1] ?? '') ?: ($m[3] ?? ''));
            $lat = (float)$m[2];
            if ($dirLat === 'S') $lat = -abs($lat);

            $dirLng = strtoupper(($m[4] ?? '') ?: ($m[6] ?? ''));
            $lng = (float)$m[5];
            if ($dirLng === 'W') $lng = -abs($lng);

            if (self::isValidCoordinate($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Mengikuti rantai redirect HTTP secara step-by-step untuk mengekstrak koordinat dari header Location atau body
     */
    private static function traceRedirectsAndExtract(string $url): array
    {
        $currentUrl = $url;
        $maxHops = 8;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $coords = self::parseCoordsFromString($currentUrl);
            if ($coords['lat'] !== null && $coords['lng'] !== null) {
                return $coords;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');

            $response = curl_exec($ch);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);

            if (!$response) {
                break;
            }

            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            // Cek jika header Location berisi target redirect baru
            if (preg_match('/Location:\s*([^\r\n]+)/i', $headers, $locMatch)) {
                $nextUrl = trim($locMatch[1]);
                if (!str_starts_with($nextUrl, 'http')) {
                    $parts = parse_url($currentUrl);
                    $nextUrl = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'maps.google.com') . $nextUrl;
                }
                $coords = self::parseCoordsFromString($nextUrl);
                if ($coords['lat'] !== null && $coords['lng'] !== null) {
                    return $coords;
                }
                $currentUrl = $nextUrl;
                continue;
            }

            // Jika tidak ada redirect (200 OK), periksa konten HTML
            if (!empty($body)) {
                // Staticmap center
                if (preg_match('/staticmap\?[^"\'<>]*center=(-?\d{1,2}\.\d+)(?:%2C|,)(-?\d{1,3}\.\d+)/i', $body, $bm)) {
                    if (self::isValidCoordinate($bm[1], $bm[2])) {
                        return ['lat' => (float)$bm[1], 'lng' => (float)$bm[2]];
                    }
                }
                // Link query q=
                if (preg_match('/[?&]q=(-?\d{1,2}\.\d+)(?:%2C|,)(-?\d{1,3}\.\d+)/i', $body, $bm)) {
                    if (self::isValidCoordinate($bm[1], $bm[2])) {
                        return ['lat' => (float)$bm[1], 'lng' => (float)$bm[2]];
                    }
                }
                // Data !3d !4d
                if (preg_match('/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/i', $body, $bm)) {
                    if (self::isValidCoordinate($bm[1], $bm[2])) {
                        return ['lat' => (float)$bm[1], 'lng' => (float)$bm[2]];
                    }
                }
                // window.APP_INITIALIZATION_STATE
                if (preg_match('/\[null,null,(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)\]/', $body, $bm)) {
                    if (self::isValidCoordinate($bm[1], $bm[2])) {
                        return ['lat' => (float)$bm[1], 'lng' => (float)$bm[2]];
                    }
                }
            }

            break;
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Validasi rentang koordinat bumi
     */
    private static function isValidCoordinate($lat, $lng): bool
    {
        $lat = (float)$lat;
        $lng = (float)$lng;
        return ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0 && ($lat != 0.0 || $lng != 0.0));
    }
}
