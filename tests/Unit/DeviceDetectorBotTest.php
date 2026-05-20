<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Services\DeviceDetector;

beforeEach( function (): void {
	$this->detector = new DeviceDetector();
} );

test( 'recognises 50 or more bot patterns', function (): void {
	$reflection = new ReflectionClass( DeviceDetector::class );
	$patterns   = $reflection->getConstant( 'BOT_PATTERNS' );

	expect( count( $patterns ) )->toBeGreaterThanOrEqual( 50 );
} );

test( 'detects AI training and answer-engine crawlers', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeTrue();
} )->with( [
	'GPTBot'          => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
	'ChatGPT-User'    => 'Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)',
	'OAI-SearchBot'   => 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)',
	'ClaudeBot'       => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
	'Claude-Web'      => 'Mozilla/5.0 (compatible; Claude-Web/1.0)',
	'anthropic-ai'    => 'anthropic-ai/1.0',
	'Google-Extended' => 'Mozilla/5.0 (compatible; Google-Extended)',
	'Amazonbot'       => 'Mozilla/5.0 (compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)',
	'Bytespider'      => 'Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)',
	'Cohere'          => 'cohere-ai/1.0',
	'CCBot'           => 'CCBot/2.0 (https://commoncrawl.org/faq/)',
	'Diffbot'         => 'Mozilla/5.0 (compatible; Diffbot/0.1; +http://www.diffbot.com)',
	'PerplexityBot'   => 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/bot)',
	'YouBot'          => 'Mozilla/5.0 (compatible; YouBot (+http://www.you.com))',
] );

test( 'detects SEO and marketing crawlers', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeTrue();
} )->with( [
	'SemrushBot'     => 'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)',
	'AhrefsBot'      => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
	'MJ12bot'        => 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)',
	'DotBot'         => 'Mozilla/5.0 (compatible; DotBot/1.2; +https://opensiteexplorer.org/dotbot)',
	'BLEXBot'        => 'Mozilla/5.0 (compatible; BLEXBot/1.0; +http://webmeup-crawler.com/)',
	'Screaming Frog' => 'Screaming Frog SEO Spider/18.0',
	'Rogerbot'       => 'rogerbot/1.0 (http://moz.com/help/pro/what-is-rogerbot-)',
	'Sistrix'        => 'Mozilla/5.0 (compatible; SISTRIX Crawler; http://crawler.sistrix.net/)',
	'DataForSeoBot'  => 'Mozilla/5.0 (compatible; DataForSeoBot/1.0; +https://dataforseo.com/dataforseo-bot)',
] );

test( 'detects scraper, headless, and HTTP client patterns', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeTrue();
} )->with( [
	'HeadlessChrome'  => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36',
	'PhantomJS'       => 'Mozilla/5.0 (Unknown; Linux x86_64) AppleWebKit/534.34 (KHTML, like Gecko) PhantomJS/2.1.1 Safari/534.34',
	'Puppeteer'       => 'Mozilla/5.0 Puppeteer',
	'Playwright'      => 'Mozilla/5.0 Playwright/1.40',
	'python-requests' => 'python-requests/2.31.0',
	'Go-http-client'  => 'Go-http-client/2.0',
	'Java'            => 'Java/17.0.1',
	'libwww-perl'     => 'libwww-perl/6.67',
	'curl'            => 'curl/8.4.0',
	'Wget'            => 'Wget/1.21.4',
	'httpx'           => 'python-httpx/0.27.0',
	'aiohttp'         => 'Python/3.11 aiohttp/3.9.1',
	'Scrapy'          => 'Scrapy/2.11 (+https://scrapy.org)',
] );

test( 'detects regional crawlers', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeTrue();
} )->with( [
	'Sogou'     => 'Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm)',
	'Yisou'     => 'Mozilla/5.0 (compatible; YisouSpider/1.0; +http://www.yisou.com)',
	'360Spider' => 'Mozilla/5.0 (compatible; 360Spider; http://www.so.com/help/help_3_2.html)',
	'SeznamBot' => 'Mozilla/5.0 (compatible; SeznamBot/3.2; +http://napoveda.seznam.cz/seznambot-intro/)',
	'Qwant'     => 'Mozilla/5.0 (compatible; Qwantify/1.0; +https://www.qwant.com/)',
	'NaverBot'  => 'Mozilla/5.0 (compatible; Yeti/1.1; +http://naver.me/spd)',
	'Daum'      => 'Mozilla/5.0 (compatible; Daumoa/4.0; +http://cs.daum.net/faq/15/4118.html)',
] );

test( 'still detects legacy bot patterns', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeTrue();
} )->with( [
	'Googlebot'   => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
	'Bingbot'     => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
	'Baiduspider' => 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)',
	'Applebot'    => 'Mozilla/5.0 (compatible; Applebot/0.1; +http://www.apple.com/go/applebot)',
] );

test( 'does not flag legitimate browser user agents as bots', function ( string $userAgent ): void {
	expect( $this->detector->isBot( $userAgent ) )->toBeFalse();
} )->with( [
	'Chrome on Windows'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
	'Safari on macOS'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
	'Firefox on Linux'   => 'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
	'Safari on iPhone'   => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
	'Edge on Windows'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
] );

test( 'parse returns a bot device info for AI crawlers', function (): void {
	$info = $this->detector->parse( 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' );

	expect( $info->isBot )->toBeTrue();
} );
