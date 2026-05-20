<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\DeviceInfo;

/**
 * Device detection service.
 *
 * Parses User-Agent strings to extract device type, browser,
 * and operating system information using regex patterns.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class DeviceDetector
{
	/**
	 * Bot patterns for detection.
	 *
	 * @var array<string, string>
	 */
	protected const BOT_PATTERNS = [
		// Search engine crawlers.
		'googlebot'           => 'Googlebot',
		'bingbot'             => 'Bingbot',
		'slurp'               => 'Yahoo! Slurp',
		'duckduckbot'         => 'DuckDuckBot',
		'baiduspider'         => 'Baiduspider',
		'yandexbot'           => 'YandexBot',
		'facebookexternalhit' => 'Facebook',
		'twitterbot'          => 'Twitter',
		'linkedinbot'         => 'LinkedIn',
		'applebot-extended'   => 'Applebot-Extended',
		'applebot'            => 'Applebot',

		// AI training and answer-engine crawlers.
		'gptbot'              => 'GPTBot',
		'chatgpt-user'        => 'ChatGPT-User',
		'oai-searchbot'       => 'OAI-SearchBot',
		'claudebot'           => 'ClaudeBot',
		'claude-web'          => 'Claude-Web',
		'anthropic-ai'        => 'Anthropic',
		'google-extended'     => 'Google-Extended',
		'gemini'              => 'Gemini',
		'amazonbot'           => 'Amazonbot',
		'bytespider'          => 'Bytespider',
		'cohere-ai'           => 'Cohere',
		'ccbot'               => 'CCBot',
		'diffbot'             => 'Diffbot',
		'facebookbot'         => 'FacebookBot',
		'perplexitybot'       => 'PerplexityBot',
		'youbot'              => 'YouBot',

		// SEO and marketing crawlers.
		'semrushbot'          => 'SEMRush',
		'ahrefsbot'           => 'Ahrefs',
		'mj12bot'             => 'Majestic',
		'dotbot'              => 'DotBot',
		'blexbot'             => 'BLEXBot',
		'screaming frog'      => 'Screaming Frog',
		'rogerbot'            => 'Rogerbot',
		'sistrix'             => 'Sistrix',
		'serpstatbot'         => 'Serpstat',
		'dataforseobot'       => 'DataForSeoBot',
		'petalbot'            => 'PetalBot',

		// Scraper, headless, and HTTP client patterns.
		'headlesschrome'      => 'Headless Chrome',
		'phantomjs'           => 'PhantomJS',
		'puppeteer'           => 'Puppeteer',
		'playwright'          => 'Playwright',
		'python-requests'     => 'python-requests',
		'go-http-client'      => 'Go-http-client',
		'java/'               => 'Java',
		'libwww-perl'         => 'libwww-perl',
		'curl/'               => 'curl',
		'wget/'               => 'Wget',
		'httpx'               => 'httpx',
		'aiohttp'             => 'aiohttp',
		'scrapy'              => 'Scrapy',

		// Regional crawlers.
		'sogou'               => 'Sogou',
		'yisouspider'         => 'Yisou',
		'360spider'           => '360Spider',
		'seznambot'           => 'SeznamBot',
		'qwantify'            => 'Qwant',
		'naverbot'            => 'NaverBot',
		'yeti'                => 'NaverBot',
		'daumoa'              => 'Daum',

		// Generic fallbacks (kept last so named bots match first).
		'bot'                 => 'Bot',
		'crawler'             => 'Crawler',
		'spider'              => 'Spider',
		'scraper'             => 'Scraper',
	];

	/**
	 * Browser patterns for detection.
	 *
	 * @var array<string, string>
	 */
	protected const BROWSER_PATTERNS = [
		'edg'          => 'Edge',
		'opr|opera'    => 'Opera',
		'chrome'       => 'Chrome',
		'safari'       => 'Safari',
		'firefox'      => 'Firefox',
		'msie|trident' => 'Internet Explorer',
		'brave'        => 'Brave',
		'vivaldi'      => 'Vivaldi',
		'samsung'      => 'Samsung Browser',
		'ucbrowser'    => 'UC Browser',
	];

	/**
	 * OS patterns for detection.
	 *
	 * @var array<string, string>
	 */
	protected const OS_PATTERNS = [
		'windows nt 10'    => 'Windows 10',
		'windows nt 6.3'   => 'Windows 8.1',
		'windows nt 6.2'   => 'Windows 8',
		'windows nt 6.1'   => 'Windows 7',
		'windows'          => 'Windows',
		'mac os x'         => 'macOS',
		'macintosh'        => 'macOS',
		'android'          => 'Android',
		'iphone|ipad|ipod' => 'iOS',
		'linux'            => 'Linux',
		'ubuntu'           => 'Ubuntu',
		'chrome os'        => 'Chrome OS',
	];

	/**
	 * Parse a User-Agent string.
	 *
	 * @param string|null $userAgent The User-Agent string to parse.
	 *
	 * @return DeviceInfo
	 *
	 * @since 1.0.0
	 */
	public function parse( ?string $userAgent ): DeviceInfo
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return DeviceInfo::unknown();
		}

		$userAgentLower = strtolower( $userAgent );

		// Check for bots first
		if ( $this->isBot( $userAgent ) ) {
			return DeviceInfo::bot( $this->detectBotName( $userAgentLower ) );
		}

		return new DeviceInfo(
			deviceType: $this->detectDeviceType( $userAgentLower ),
			browser: $this->detectBrowser( $userAgentLower ),
			browserVersion: $this->detectBrowserVersion( $userAgent ),
			os: $this->detectOs( $userAgentLower ),
			osVersion: $this->detectOsVersion( $userAgent ),
			isBot: false,
		);
	}

	/**
	 * Get the device type from a User-Agent.
	 *
	 * @param string|null $userAgent The User-Agent string.
	 *
	 * @return string The device type (desktop, mobile, tablet).
	 *
	 * @since 1.0.0
	 */
	public function getDeviceType( ?string $userAgent ): string
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return 'other';
		}

		return $this->detectDeviceType( strtolower( $userAgent ) );
	}

	/**
	 * Get browser name and version.
	 *
	 * @param string|null $userAgent The User-Agent string.
	 *
	 * @return array{name: string|null, version: string|null}
	 *
	 * @since 1.0.0
	 */
	public function getBrowser( ?string $userAgent ): array
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return [ 'name' => null, 'version' => null ];
		}

		return [
			'name'    => $this->detectBrowser( strtolower( $userAgent ) ),
			'version' => $this->detectBrowserVersion( $userAgent ),
		];
	}

	/**
	 * Get operating system name and version.
	 *
	 * @param string|null $userAgent The User-Agent string.
	 *
	 * @return array{name: string|null, version: string|null}
	 *
	 * @since 1.0.0
	 */
	public function getOperatingSystem( ?string $userAgent ): array
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return [ 'name' => null, 'version' => null ];
		}

		return [
			'name'    => $this->detectOs( strtolower( $userAgent ) ),
			'version' => $this->detectOsVersion( $userAgent ),
		];
	}

	/**
	 * Check if the User-Agent is a bot/crawler.
	 *
	 * @param string|null $userAgent The User-Agent string.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isBot( ?string $userAgent ): bool
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return false;
		}

		$userAgentLower = strtolower( $userAgent );

		foreach ( array_keys( self::BOT_PATTERNS ) as $pattern ) {
			if ( str_contains( $userAgentLower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect the device type.
	 *
	 * @param string $userAgentLower Lowercase User-Agent string.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function detectDeviceType( string $userAgentLower ): string
	{
		// Check for tablets first (before mobile)
		if ( preg_match( '/tablet|ipad|playbook|silk/i', $userAgentLower ) ) {
			return 'tablet';
		}

		// Check for mobile devices
		if ( preg_match( '/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $userAgentLower ) ) {
			// Android tablets often have 'android' but not 'mobile'
			if ( str_contains( $userAgentLower, 'android' ) && ! str_contains( $userAgentLower, 'mobile' ) ) {
				return 'tablet';
			}

			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Detect the browser name.
	 *
	 * @param string $userAgentLower Lowercase User-Agent string.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function detectBrowser( string $userAgentLower ): ?string
	{
		// Edge must be checked before Chrome
		if ( str_contains( $userAgentLower, 'edg' ) ) {
			return 'Edge';
		}

		// Opera must be checked before Chrome
		if ( str_contains( $userAgentLower, 'opr' ) || str_contains( $userAgentLower, 'opera' ) ) {
			return 'Opera';
		}

		// Chrome must be checked before Safari
		if ( str_contains( $userAgentLower, 'chrome' ) || str_contains( $userAgentLower, 'crios' ) ) {
			return 'Chrome';
		}

		// Safari
		if ( str_contains( $userAgentLower, 'safari' ) ) {
			return 'Safari';
		}

		// Firefox
		if ( str_contains( $userAgentLower, 'firefox' ) || str_contains( $userAgentLower, 'fxios' ) ) {
			return 'Firefox';
		}

		// Internet Explorer
		if ( str_contains( $userAgentLower, 'msie' ) || str_contains( $userAgentLower, 'trident' ) ) {
			return 'Internet Explorer';
		}

		// Samsung Browser
		if ( str_contains( $userAgentLower, 'samsungbrowser' ) ) {
			return 'Samsung Browser';
		}

		return null;
	}

	/**
	 * Detect the browser version.
	 *
	 * @param string $userAgent The User-Agent string (original case).
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function detectBrowserVersion( string $userAgent ): ?string
	{
		$patterns = [
			'/Edg\/([0-9.]+)/',
			'/OPR\/([0-9.]+)/',
			'/Chrome\/([0-9.]+)/',
			'/Version\/([0-9.]+).*Safari/',
			'/Firefox\/([0-9.]+)/',
			'/MSIE ([0-9.]+)/',
			'/rv:([0-9.]+)/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $userAgent, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
	}

	/**
	 * Detect the operating system.
	 *
	 * @param string $userAgentLower Lowercase User-Agent string.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function detectOs( string $userAgentLower ): ?string
	{
		// iOS must be checked before macOS
		if ( preg_match( '/iphone|ipad|ipod/i', $userAgentLower ) ) {
			return 'iOS';
		}

		if ( str_contains( $userAgentLower, 'android' ) ) {
			return 'Android';
		}

		if ( str_contains( $userAgentLower, 'windows' ) ) {
			return 'Windows';
		}

		if ( str_contains( $userAgentLower, 'mac os x' ) || str_contains( $userAgentLower, 'macintosh' ) ) {
			return 'macOS';
		}

		if ( str_contains( $userAgentLower, 'linux' ) ) {
			return 'Linux';
		}

		if ( str_contains( $userAgentLower, 'cros' ) ) {
			return 'Chrome OS';
		}

		return null;
	}

	/**
	 * Detect the operating system version.
	 *
	 * @param string $userAgent The User-Agent string (original case).
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function detectOsVersion( string $userAgent ): ?string
	{
		$patterns = [
			'/Windows NT ([0-9.]+)/',
			'/Mac OS X ([0-9_]+)/',
			'/Android ([0-9.]+)/',
			'/iPhone OS ([0-9_]+)/',
			'/CPU OS ([0-9_]+)/',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $userAgent, $matches ) ) {
				return str_replace( '_', '.', $matches[1] );
			}
		}

		return null;
	}

	/**
	 * Detect the bot name.
	 *
	 * @param string $userAgentLower Lowercase User-Agent string.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function detectBotName( string $userAgentLower ): ?string
	{
		foreach ( self::BOT_PATTERNS as $pattern => $name ) {
			if ( str_contains( $userAgentLower, $pattern ) ) {
				return $name;
			}
		}

		return 'Unknown Bot';
	}
}
