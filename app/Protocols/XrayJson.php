<?php

namespace App\Protocols;

use App\Models\Server;
use App\Support\AbstractProtocol;
use App\Utils\Helper;
use Illuminate\Http\Response;
use JsonException;

/**
 * SMART JSON subscription exporter for Streisand.
 *
 * The normal Xboard subscription URL remains unchanged. ProtocolManager
 * selects this exporter from the request User-Agent.
 */
class XrayJson extends AbstractProtocol
{
    /**
     * IMPORTANT:
     * - For Streisand, set the subscription's custom User-Agent to:
     *     Streisand-Smart
     *   This keeps the original subscription URL unchanged and avoids
     *   treating every generic Mozilla request as Streisand.
     *
     * Generic Mozilla User-Agents are intentionally NOT matched because
     * they cannot safely distinguish Streisand from browsers or other apps.
     */
    public $flags = [
        'xray-json',
        'streisand',
        'streisand/',
        'streisand-smart',
    ];

    /**
     * Do not let AbstractProtocol remove unsupported nodes before handle().
     * We need the complete list to preserve Xboard information entries and
     * report any protocols that cannot be represented by Xray JSON.
     */
    protected $allowedProtocols = [];

    private const SMART_NAME = '🚀 SMART SERVER';
    private const SMART_OUTBOUND_PREFIX = 'smart-1-proxy-';
    private const SMART_BALANCER_TAG = 'smart-balancer-1';

    /**
     * Protocols representable as native Xray outbounds in this exporter.
     * TUIC, AnyTLS, Naive and Mieru are intentionally excluded because they
     * are not native Xray outbound protocols in the JSON format used here.
     */
    private const XRAY_SUPPORTED_PROTOCOLS = [
        Server::TYPE_VLESS,
        Server::TYPE_VMESS,
        Server::TYPE_TROJAN,
        Server::TYPE_SHADOWSOCKS,
        Server::TYPE_SOCKS,
        Server::TYPE_HTTP,
        Server::TYPE_HYSTERIA,
    ];

    /**
     * @throws JsonException
     */
    public function handle(): Response
    {
        [$infoServers, $proxyServers] = $this->splitLeadingInfoServers(
            array_values($this->servers)
        );

        $supportedProxyServers = array_values(array_filter(
            $proxyServers,
            fn (array $server): bool => $this->isSupportedServer($server)
        ));

        $unsupportedTypes = array_values(array_unique(array_filter(array_map(
            static fn (array $server): string => (string) data_get($server, 'type', ''),
            array_filter(
                $proxyServers,
                fn (array $server): bool => !$this->isSupportedServer($server)
            )
        ))));

        // Never return an empty subscription. If all nodes are non-Xray
        // protocols, gracefully fall back to Xboard's normal Base64 output.
        if ($supportedProxyServers === []) {
            return (new General(
                $this->user,
                $this->servers,
                $this->clientName,
                $this->clientVersion,
                $this->userAgent
            ))->handle();
        }

        $configs = [];
        $smartOutbounds = [];
        $dnsDomains = [];

        // Xboard information entries are clones of the first real node. If
        // that first node is unsupported by Xray JSON, rebuild the information
        // entries using the first supported node while preserving their names.
        $infoTemplate = $supportedProxyServers[0];
        foreach ($infoServers as $infoServer) {
            $server = $infoTemplate;
            $server['name'] = (string) data_get($infoServer, 'name', 'Account information');
            $configs[] = $this->buildSingleConfig($server);
        }

        // Build the SMART profile only from real, supported proxy nodes.
        foreach ($supportedProxyServers as $index => $server) {
            $tag = self::SMART_OUTBOUND_PREFIX
                . $this->makeTagSuffix($server, $index);

            $outbound = $this->buildProxyOutbound($server, $tag);
            if ($outbound === null) {
                continue;
            }

            $smartOutbounds[] = $outbound;
            $this->appendDnsDomain($dnsDomains, $server);
        }

        // Required order: Xboard information → SMART SERVER → normal nodes.
        if ($smartOutbounds !== []) {
            $configs[] = $this->buildSmartConfig(
                $smartOutbounds,
                array_values(array_unique($dnsDomains))
            );
        }

        foreach ($supportedProxyServers as $server) {
            $configs[] = $this->buildSingleConfig($server);
        }

        $body = json_encode(
            $configs,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_PRETTY_PRINT
        );

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'inline; filename="xray-smart-subscription.json"',
            'profile-title' => 'base64:' . base64_encode(self::SMART_NAME),
            'profile-update-interval' => '1',
            'subscription-userinfo' => $this->buildSubscriptionUserInfo(),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Smart-Supported-Protocols' => implode(',', self::XRAY_SUPPORTED_PROTOCOLS),
        ];

        if ($unsupportedTypes !== []) {
            $headers['X-Smart-Skipped-Protocols'] = implode(',', $unsupportedTypes);
        }

        return response($body, 200, $headers);
    }

    private function isSupportedServer(array $server): bool
    {
        $type = (string) data_get($server, 'type', '');

        if (!in_array($type, self::XRAY_SUPPORTED_PROTOCOLS, true)) {
            return false;
        }

        // Xray's Hysteria outbound supports Hysteria v2 only.
        if ($type === Server::TYPE_HYSTERIA) {
            return (int) data_get($server, 'protocol_settings.version', 2) === 2;
        }

        return true;
    }

    /**
     * Xboard prepends subscription-information entries by cloning the first
     * real server and changing only its name. This detects that leading clone
     * group without relying on translated information labels.
     *
     * @return array{0: array, 1: array}
     */
    private function splitLeadingInfoServers(array $servers): array
    {
        if (count($servers) < 2) {
            return [[], $servers];
        }

        $firstFingerprint = $this->serverConnectionFingerprint($servers[0]);
        $sameConnectionCount = 1;

        for ($index = 1, $count = count($servers); $index < $count; $index++) {
            if ($this->serverConnectionFingerprint($servers[$index]) !== $firstFingerprint) {
                break;
            }

            $sameConnectionCount++;
        }

        if ($sameConnectionCount === 1) {
            return [[], $servers];
        }

        // The final entry in the identical leading run is the actual node.
        $infoCount = $sameConnectionCount - 1;

        return [
            array_slice($servers, 0, $infoCount),
            array_slice($servers, $infoCount),
        ];
    }

    private function serverConnectionFingerprint(array $server): string
    {
        unset($server['name'], $server['tags']);

        return hash(
            'sha256',
            json_encode(
                $server,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            ) ?: serialize($server)
        );
    }

    private function buildSingleConfig(array $server): array
    {
        $outbound = $this->buildProxyOutbound($server, 'proxy');

        if ($outbound === null) {
            throw new \RuntimeException(
                'Unsupported Xray JSON protocol: ' . (string) data_get($server, 'type', 'unknown')
            );
        }

        $domains = [];
        $this->appendDnsDomain($domains, $server);

        return [
            'remarks' => (string) data_get($server, 'name', 'Proxy'),
            'dns' => $this->buildDns($domains),
            'fakedns' => $this->buildFakeDns(),
            'inbounds' => $this->buildInbounds(),
            'outbounds' => [
                $outbound,
                $this->buildDirectOutbound(),
                $this->buildBlockOutbound(),
                $this->buildDnsOutbound(),
            ],
            'routing' => $this->buildSingleRouting(),
        ];
    }

    private function buildSmartConfig(array $proxyOutbounds, array $dnsDomains): array
    {
        $fallbackTag = (string) data_get($proxyOutbounds, '0.tag');

        return [
            'remarks' => self::SMART_NAME,
            'burstObservatory' => [
                'pingConfig' => [
                    'connectivity' => '',
                    'destination' => 'https://connectivitycheck.gstatic.com/generate_204',
                    'interval' => '20m',
                    'sampling' => 5,
                    'timeout' => '5s',
                ],
                'subjectSelector' => [
                    self::SMART_OUTBOUND_PREFIX,
                ],
            ],
            'dns' => $this->buildDns($dnsDomains),
            'fakedns' => $this->buildFakeDns(),
            'inbounds' => $this->buildInbounds(),
            'outbounds' => array_merge(
                $proxyOutbounds,
                [
                    $this->buildDirectOutbound(),
                    $this->buildBlockOutbound(),
                    $this->buildDnsOutbound(),
                ]
            ),
            'routing' => [
                'balancers' => [
                    [
                        'fallbackTag' => $fallbackTag,
                        'selector' => [
                            self::SMART_OUTBOUND_PREFIX,
                        ],
                        'strategy' => [
                            'type' => 'leastLoad',
                        ],
                        'tag' => self::SMART_BALANCER_TAG,
                    ],
                ],
                'domainStrategy' => 'AsIs',
                'rules' => array_merge(
                    $this->buildCommonDirectRules(),
                    [
                        [
                            'balancerTag' => self::SMART_BALANCER_TAG,
                            'domain' => [
                                'domain:api.ipify.org',
                                'domain:ipify.org',
                                'domain:ipinfo.io',
                                'domain:ip-api.com',
                                'domain:ipwho.is',
                                'domain:ifconfig.me',
                                'domain:icanhazip.com',
                                'domain:ip.sb',
                            ],
                            'type' => 'field',
                        ],
                        [
                            'balancerTag' => self::SMART_BALANCER_TAG,
                            'network' => 'tcp,udp',
                            'type' => 'field',
                        ],
                    ]
                ),
            ],
        ];
    }

    private function buildSingleRouting(): array
    {
        return [
            'domainStrategy' => 'AsIs',
            'rules' => array_merge(
                $this->buildCommonDirectRules(),
                [
                    [
                        'network' => 'tcp,udp',
                        'outboundTag' => 'proxy',
                        'type' => 'field',
                    ],
                ]
            ),
        ];
    }

    private function buildCommonDirectRules(): array
    {
        return [
            [
                'network' => 'udp',
                'outboundTag' => 'block',
                'port' => '443',
                'type' => 'field',
            ],
            [
                'ip' => ['geoip:private'],
                'outboundTag' => 'direct',
                'type' => 'field',
            ],
            [
                'domain' => ['geosite:private'],
                'outboundTag' => 'direct',
                'type' => 'field',
            ],
            [
                'ip' => ['geoip:ir'],
                'outboundTag' => 'direct',
                'type' => 'field',
            ],
            [
                'domain' => ['geosite:category-ir'],
                'outboundTag' => 'direct',
                'type' => 'field',
            ],
        ];
    }

    private function buildProxyOutbound(array $server, string $tag): ?array
    {
        $protocolSettings = (array) data_get(
            $server,
            'protocol_settings',
            []
        );
        
        $tlsMode = (int) data_get(
            $protocolSettings,
            'tls',
            0
        );
        
        $network = (string) data_get(
            $protocolSettings,
            'network',
            'tcp'
        );
        
        if (
            $tlsMode === 2
            && !in_array($network, ['tcp', 'xhttp', 'grpc'], true)
        ) {
            return null;
        }        
        return match ((string) data_get($server, 'type', '')) {
            Server::TYPE_VLESS => $this->buildVlessOutbound($server, $tag),
            Server::TYPE_VMESS => $this->buildVmessOutbound($server, $tag),
            Server::TYPE_TROJAN => $this->buildTrojanOutbound($server, $tag),
            Server::TYPE_SHADOWSOCKS => $this->buildShadowsocksOutbound($server, $tag),
            Server::TYPE_SOCKS => $this->buildSocksOutbound($server, $tag),
            Server::TYPE_HTTP => $this->buildHttpOutbound($server, $tag),
            Server::TYPE_HYSTERIA => $this->buildHysteria2Outbound($server, $tag),
            default => null,
        };
    }

    private function buildVlessOutbound(array $server, string $tag): array
    {
        $protocolSettings = (array) data_get($server, 'protocol_settings', []);

        $user = [
            'encryption' => data_get($protocolSettings, 'encryption.enabled')
                ? (string) data_get($protocolSettings, 'encryption.encryption', 'none')
                : 'none',
            'id' => (string) data_get($server, 'password', ''),
            'level' => 8,
        ];

        $flow = trim((string) data_get($protocolSettings, 'flow', ''));
        if ($flow !== '') {
            $user['flow'] = $flow;
        }

        return [
            'protocol' => 'vless',
            'settings' => [
                'vnext' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'port' => (int) data_get($server, 'port', 443),
                        'users' => [$user],
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server),
            'tag' => $tag,
        ];
    }

    private function buildVmessOutbound(array $server, string $tag): array
    {
        $protocolSettings = (array) data_get($server, 'protocol_settings', []);

        return [
            'protocol' => 'vmess',
            'settings' => [
                'vnext' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'port' => (int) data_get($server, 'port', 443),
                        'users' => [
                            [
                                'alterId' => 0,
                                'id' => (string) data_get($server, 'password', ''),
                                'level' => 8,
                                'security' => (string) data_get(
                                    $protocolSettings,
                                    'security',
                                    'auto'
                                ),
                            ],
                        ],
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server),
            'tag' => $tag,
        ];
    }

    private function buildTrojanOutbound(array $server, string $tag): array
    {
        return [
            'protocol' => 'trojan',
            'settings' => [
                'servers' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'level' => 8,
                        'password' => (string) data_get($server, 'password', ''),
                        'port' => (int) data_get($server, 'port', 443),
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server, true),
            'tag' => $tag,
        ];
    }

    private function buildShadowsocksOutbound(array $server, string $tag): array
    {
        $protocolSettings = (array) data_get($server, 'protocol_settings', []);

        return [
            'protocol' => 'shadowsocks',
            'settings' => [
                'servers' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'level' => 8,
                        'method' => (string) data_get($protocolSettings, 'cipher', ''),
                        'password' => (string) data_get($server, 'password', ''),
                        'port' => (int) data_get($server, 'port', 443),
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server),
            'tag' => $tag,
        ];
    }

    private function buildSocksOutbound(array $server, string $tag): array
    {
        $password = (string) data_get($server, 'password', '');

        return [
            'protocol' => 'socks',
            'settings' => [
                'servers' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'port' => (int) data_get($server, 'port', 1080),
                        'users' => $password === '' ? [] : [
                            [
                                'level' => 8,
                                'pass' => $password,
                                'user' => $password,
                            ],
                        ],
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server),
            'tag' => $tag,
        ];
    }

    private function buildHttpOutbound(array $server, string $tag): array
    {
        $password = (string) data_get($server, 'password', '');

        return [
            'protocol' => 'http',
            'settings' => [
                'servers' => [
                    [
                        'address' => (string) data_get($server, 'host', ''),
                        'port' => (int) data_get($server, 'port', 8080),
                        'users' => $password === '' ? [] : [
                            [
                                'level' => 8,
                                'pass' => $password,
                                'user' => $password,
                            ],
                        ],
                    ],
                ],
            ],
            'streamSettings' => $this->buildCommonStreamSettings($server),
            'tag' => $tag,
        ];
    }

    /**
     * Hysteria2 support requires a recent Xray core (2026 builds).
     */
    private function buildHysteria2Outbound(array $server, string $tag): array
    {
        $protocolSettings = (array) data_get($server, 'protocol_settings', []);

        $serverName = $this->firstString(
            data_get($protocolSettings, 'tls.server_name')
        );

        $streamSettings = [
            'method' => 'hysteria',
            'security' => 'tls',
            'hysteriaSettings' => [
                'auth' => (string) data_get($server, 'password', ''),
                'udpIdleTimeout' => 60,
                'version' => 2,
            ],
            'sockopt' => [
                'domainStrategy' => 'UseIPv4',
            ],
            'tlsSettings' => $this->removeEmptyStrings([
                'alpn' => ['h3'],
                'fingerprint' => 'chrome',
                'serverName' => $serverName,
            ]),
        ];

        return [
            'protocol' => 'hysteria',
            'settings' => [
                'address' => (string) data_get($server, 'host', ''),
                'port' => (int) data_get($server, 'port', 443),
                'version' => 2,
            ],
            'streamSettings' => $streamSettings,
            'tag' => $tag,
        ];
    }

    private function buildCommonStreamSettings(
        array $server,
        bool $forceTlsForTrojan = false
    ): array {
        $protocolSettings = (array) data_get($server, 'protocol_settings', []);
        $network = (string) data_get($protocolSettings, 'network', 'tcp');

        $streamSettings = [
            'network' => $network,
            'security' => 'none',
            'sockopt' => [
                'domainStrategy' => 'UseIPv4',
            ],
        ];

        $tlsMode = (int) data_get($protocolSettings, 'tls', $forceTlsForTrojan ? 1 : 0);

        if ($tlsMode === 1 || ($forceTlsForTrojan && $tlsMode !== 2)) {
            $streamSettings['security'] = 'tls';

            $tlsSettings = [
                'fingerprint' => Helper::getTlsFingerprint(
                    data_get($protocolSettings, 'utls')
                ) ?: 'chrome',
                'serverName' => $this->firstString(
                    data_get($protocolSettings, 'tls_settings.server_name')
                ),
            ];

            $alpn = $this->normalizeStringList(
                data_get($protocolSettings, 'tls_settings.alpn')
            );

            if ($alpn === []) {
                $alpn = match ($network) {
                    'ws', 'httpupgrade' => [],
                    'grpc', 'h2', 'http' => ['h2'],
                    default => ['h2', 'http/1.1'],
                };
            }

            if ($alpn !== []) {
                $tlsSettings['alpn'] = $alpn;
            }

            $pinnedPeerCertSha256 =
                data_get($protocolSettings, 'tls_settings.pinnedPeerCertSha256')
                ?? data_get($protocolSettings, 'tls_settings.pinned_peer_cert_sha256')
                ?? data_get($protocolSettings, 'tls_settings.pcs')
                ?? data_get($protocolSettings, 'network_settings.pinnedPeerCertSha256')
                ?? data_get($protocolSettings, 'network_settings.pinned_peer_cert_sha256')
                ?? data_get($protocolSettings, 'network_settings.pcs');

            if (is_string($pinnedPeerCertSha256) && trim($pinnedPeerCertSha256) !== '') {
                $tlsSettings['pinnedPeerCertSha256'] = trim($pinnedPeerCertSha256);
            }

            $verifyPeerCertByName =
                data_get($protocolSettings, 'tls_settings.verifyPeerCertByName')
                ?? data_get($protocolSettings, 'tls_settings.verify_peer_cert_by_name')
                ?? data_get($protocolSettings, 'tls_settings.vcn')
                ?? data_get($protocolSettings, 'tls_settings.pcn')
                ?? data_get($protocolSettings, 'network_settings.verifyPeerCertByName')
                ?? data_get($protocolSettings, 'network_settings.verify_peer_cert_by_name')
                ?? data_get($protocolSettings, 'network_settings.vcn')
                ?? data_get($protocolSettings, 'network_settings.pcn');

            if (is_string($verifyPeerCertByName) && trim($verifyPeerCertByName) !== '') {
                $tlsSettings['verifyPeerCertByName'] = trim($verifyPeerCertByName);
            }

            $streamSettings['tlsSettings'] = $this->removeEmptyStrings($tlsSettings);
        } elseif ($tlsMode === 2) {
            $streamSettings['security'] = 'reality';

            $streamSettings['realitySettings'] = $this->removeEmptyStrings([
                'fingerprint' => Helper::getTlsFingerprint(
                    data_get($protocolSettings, 'utls')
                ) ?: 'chrome',
                'publicKey' => $this->firstString(
                    data_get($protocolSettings, 'reality_settings.public_key')
                ),
                'serverName' => $this->firstString(
                    data_get($protocolSettings, 'reality_settings.server_name')
                ),
                'shortId' => $this->firstString(
                    data_get($protocolSettings, 'reality_settings.short_id')
                ),
                'spiderX' => (string) data_get(
                    $protocolSettings,
                    'reality_settings.spider_x',
                    '/'
                ),
            ]);
        }

        $this->applyTransportSettings(
            $streamSettings,
            $protocolSettings,
            $network,
            (string) data_get($server, 'host', '')
        );

        return $streamSettings;
    }

    private function applyTransportSettings(
        array &$streamSettings,
        array $protocolSettings,
        string $network,
        string $serverHost
    ): void {
        $networkSettings = (array) data_get(
            $protocolSettings,
            'network_settings',
            []
        );

        switch ($network) {
            case 'ws':
                $wsSettings = [
                    'path' => (string) data_get($networkSettings, 'path', '/'),
                ];

                $host = $this->firstString(
                    data_get($networkSettings, 'headers.Host')
                );

                if ($host !== '') {
                    $wsSettings['headers'] = ['Host' => $host];
                }

                $streamSettings['wsSettings'] = $wsSettings;
                break;

            case 'grpc':
                $streamSettings['grpcSettings'] = $this->removeEmptyStrings([
                    'authority' => $this->firstString(
                        data_get($networkSettings, 'authority')
                    ),
                    'multiMode' => (bool) data_get(
                        $networkSettings,
                        'multiMode',
                        false
                    ),
                    'serviceName' => (string) data_get(
                        $networkSettings,
                        'serviceName',
                        ''
                    ),
                ]);
                break;

            case 'xhttp':
                $xhttpSettings = [
                    'host' => $this->firstString(
                        data_get($networkSettings, 'host', '')
                    ),
                    'mode' => (string) data_get(
                        $networkSettings,
                        'mode',
                        'auto'
                    ),
                    'path' => (string) data_get(
                        $networkSettings,
                        'path',
                        '/'
                    ),
                ];

                $extra = data_get($networkSettings, 'extra');
                if (is_string($extra) && trim($extra) !== '') {
                    $decoded = json_decode($extra, true);
                    $extra = json_last_error() === JSON_ERROR_NONE
                        ? $decoded
                        : $extra;
                }

                if ($extra !== null && $extra !== [] && $extra !== '') {
                    $xhttpSettings['extra'] = $extra;
                }

                $streamSettings['xhttpSettings'] =
                    $this->removeEmptyStrings($xhttpSettings);
                break;

            case 'httpupgrade':
                $streamSettings['httpupgradeSettings'] =
                    $this->removeEmptyStrings([
                        'host' => $this->firstString(
                            data_get($networkSettings, 'host', $serverHost)
                        ),
                        'path' => (string) data_get(
                            $networkSettings,
                            'path',
                            '/'
                        ),
                    ]);
                break;

            case 'kcp':
                $streamSettings['kcpSettings'] = [
                    'header' => [
                        'type' => (string) data_get(
                            $networkSettings,
                            'header.type',
                            'none'
                        ),
                    ],
                    'seed' => (string) data_get(
                        $networkSettings,
                        'seed',
                        ''
                    ),
                ];
                break;

            case 'h2':
            case 'http':
                $streamSettings['httpSettings'] =
                    $this->removeEmptyStrings([
                        'host' => $this->normalizeStringList(
                            data_get($networkSettings, 'host')
                        ),
                        'path' => (string) data_get(
                            $networkSettings,
                            'path',
                            '/'
                        ),
                    ]);
                break;

            case 'quic':
                $streamSettings['quicSettings'] = $this->removeEmptyStrings([
                    'header' => [
                        'type' => (string) data_get($networkSettings, 'header.type', 'none'),
                    ],
                    'key' => (string) data_get($networkSettings, 'key', ''),
                    'security' => (string) data_get($networkSettings, 'security', 'none'),
                ]);
                break;

            case 'tcp':
            default:
                $headerType = (string) data_get(
                    $networkSettings,
                    'header.type',
                    'none'
                );

                if ($headerType !== '' && $headerType !== 'none') {
                    $streamSettings['tcpSettings'] = [
                        'header' => [
                            'type' => $headerType,
                            'request' => data_get(
                                $networkSettings,
                                'header.request',
                                []
                            ),
                            'response' => data_get(
                                $networkSettings,
                                'header.response',
                                []
                            ),
                        ],
                    ];
                }
                break;
        }
    }

    private function appendDnsDomain(array &$domains, array $server): void
    {
        $host = trim((string) data_get($server, 'host', ''));

        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $domains[] = 'full:' . strtolower($host);
        }
    }

    private function buildDns(array $domains): array
    {
        return [
            'disableFallbackIfMatch' => true,
            'queryStrategy' => 'UseIPv4',
            'servers' => [
                [
                    'address' => 'localhost',
                    'domains' => array_values(array_unique($domains)),
                    'finalQuery' => true,
                    'queryStrategy' => 'UseIPv4',
                    'skipFallback' => true,
                ],
            ],
        ];
    }

    private function buildFakeDns(): array
    {
        return [
            [
                'ipPool' => '198.20.0.0/15',
                'poolSize' => 128,
            ],
            [
                'ipPool' => 'fc00::/64',
                'poolSize' => 128,
            ],
        ];
    }

    private function buildInbounds(): array
    {
        return [
            [
                'listen' => '127.0.0.1',
                'port' => 1080,
                'protocol' => 'socks',
                'settings' => [
                    'auth' => 'noauth',
                    'udp' => true,
                ],
                'sniffing' => [
                    'destOverride' => ['http', 'tls', 'quic'],
                    'enabled' => true,
                    'routeOnly' => true,
                ],
                'tag' => 'socks-in',
            ],
        ];
    }

    private function buildDirectOutbound(): array
    {
        return [
            'protocol' => 'freedom',
            'settings' => [
                'domainStrategy' => 'UseIPv4',
            ],
            'tag' => 'direct',
        ];
    }

    private function buildBlockOutbound(): array
    {
        return [
            'protocol' => 'blackhole',
            'settings' => [
                'response' => [
                    'type' => 'http',
                ],
            ],
            'tag' => 'block',
        ];
    }

    private function buildDnsOutbound(): array
    {
        return [
            'protocol' => 'dns',
            'tag' => 'dns-out',
        ];
    }

    private function buildSubscriptionUserInfo(): string
    {
        $parts = [
            'upload=' . (int) data_get($this->user, 'u', 0),
            'download=' . (int) data_get($this->user, 'd', 0),
            'total=' . (int) data_get($this->user, 'transfer_enable', 0),
        ];

        $expiredAt = (int) data_get($this->user, 'expired_at', 0);
        if ($expiredAt > 0) {
            $parts[] = 'expire=' . $expiredAt;
        }

        return implode('; ', $parts);
    }

    private function makeTagSuffix(array $server, int $index): string
    {
        $raw = (string) (
            data_get($server, 'id')
            ?? data_get($server, 'server_id')
            ?? ($index + 1)
        );

        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $raw) ?: '';
        $safe = trim($safe, '-');

        return $safe !== '' ? $safe : (string) ($index + 1);
    }

    private function firstString(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value)) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn ($item): string =>
                    is_scalar($item) ? trim((string) $item) : '',
                $value
            ),
            static fn (string $item): bool => $item !== ''
        ));
    }

    private function removeEmptyStrings(array $values): array
    {
        return array_filter(
            $values,
            static fn ($value): bool =>
                $value !== null
                && $value !== ''
                && $value !== []
        );
    }
}
