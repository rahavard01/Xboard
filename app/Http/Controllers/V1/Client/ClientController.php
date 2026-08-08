<?php

namespace App\Http\Controllers\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Protocols\General;
use App\Services\Plugin\HookManager;
use App\Services\ServerService;
use App\Services\UserService;
use App\Utils\Helper;
use Illuminate\Http\Request;
#اضافه شد برای تبدیل تاریخ به شمسی
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
#تا اینجا
class ClientController extends Controller
{
    /**
     * Protocol prefix mapping for server names
     */
    private const PROTOCOL_PREFIXES = [
        'hysteria' => [
            1 => '[Hy]',
            2 => '[Hy2]'
        ],
        'vless' => '[vless]',
        'shadowsocks' => '[ss]',
        'vmess' => '[vmess]',
        'trojan' => '[trojan]',
        'tuic' => '[tuic]',
        'socks' => '[socks]',
        'anytls' => '[anytls]'
    ];


    public function subscribe(Request $request)
    {
        HookManager::call('client.subscribe.before');
        $request->validate([
            'types' => ['nullable', 'string'],
            'filter' => ['nullable', 'string'],
            'flag' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $userService = new UserService();
        $useTraffic = $user['u'] + $user['d'];
        $remainingTraffic = $user['transfer_enable'] - $useTraffic;
        
        if ($remainingTraffic <= 0) {
        
            $account = $user['email'] ?? 'unknown';
            $accountName = str_replace(["@", ".com"], ["-", ""], $account);
        
            $userName = rawurlencode("👤 USER ：{$accountName}");
            $trafficName = rawurlencode('⛔ ترافیک شما به اتمام رسید ⛔');
        
            $disabledLinks = implode("\n", [
                "vless://00000000-0000-0000-0000-000000000001@0.0.0.0:1?encryption=none&type=tcp#{$trafficName}",
                "vless://00000000-0000-0000-0000-000000000000@0.0.0.0:1?encryption=none&type=tcp#{$userName}",
            ]);
        
            return response($disabledLinks, 200, ['Content-Type' => 'text/plain']);
        }
        if (!$userService->isAvailable($user)) {
            HookManager::call('client.subscribe.unavailable');
        
            $account = $user['email'] ?? 'unknown';
            $accountName = str_replace(["@", ".com"], ["-", ""], $account);
        
            $userName = rawurlencode("👤 USER ：{$accountName}");
            $expiredName = rawurlencode('⛔️ اکانت شما منقضی شد ⛔️');
        
            $disabledLinks = implode("\n", [
                "vless://00000000-0000-0000-0000-000000000001@0.0.0.0:1?encryption=none&type=tcp#{$expiredName}",
                "vless://00000000-0000-0000-0000-000000000000@0.0.0.0:1?encryption=none&type=tcp#{$userName}",
            ]);
        
            return response($disabledLinks, 200, ['Content-Type' => 'text/plain']);
        }

        return $this->doSubscribe($request, $user);
    }

    public function doSubscribe(Request $request, $user, $servers = null)
    {
        if ($servers === null) {
            $servers = ServerService::getAvailableServers($user);
            $servers = HookManager::filter('client.subscribe.servers', $servers, $user, $request);
        }

        $clientInfo = $this->getClientInfo($request);

        $requestedTypes = $this->parseRequestedTypes($request->input('types'));
        $filterKeywords = $this->parseFilterKeywords($request->input('filter'));

        $protocolClassName = app('protocols.manager')->matchProtocolClassName($clientInfo['flag'])
            ?? General::class;

        $serversFiltered = $this->filterServers(
            servers: $servers,
            allowedTypes: $requestedTypes,
            filterKeywords: $filterKeywords
        );

        // ✅ SpeedBox-only nodes (SBONLY)
        $speedboxKey = config('services.speedbox.sub_key', '');
        $reqKey = (string) $request->header('X-SpeedBox-Key', '');
        $isSpeedBox = ($speedboxKey !== '' && hash_equals($speedboxKey, $reqKey));

        $hasSBOnly = function ($server) {
            $name = $server['name'] ?? '';
            $tags = $server['tags'] ?? [];

            // name contains SBONLY
            if (stripos($name, 'SBONLY') !== false) return true;

            // tags contains SBONLY (case-insensitive)
            foreach ($tags as $t) {
                if (is_string($t) && strcasecmp($t, 'SBONLY') === 0) return true;
            }

            return false;
        };

        if (!$isSpeedBox) {
            // For all other clients: hide SBONLY nodes completely
            $serversFiltered = collect($serversFiltered)
                ->reject(fn($s) => $hasSBOnly($s))
                ->values()
                ->all();
        } else {
            // For SpeedBox: keep them, but clean the name so SBONLY doesn't appear in UI
            $serversFiltered = collect($serversFiltered)
                ->map(function ($s) {
                    if (!isset($s['name'])) return $s;

                    // remove SBONLY token in common formats: "SBONLY", "[SBONLY]", "(SBONLY)"
                    $n = (string) $s['name'];
                    $n = preg_replace('/\s*[\[\(]?\s*SBONLY\s*[\]\)]?\s*/i', ' ', $n);
                    // normalize separators/spaces
                    $n = preg_replace('/\s{2,}/', ' ', $n);
                    $n = preg_replace('/\s*-\s*/', ' - ', $n);
                    $n = trim($n);

                    $s['name'] = $n;
                    return $s;
                })
                ->values()
                ->all();
        }
        
        $this->setSubscribeInfoToServers($serversFiltered, $user, 0);
        $serversFiltered = $this->addPrefixToServerName($serversFiltered);

        // Instantiate the protocol class with filtered servers and client info
        $protocolInstance = app()->make($protocolClassName, [
            'user' => $user,
            'servers' => $serversFiltered,
            'clientName' => $clientInfo['name'] ?? null,
            'clientVersion' => $clientInfo['version'] ?? null
        ]);

        return $protocolInstance->handle();
    }

    /**
     * Parses the input string for requested server types.
     */
    private function parseRequestedTypes(?string $typeInputString): array
    {
        if (blank($typeInputString) || $typeInputString === 'all') {
            return Server::VALID_TYPES;
        }

        $requested = collect(preg_split('/[|,｜]+/', $typeInputString))
            ->map(fn($type) => trim($type))
            ->filter() // Remove empty strings that might result from multiple delimiters
            ->all();

        return array_values(array_intersect($requested, Server::VALID_TYPES));
    }

    /**
     * Parses the input string for filter keywords.
     */
    private function parseFilterKeywords(?string $filterInputString): ?array
    {
        if (blank($filterInputString) || mb_strlen($filterInputString) > 20) {
            return null;
        }

        return collect(preg_split('/[|,｜]+/', $filterInputString))
            ->map(fn($keyword) => trim($keyword))
            ->filter() // Remove empty strings
            ->all();
    }

    /**
     * Filters servers based on allowed types and keywords.
     */
    private function filterServers(array $servers, array $allowedTypes, ?array $filterKeywords): array
    {
        return collect($servers)->filter(function ($server) use ($allowedTypes, $filterKeywords) {
            // Condition 1: Server type must be in the list of allowed types
            if ($allowedTypes && !in_array($server['type'], $allowedTypes)) {
                return false; // Filter out (don't keep)
            }

            // Condition 2: If filterKeywords are provided, at least one keyword must match
            if (!empty($filterKeywords)) { // Check if $filterKeywords is not empty
                $keywordMatch = collect($filterKeywords)->contains(function ($keyword) use ($server) {
                    return stripos($server['name'], $keyword) !== false
                        || in_array($keyword, $server['tags'] ?? []);
                });
                if (!$keywordMatch) {
                    return false; // Filter out if no keywords match
                }
            }
            // Keep the server if its type is allowed AND (no filter keywords OR at least one keyword matched)
            return true;
        })->values()->all();
    }

    private function getClientInfo(Request $request): array
    {
        $flag = strtolower($request->input('flag') ?? $request->header('User-Agent', ''));

        $clientName = null;
        $clientVersion = null;

        if (preg_match('/([a-zA-Z0-9\-_]+)[\/\s]+(v?[0-9]+(?:\.[0-9]+){0,2})/', $flag, $matches)) {
            $potentialName = strtolower($matches[1]);
            $clientVersion = preg_replace('/^v/', '', $matches[2]);

            if (in_array($potentialName, app('protocols.flags'))) {
                $clientName = $potentialName;
            }
        }

        if (!$clientName) {
            $flags = collect(app('protocols.flags'))->sortByDesc(fn($f) => strlen($f))->values()->all();
            foreach ($flags as $name) {
                if (stripos($flag, $name) !== false) {
                    $clientName = $name;
                    if (!$clientVersion) {
                        $pattern = '/' . preg_quote($name, '/') . '[\/\s]+(v?[0-9]+(?:\.[0-9]+){0,2})/i';
                        if (preg_match($pattern, $flag, $vMatches)) {
                            $clientVersion = preg_replace('/^v/', '', $vMatches[1]);
                        }
                    }
                    break;
                }
            }
        }

        if (!$clientVersion) {
            if (preg_match('/\/v?(\d+(?:\.\d+){0,2})/', $flag, $matches)) {
                $clientVersion = $matches[1];
            }
        }

        return [
            'flag' => $flag,
            'name' => $clientName,
            'version' => $clientVersion
        ];
    }

#این تابع تغییر کرد داخلش برای عبارت های مختلف و تبدیل تاریخ به شمسی
    private function setSubscribeInfoToServers(&$servers, $user, $rejectServerCount = 0)
    {
        if (!isset($servers[0]))
            return;
        if ($rejectServerCount > 0) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "过滤掉{$rejectServerCount}条线路",
            ]));
        }
        if (!(int) admin_setting('show_info_to_server_enable', 0))
            return;
        $firstServerName = $servers[0]['name'] ?? '';
        
        preg_match('/^(\p{Regional_Indicator}{2})/u', $firstServerName, $matches);
        
        $flagEmoji = $matches[1] ?? '';     
        
        $useTraffic = round($user['u'] / (1024 * 1024 * 1024), 2) + round($user['d'] / (1024 * 1024 * 1024), 2);
        $totalTraffic = round($user['transfer_enable'] / (1024 * 1024 * 1024), 2);
        $remainingTraffic = round($totalTraffic - $useTraffic, 2);
        $expiredDate = $user['expired_at'] ? date('Y-m-d', $user['expired_at']) : __('Updating Soon ...');
        $expiredDate1 = $user['expired_at']
            ? Jalalian::fromCarbon(Carbon::createFromTimestamp($user['expired_at']))->format('Y-m-d')
            : __('Updating Soon ...');
        $userService = new UserService();
        $resetDay = $userService->getResetDay($user);
        $account = $user['email'];
        $account1 = str_replace(["@", ".com"], ["-", ""], $account);
#        $remain_date=round ((strtotime($expiredDate)-strtotime(date("m/d/Y")))/86400);
        $remain_date = null;
        
        if ($expiredDate && strtotime($expiredDate) >= strtotime(date("Y-m-d"))) {
            $remain_date = round((strtotime($expiredDate) - strtotime(date("Y-m-d"))) / 86400);
        }
        $remainingTrafficValue = floatval($remainingTraffic);
        array_unshift($servers, array_merge($servers[0], [
            'name' => "{$flagEmoji}⛔️️ Expire ：{$expiredDate1} / {$remain_date} Days",
        ]));
        if ($resetDay) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "{$flagEmoji}♻️ Reset Traffic ：{$resetDay} Days",
            ]));
        }
        array_unshift($servers, array_merge($servers[0], [
            'name' => "{$flagEmoji}👤 USER  ：{$account1} ",
        ]));
        array_unshift($servers, array_merge($servers[0], [
            'name' => "{$flagEmoji}☠️ Remaining Traffic  ：{$remainingTraffic} GB",
        ]));
        if (in_array((string)$remain_date, ['0', '1', '2'])) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "{$flagEmoji}❌ اکانت شما رو به اتمام است ❌",
            ]));
        }
        if ($remainingTraffic <= 0) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "{$flagEmoji}⛔ ترافیک شما به اتمام رسید ⛔",
            ]));
        } elseif ($remainingTraffic <= 1) {
            array_unshift($servers, array_merge($servers[0], [
                'name' => "{$flagEmoji}❌ ترافیک شما رو به اتمام است ❌",
            ]));
        }
    }
#تا اینجا
    private function addPrefixToServerName(array $servers): array
    {
        if (!admin_setting('show_protocol_to_server_enable', false)) {
            return $servers;
        }
        return collect($servers)
            ->map(function (array $server): array {
                $server['name'] = $this->getPrefixedServerName($server);
                return $server;
            })
            ->all();
    }

    private function getPrefixedServerName(array $server): string
    {
        $type = $server['type'] ?? '';
        if (!isset(self::PROTOCOL_PREFIXES[$type])) {
            return $server['name'] ?? '';
        }
        $prefix = is_array(self::PROTOCOL_PREFIXES[$type])
            ? self::PROTOCOL_PREFIXES[$type][$server['protocol_settings']['version'] ?? 1] ?? ''
            : self::PROTOCOL_PREFIXES[$type];
        return $prefix . ($server['name'] ?? '');
    }
}
