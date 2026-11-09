<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* OpenStreetMap integration. Public Nominatim: manual search only, max 1 req/sec
   for the entire application; use a dedicated provider for production traffic. */
class CarEaseMaps extends Controller {
    private function respond($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    }
    private function requestJson($url) {
        if (!function_exists('curl_init')) throw new RuntimeException('Enable PHP cURL.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 18,
           CURLOPT_HTTPHEADER => [
    'Accept: application/json',
    'Referer: http://localhost/carease-rentals',
    'User-Agent: CarEaseRentals/1.0 (contact: kyrie6072@gmail.com)'
],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Map service unavailable: '.$err);
        if ($code === 429) throw new RuntimeException('Map service rate limit reached. Please wait and try again.');
        if ($code < 200 || $code >= 300) throw new RuntimeException('Map service returned HTTP '.$code);
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new RuntimeException('Invalid map service response.');
        return $data;
    }
    public function config() {
        $this->respond(['browser_key'=>'','map_id'=>'','region'=>'PH','language'=>'en']);
    }
    public function autocomplete() {
        $query = trim((string) ($_GET['input'] ?? ''));
        if (mb_strlen($query) < 3 || mb_strlen($query) > 150)
            $this->respond(['suggestions'=>[]]);
        try {
            $url = 'https://nominatim.openstreetmap.org/search?'.http_build_query([
                'q'=>$query, 'format'=>'jsonv2', 'limit'=>5, 'addressdetails'=>1
            ]);
            $data = $this->requestJson($url);
            $results = [];
            foreach ($data as $item) {
                if (!isset($item['lat'],$item['lon'],$item['place_id'])) continue;
                $results[] = [
                    'place_id'=>'osm:'.$item['place_id'],
                    'text'=>$item['display_name'] ?? '',
                    'main_text'=>explode(',', $item['display_name'] ?? '')[0],
                    'secondary_text'=>$item['display_name'] ?? '',
                    'lat'=>(float)$item['lat'], 'lng'=>(float)$item['lon'],
                ];
            }
            $this->respond(['suggestions'=>$results]);
        } catch (Throwable $e) { $this->respond(['error'=>$e->getMessage()], 503); }
    }
    public function place() {
        // Place coordinates are provided directly by the selected search result.
        $this->respond(['error'=>'Select a location from the search results.'], 400);
    }
    public function directions() {
        $a = (float) ($_POST['origin_lat'] ?? 999);
        $b = (float) ($_POST['origin_lng'] ?? 999);
        $c = (float) ($_POST['dest_lat'] ?? 999);
        $d = (float) ($_POST['dest_lng'] ?? 999);
        if (abs($a)>90 || abs($c)>90 || abs($b)>180 || abs($d)>180)
            $this->respond(['error'=>'Invalid coordinates.'], 422);
        try {
            $url = sprintf('https://router.project-osrm.org/route/v1/driving/%.6f,%.6f;%.6f,%.6f?overview=full&geometries=geojson', $b,$a,$d,$c);
            $data = $this->requestJson($url);
            $route = $data['routes'][0] ?? null;
            if (!$route) $this->respond(['error'=>'No driving route found.'], 404);
            $polyline = [];
            foreach (($route['geometry']['coordinates'] ?? []) as $p)
                $polyline[] = [(float)$p[1],(float)$p[0]];
            $this->respond([
                'distance_m'=>(float)$route['distance'],
                'duration_s'=>(float)$route['duration'],
                'polyline'=>$polyline
            ]);
        } catch (Throwable $e) { $this->respond(['error'=>$e->getMessage()], 503); }
    }
}
