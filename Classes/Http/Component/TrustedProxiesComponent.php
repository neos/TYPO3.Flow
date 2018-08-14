<?php
namespace Neos\Flow\Http\Component;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Utility\Ip as IpUtility;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP component that checks request headers against a configured list of trusted proxy IP addresses.
 */
class TrustedProxiesComponent implements ComponentInterface
{
    const HEADER_CLIENT_IP = 'clientIp';
    const HEADER_HOST = 'host';
    const HEADER_PORT = 'port';
    const HEADER_PROTOCOL = 'proto';

    // Patterns for Forwarded headers, according to https://tools.ietf.org/html/rfc7239#section-4
    const FOR_PATTERN = '(?:for=(?<for>"[^"]+"|[0-9a-z_\.:\-]+))';
    const PROTO_PATTERN = '(?:proto=(?<proto>[a-z][a-z0-9+\.\-]+))';
    const HOST_PATTERN = '(?:host=(?<host>"[^"]+"|[0-9a-z_\.:\-]+))';

    /**
     * @Flow\InjectConfiguration("http.trustedProxies")
     * @var array
     */
    protected $settings;

    /**
     * @param ComponentContext $componentContext
     * @return void
     * @api
     */
    public function handle(ComponentContext $componentContext)
    {
        $request = $componentContext->getHttpRequest();

        $trustedRequest = $request->withAttribute(Request::ATTRIBUTE_TRUSTED_PROXY, $this->isFromTrustedProxy($request));

        $trustedRequest = $trustedRequest->withAttribute(Request::ATTRIBUTE_CLIENT_IP, $this->getTrustedClientIpAddress($trustedRequest));

        $protocolHeader = $this->getFirstTrustedProxyHeaderValue(self::HEADER_PROTOCOL, $trustedRequest);
        if ($protocolHeader !== null) {
            $trustedRequest = $trustedRequest->withUri($trustedRequest->getUri()->withScheme($protocolHeader), true);
        }

        $hostHeader = $this->getFirstTrustedProxyHeaderValue(self::HEADER_HOST, $trustedRequest);
        $portFromHost = null;
        if ($hostHeader !== null) {
            if (strpos($hostHeader, '[') === 0 && strrpos($hostHeader, ']') !== false) {
                $portSeparatorIndex = strrpos($hostHeader, ':', -strrpos($hostHeader, ']'));
            } else {
                $portSeparatorIndex = strrpos($hostHeader, ':');
            }
            if ($portSeparatorIndex !== false) {
                $portFromHost = substr($hostHeader, $portSeparatorIndex + 1);
                $trustedRequest = $trustedRequest->withUri($trustedRequest->getUri()->withPort($portFromHost), true);
                $hostHeader = substr($hostHeader, 0, $portSeparatorIndex);
            }
            $trustedRequest = $trustedRequest->withUri($trustedRequest->getUri()->withHost($hostHeader), true);
        }

        $portHeader = $this->getFirstTrustedProxyHeaderValue(self::HEADER_PORT, $trustedRequest);
        if ($portHeader !== null) {
            $trustedRequest = $trustedRequest->withUri($trustedRequest->getUri()->withPort($portHeader), true);
        } elseif ($protocolHeader !== null && $portFromHost === null) {
            $trustedRequest = $trustedRequest->withUri($trustedRequest->getUri()->withPort(strtolower($protocolHeader) === 'https' ? 443 : 80), true);
        }

        $componentContext->replaceHttpRequest($trustedRequest);
    }

    /**
     * @param array $array
     * @return array
     */
    protected function unquoteArray($array)
    {
        return array_map(function ($value) {
            return trim($value, '"');
        }, array_values(array_filter($array)));
    }

    /**
     * @param string $type The header value type to retrieve from the Forwarded header value. One of the HEADER_* constants.
     * @param string $headerValue The Forwarded header value, e.g. "for=192.168.178.5; host=www.acme.org:8080"
     * @return array|null The array of values for the header type or null if the header
     */
    protected function getForwardedHeader($type, $headerValue)
    {
        $patterns = [
            self::HEADER_CLIENT_IP => self::FOR_PATTERN,
            self::HEADER_HOST => self::HOST_PATTERN,
            self::HEADER_PROTOCOL => self::PROTO_PATTERN
        ];
        if (!isset($patterns[$type])) {
            return null;
        }
        preg_match_all('/' . $patterns[$type] . '/i', $headerValue, $matches);
        $matchedHeader = $this->unquoteArray($matches[1]);
        if ($matchedHeader === []) {
            return null;
        }
        return $matchedHeader;
    }

    /**
     * Get the values of trusted proxy header.
     *
     * @param string $type One of the HEADER_* constants
     * @param ServerRequestInterface $request The request to get the trusted proxy header from
     * @return \Iterator An array of the values for this header type or NULL if this header type should not be trusted
     */
    protected function getTrustedProxyHeaderValues($type, ServerRequestInterface $request)
    {
        if (isset($this->settings['headers']) && is_string($this->settings['headers'])) {
            $trustedHeaders = $this->settings['headers'];
        } else {
            $trustedHeaders = $this->settings['headers'][$type] ?? '';
        }
        if ($trustedHeaders === '' || !$request->getAttribute(Request::ATTRIBUTE_TRUSTED_PROXY)) {
            yield null;
            return;
        }
        $trustedHeaders = array_map('trim', explode(',', $trustedHeaders));

        foreach ($trustedHeaders as $trustedHeader) {
            if (!$request->hasHeader($trustedHeader)) {
                continue;
            }
            if (strtolower($trustedHeader) === 'forwarded') {
                $forwardedHeaderValue = $this->getForwardedHeader($type, $request->getHeader($trustedHeader));
                if ($forwardedHeaderValue !== null) {
                    yield $forwardedHeaderValue;
                }
            } else {
                yield array_map('trim', explode(',', $request->getHeader($trustedHeader)));
            }
        }

        yield null;
    }

    /**
     * Convenience getter for the first value of a given trusted proxy header.
     *
     * @param string $type One of the HEADER_* constants
     * @param Request $request The request to get the trusted proxy header from
     * @return mixed|null The first value of this header type or NULL if this header type should not be trusted
     */
    protected function getFirstTrustedProxyHeaderValue($type, Request $request)
    {
        $values = $this->getTrustedProxyHeaderValues($type, $request)->current();
        return $values !== null ? reset($values) : null;
    }

    /**
     * Check if the given IP address is from a trusted proxy.
     *
     * @param string $ipAddress
     * @return bool
     */
    protected function ipIsTrustedProxy($ipAddress)
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $allowedProxies = $this->settings['proxies'];
        if ($allowedProxies === '*') {
            return true;
        }
        if (is_string($allowedProxies)) {
            $allowedProxies = array_map('trim', explode(',', $allowedProxies));
        }
        if (!is_array($allowedProxies)) {
            return false;
        }
        foreach ($allowedProxies as $ipPattern) {
            if (IpUtility::cidrMatch($ipAddress, $ipPattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the given request is from a trusted proxy.
     *
     * @param Request $request
     * @return bool If the server REMOTE_ADDR is from a trusted proxy
     */
    protected function isFromTrustedProxy(Request $request)
    {
        $server = $request->getServerParams();
        if (!isset($server['REMOTE_ADDR'])) {
            return false;
        }
        return $this->ipIsTrustedProxy($server['REMOTE_ADDR']);
    }

    /**
     * Get the most trusted client's IP address.
     *
     * This is the right-most address in the trusted client IP header, that is not a trusted proxy address.
     * If all proxies are trusted, this is the left-most address in the header.
     * If no proxies are trusted or no client IP header is trusted, this is the remote address of the machine
     * directly connected to the server.
     *
     * @param ServerRequestInterface $request
     * @return string|bool The most trusted client's IP address or FALSE if no remote address can be found
     */
    protected function getTrustedClientIpAddress(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        if (!isset($server['REMOTE_ADDR'])) {
            return false;
        }

        $ipAddress = $server['REMOTE_ADDR'];
        $trustedIpHeaders = $this->getTrustedProxyHeaderValues(self::HEADER_CLIENT_IP, $request);
        $trustedIpHeader = [];
        while ($trustedIpHeaders->valid()) {
            $trustedIpHeader = $trustedIpHeaders->current();
            if ($trustedIpHeader === null || empty($this->settings['proxies'])) {
                return $server['REMOTE_ADDR'];
            }
            $ipAddress = reset($trustedIpHeader);
            if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE) !== false) {
                break;
            }
            $trustedIpHeaders->next();
        }

        if ($this->settings['proxies'] === '*') {
            return $ipAddress;
        }

        $ipAddress = false;
        foreach (array_reverse($trustedIpHeader) as $headerIpAddress) {
            $portPosition = strpos($headerIpAddress, ':');
            $ipAddress = $portPosition !== false ? substr($headerIpAddress, 0, $portPosition) : $headerIpAddress;
            if (!$this->ipIsTrustedProxy($ipAddress)) {
                break;
            }
        }

        return $ipAddress;
    }
}
