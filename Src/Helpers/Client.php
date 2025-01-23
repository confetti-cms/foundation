<?php

declare(strict_types=1);

namespace Confetti\Foundation\Helpers;

class Client
{
    public function get(string $url, array $headers = [], ?array $body = null): string
    {
        $curl = curl_init();

        if ($body) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        // get from cookie access_token
        $accessToken = $_COOKIE['access_token'] ?? null;
        if (!empty($accessToken)) {
            $headers[] = 'authorization: Bearer ' . $accessToken;
        };

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode >= 300) {
            throw new \RuntimeException('Could not fetch with url: ' . $url . '. Err: ' . $httpCode . ' ' . $response);
        }
        if ($response === false) {
            throw new \RuntimeException('Could not fetch with url: ' . $url . '. Curl Err: ' . curl_error($curl));
        }

        curl_close($curl);
        return $response;
    }
}
