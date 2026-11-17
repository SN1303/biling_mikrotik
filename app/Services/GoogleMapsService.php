<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    /**
     * Ekstrak Latitude dan Longitude dari input string (link Google Maps atau teks koordinat)
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

        // Pola 1: Format langsung angka lat,lng (misal "-5.123456, 105.123456" atau "-5.123456,105.123456")
        if (preg_match('/^(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)$/', $input, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2]
            ];
        }

        // Jika merupakan link pendek (shortlink maps.app.goo.gl atau goo.gl), fetch redirect URL aslinya
        if (str_contains($input, 'maps.app.goo.gl') || str_contains($input, 'goo.gl/maps')) {
            try {
                $response = Http::withoutRedirecting()->timeout(5)->get($input);
                $location = $response->header('Location');
                if ($location) {
                    $input = $location;
                }
            } catch (\Exception $e) {
                // Abaikan timeout atau error jaringan, lanjut coba regex URL asal
            }
        }

        // Pola 2: Format dalam URL @lat,lng,zoomz (misal: @-5.123456,105.123456,17z)
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2]
            ];
        }

        // Pola 3: Format query string ?q=lat,lng atau &ll=lat,lng
        if (preg_match('/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2]
            ];
        }

        // Pola 4: Format destination=/place/...lat,lng
        if (preg_match('/destination=(-?\d+\.\d+),(-?\d+\.\d+)/', $input, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2]
            ];
        }

        return ['lat' => null, 'lng' => null];
    }
}
