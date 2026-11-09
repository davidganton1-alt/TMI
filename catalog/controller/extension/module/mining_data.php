<?php
/**
 * The Miner Island — Mining data refresh endpoint
 *
 * Catalog route: index.php?route=extension/module/mining_data
 *
 * Behaviour:
 *  - GET (default): returns the cached JSON to the browser. If the cache
 *    is older than 1 hour, refreshes it inline from upstream sources.
 *  - This is safe to hit on every product page load: the heavy work
 *    (curl_multi to 5 sources) only fires once per hour at most.
 *  - For a true cron-only refresh you can also call this from a system
 *    cron job (curl -s https://theminerisland.com/index.php?route=extension/module/mining_data >/dev/null)
 *    once an hour, and the browser-side calls then always serve from cache.
 *
 * Sources (free, no API key, no CORS issues since we proxy server-side):
 *   BTC : blockchain.info/stats               (hash_rate, difficulty, block reward)
 *   LTC : api.litecoinpool.org/pool           OR fallback constants
 *   KAS : api.kaspa.org/info/network          (live difficulty)
 *   XMR : localmonero.co/blocks/api/get_stats
 *   ZEC : api.zcha.in/v2/mainnet/network      (deprecated but mirrors still work)
 *   DOGE: dogechain.info/api/v1/network_info
 *
 * Any source that fails just keeps its previous cached value — the cache
 * file is never wiped on a partial failure. If a value has NEVER been
 * fetched, it gets the May 2026 baseline below.
 *
 * Output JSON schema (all values are H/s, USD, or coins-per-block):
 *   {
 *     "ts": 1747574400,        // unix timestamp of last refresh
 *     "stale_after": 3600,     // seconds until next refresh
 *     "BTC": {
 *       "network_hs": 1.02e21, "difficulty": 1.366e14,
 *       "block_reward": 3.125, "blocks_per_day": 144,
 *       "source": "blockchain.info"
 *     },
 *     "LTC": { ... },
 *     ...
 *   }
 *
 * The data file lives at storage/cache/mi_mining_data.json (gitignored
 * because storage/ is generally not version-controlled).
 */
class ControllerExtensionModuleMiningData extends Controller {

    /** Time before a cached value is considered stale */
    const CACHE_TTL = 3600; // 1 hour

    /** Per-source soft timeout in seconds */
    const FETCH_TIMEOUT = 3;

    /** Path to the JSON cache file, relative to OpenCart's DIR_CACHE */
    private function cacheFile() {
        return DIR_CACHE . 'mi_mining_data.json';
    }

    /**
     * Baseline values calibrated to May 2026. Used as last-resort fallbacks
     * if a source has never been successfully fetched. Block reward and
     * blocks_per_day are protocol constants (only change at known halving
     * dates) so they're always set from here, not from external APIs.
     */
    private function baseline() {
        return array(
            'BTC' => array(
                'network_hs'     => 1.02e21,       // 1.02 ZH/s (May 17 2026)
                'difficulty'     => 1.3661e14,     // 136.61 T
                'block_reward'   => 3.125,         // Post April 2024 halving
                'blocks_per_day' => 144,
            ),
            'LTC' => array(
                'network_hs'     => 2.85e15,       // 2.85 PH/s
                'difficulty'     => 4.8e7,
                'block_reward'   => 6.25,          // Pre-Aug 2027 halving
                'blocks_per_day' => 576,
            ),
            'KAS' => array(
                'network_hs'     => 4.15e17,       // 415 PH/s
                'difficulty'     => 3.5e16,
                'block_reward'   => 55,            // Drops sharply July 10 2026
                'blocks_per_day' => 86400,
            ),
            'XMR' => array(
                'network_hs'     => 6.5e9,         // 6.5 GH/s
                'difficulty'     => 7.8e11,
                'block_reward'   => 0.6,           // Tail emission
                'blocks_per_day' => 720,
            ),
            'ZEC' => array(
                'network_hs'     => 1.507e16,      // 15.07 PH/s
                'difficulty'     => 1.36e8,
                'block_reward'   => 1.5625,        // Post-Nov 2024 halving
                'blocks_per_day' => 1152,
            ),
            'DOGE' => array(
                'network_hs'     => 2.99e15,       // Shared Scrypt hashrate with LTC merge
                'difficulty'     => 4.39e7,
                'block_reward'   => 10000,
                'blocks_per_day' => 1440,
            ),
        );
    }

    /**
     * Public entry point. Always returns JSON to the browser. Refreshes
     * the cache on the way out if it's stale (or doesn't exist yet).
     */
    public function index() {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Cache-Control: public, max-age=300'); // 5 min browser cache
        $this->response->addHeader('Access-Control-Allow-Origin: *');

        $data = $this->readCache();
        $now = time();

        // Refresh if missing or stale
        if (!$data || !isset($data['ts']) || ($now - $data['ts']) > self::CACHE_TTL) {
            $data = $this->refresh($data);
        }

        $this->response->setOutput(json_encode($data));
    }

    private function readCache() {
        $path = $this->cacheFile();
        if (!file_exists($path)) return null;
        $raw = @file_get_contents($path);
        if (!$raw) return null;
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : null;
    }

    private function writeCache($data) {
        $path = $this->cacheFile();
        @file_put_contents($path, json_encode($data));
    }

    /**
     * Refresh all sources. Starts from the existing cache (or baseline if
     * none), and overwrites only the values we successfully fetched. A
     * failed source preserves the previous value.
     */
    private function refresh($previous) {
        $baseline = $this->baseline();

        // Seed with previous data (or baseline) so failed sources keep their values
        $data = array('ts' => time(), 'stale_after' => self::CACHE_TTL);
        foreach ($baseline as $coin => $vals) {
            $data[$coin] = isset($previous[$coin]) ? array_merge($vals, $previous[$coin]) : $vals;
            $data[$coin]['source'] = isset($previous[$coin]['source']) ? $previous[$coin]['source'] : 'baseline';
        }

        // ─── BTC: blockchain.info/stats ─────────────────────────────────
        $btc = $this->httpGet('https://blockchain.info/stats?format=json');
        if ($btc && isset($btc['hash_rate']) && $btc['hash_rate'] > 0) {
            // blockchain.info reports hash_rate in GH/s
            $data['BTC']['network_hs'] = $btc['hash_rate'] * 1e9;
            if (isset($btc['difficulty'])) $data['BTC']['difficulty'] = (float)$btc['difficulty'];
            $data['BTC']['source'] = 'blockchain.info';
        }

        // ─── LTC: api.blockchair.com (covers Litecoin) ──────────────────
        $ltc = $this->httpGet('https://api.blockchair.com/litecoin/stats');
        if ($ltc && isset($ltc['data']['hashrate_24h'])) {
            $data['LTC']['network_hs'] = (float)$ltc['data']['hashrate_24h'];
            if (isset($ltc['data']['difficulty'])) $data['LTC']['difficulty'] = (float)$ltc['data']['difficulty'];
            $data['LTC']['source'] = 'blockchair.com';
        }

        // ─── DOGE: api.blockchair.com (Dogecoin) ────────────────────────
        $doge = $this->httpGet('https://api.blockchair.com/dogecoin/stats');
        if ($doge && isset($doge['data']['hashrate_24h'])) {
            $data['DOGE']['network_hs'] = (float)$doge['data']['hashrate_24h'];
            if (isset($doge['data']['difficulty'])) $data['DOGE']['difficulty'] = (float)$doge['data']['difficulty'];
            $data['DOGE']['source'] = 'blockchair.com';
        }

        // ─── XMR: localmonero / monero.fail ─────────────────────────────
        // localmonero has a public stats endpoint that returns difficulty
        $xmr = $this->httpGet('https://localmonero.co/blocks/api/get_stats');
        if ($xmr && isset($xmr['difficulty'])) {
            // XMR block time is 120s, so network_hs = difficulty / 120
            $data['XMR']['difficulty'] = (float)$xmr['difficulty'];
            $data['XMR']['network_hs'] = (float)$xmr['difficulty'] / 120;
            $data['XMR']['source'] = 'localmonero.co';
        }

        // ─── KAS: api-v2-do-sf.kas.fyi or alt ──────────────────────────
        // Kaspa's official explorer API
        $kas = $this->httpGet('https://api.kaspa.org/info/network');
        if ($kas && isset($kas['difficulty'])) {
            // Kaspa 1 block per second, so network_hs ≈ difficulty × 2
            $data['KAS']['difficulty'] = (float)$kas['difficulty'];
            $data['KAS']['network_hs'] = (float)$kas['difficulty'] * 2;
            $data['KAS']['source'] = 'api.kaspa.org';
        }

        // ─── ZEC: api.blockchair.com/zcash ─────────────────────────────
        $zec = $this->httpGet('https://api.blockchair.com/zcash/stats');
        if ($zec && isset($zec['data']['hashrate_24h'])) {
            $data['ZEC']['network_hs'] = (float)$zec['data']['hashrate_24h'];
            if (isset($zec['data']['difficulty'])) $data['ZEC']['difficulty'] = (float)$zec['data']['difficulty'];
            $data['ZEC']['source'] = 'blockchair.com';
        }

        $this->writeCache($data);
        return $data;
    }

    /**
     * Minimal JSON HTTP GET with short timeout. Returns decoded array or null.
     * Uses cURL when available, falls back to file_get_contents.
     */
    private function httpGet($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::FETCH_TIMEOUT);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'TheMinerIsland/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || !$body) return null;
        } else {
            $ctx = stream_context_create(array(
                'http' => array(
                    'timeout' => self::FETCH_TIMEOUT,
                    'header' => "Accept: application/json\r\nUser-Agent: TheMinerIsland/1.0\r\n",
                    'ignore_errors' => true
                )
            ));
            $body = @file_get_contents($url, false, $ctx);
            if (!$body) return null;
        }
        $parsed = @json_decode($body, true);
        return is_array($parsed) ? $parsed : null;
    }
}
