<?php
/**
 * Static registry of known third-party trackers and the regex / cookie
 * fingerprints used to detect them.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrackerRegistry {

	/**
	 * @return array<string, array{name:string,vendor:string,country:string,category:string,consent_required:bool,patterns:array<int,string>,cookies:array<int,string>,docs:string}>
	 */
	public static function all() : array {
		$rows = array(
			'ga4'         => array( 'Google Analytics 4',           'Google LLC',           'US', 'analytics',   true,  array( 'googletagmanager\\.com/gtag/js', 'google-analytics\\.com/g/collect', 'gtag\\(' ),                       array( '_ga', '_ga_', '_gid', '_gat' ),                       'https://policies.google.com/privacy' ),
			'ua'          => array( 'Google Analytics (UA legacy)', 'Google LLC',           'US', 'analytics',   true,  array( 'google-analytics\\.com/analytics\\.js', 'google-analytics\\.com/ga\\.js', 'google-analytics\\.com/collect' ), array( '__utma', '__utmb', '__utmc', '__utmz' ),               'https://policies.google.com/privacy' ),
			'gtm'         => array( 'Google Tag Manager',           'Google LLC',           'US', 'analytics',   true,  array( 'googletagmanager\\.com/gtm\\.js', 'googletagmanager\\.com/ns\\.html' ),                                  array( '_dc_gtm_' ),                                          'https://policies.google.com/privacy' ),
			'gads'        => array( 'Google Ads conversion',        'Google LLC',           'US', 'advertising', true,  array( 'googleadservices\\.com', 'googleads\\.g\\.doubleclick\\.net', 'gtag/js\\?id=AW-' ),                       array( '_gcl_au', '_gcl_aw', '_gac_' ),                       'https://policies.google.com/privacy' ),
			'doubleclick' => array( 'DoubleClick / Google Marketing','Google LLC',           'US', 'advertising', true,  array( 'doubleclick\\.net', 'stats\\.g\\.doubleclick\\.net' ),                                                    array( 'IDE', 'DSID', 'test_cookie' ),                        'https://policies.google.com/privacy' ),
			'fbp'         => array( 'Meta Pixel (Facebook)',        'Meta Platforms',        'US', 'advertising', true,  array( 'connect\\.facebook\\.net/.*/fbevents\\.js', 'fbq\\(' ),                                                     array( '_fbp', '_fbc', 'fr', 'datr' ),                        'https://www.facebook.com/policy.php' ),
			'fbsdk'       => array( 'Facebook SDK',                 'Meta Platforms',        'US', 'social',      true,  array( 'connect\\.facebook\\.net/.*/sdk\\.js' ),                                                                    array( 'fr', 'sb', 'datr' ),                                  'https://www.facebook.com/policy.php' ),
			'instagram'   => array( 'Instagram embed',              'Meta Platforms',        'US', 'social',      true,  array( 'platform\\.instagram\\.com/en_US/embeds\\.js', 'instagram\\.com/embed' ),                                  array(),                                                       'https://www.facebook.com/policy.php' ),
			'linkedin'    => array( 'LinkedIn Insight Tag',         'LinkedIn (Microsoft)',  'US', 'advertising', true,  array( 'snap\\.licdn\\.com/li\\.lms-analytics/insight\\.min\\.js', 'platform\\.linkedin\\.com/in\\.js' ),           array( 'li_sugr', 'lidc', 'bcookie', 'AnalyticsSyncHistory' ), 'https://www.linkedin.com/legal/privacy-policy' ),
			'twitter'     => array( 'Twitter / X widget',           'X Corp',                'US', 'social',      true,  array( 'platform\\.twitter\\.com/widgets\\.js', 'static\\.ads-twitter\\.com/uwt\\.js' ),                            array( 'personalization_id', 'guest_id' ),                    'https://twitter.com/en/privacy' ),
			'tiktok'      => array( 'TikTok pixel',                 'ByteDance',             'CN', 'advertising', true,  array( 'analytics\\.tiktok\\.com/i18n/pixel/events\\.js', 'analytics\\.tiktok\\.com/api' ),                       array( '_ttp', 'ttwid' ),                                     'https://www.tiktok.com/legal/privacy-policy' ),
			'pinterest'   => array( 'Pinterest tag',                'Pinterest',             'US', 'advertising', true,  array( 's\\.pinimg\\.com/ct/core\\.js', 'ct\\.pinterest\\.com' ),                                                  array( '_pin_unauth', '_pinterest_ct' ),                       'https://policy.pinterest.com/' ),
			'snapchat'    => array( 'Snapchat pixel',               'Snap Inc.',             'US', 'advertising', true,  array( 'sc-static\\.net/scevent\\.min\\.js', 'tr\\.snapchat\\.com' ),                                              array( 'sc-cookie', '_scid' ),                                'https://snap.com/en-US/privacy/privacy-policy' ),
			'reddit'      => array( 'Reddit pixel',                 'Reddit Inc.',           'US', 'advertising', true,  array( 'redditstatic\\.com/ads/pixel\\.js', 'events\\.redditmedia\\.com' ),                                        array( 'reddit_session' ),                                    'https://www.reddit.com/policies/privacy-policy' ),
			'bing'        => array( 'Microsoft Advertising (Bing) UET','Microsoft',           'US', 'advertising', true,  array( 'bat\\.bing\\.com/bat\\.js', 'bat\\.bing\\.com/action' ),                                                    array( 'MUID', 'MUIDB' ),                                     'https://privacy.microsoft.com/' ),
			'msclarity'   => array( 'Microsoft Clarity',            'Microsoft',             'US', 'analytics',   true,  array( 'clarity\\.ms/tag/', 'clarity\\(\\s*"' ),                                                                    array( '_clck', '_clsk', 'CLID' ),                            'https://privacy.microsoft.com/' ),
			'hotjar'      => array( 'Hotjar',                       'Contentsquare',         'FR', 'analytics',   true,  array( 'static\\.hotjar\\.com/c/hotjar-', 'script\\.hotjar\\.com', 'hjSetting=' ),                                array( '_hjSessionUser_', '_hjSession_', '_hjid' ),           'https://www.hotjar.com/legal/policies/privacy/' ),
			'crazyegg'    => array( 'Crazy Egg',                    'Crazy Egg',             'US', 'analytics',   true,  array( 'script\\.crazyegg\\.com', 'tracker\\.crazyegg\\.com' ),                                                    array( '_ce.s', '_ce.gtldn' ),                                'https://www.crazyegg.com/privacy' ),
			'fullstory'   => array( 'FullStory',                    'FullStory Inc.',        'US', 'analytics',   true,  array( 'edge\\.fullstory\\.com/s/fs\\.js', 'rs\\.fullstory\\.com' ),                                               array( 'fs_uid' ),                                            'https://www.fullstory.com/legal/privacy-policy/' ),
			'mixpanel'    => array( 'Mixpanel',                     'Mixpanel Inc.',         'US', 'analytics',   true,  array( 'cdn\\.mxpnl\\.com/libs/mixpanel-', 'api\\.mixpanel\\.com/track' ),                                         array( 'mp_' ),                                               'https://mixpanel.com/legal/privacy-policy/' ),
			'amplitude'   => array( 'Amplitude',                    'Amplitude',             'US', 'analytics',   true,  array( 'cdn\\.amplitude\\.com/libs/amplitude', 'api\\.amplitude\\.com/2/httpapi' ),                                array( 'amplitude_id' ),                                      'https://amplitude.com/privacy' ),
			'heap'        => array( 'Heap Analytics',               'Heap Inc.',             'US', 'analytics',   true,  array( 'heapanalytics\\.com/js/heap-' ),                                                                            array( '_hp2_id\\.', '_hp2_ses_props\\.' ),                    'https://www.heap.io/privacy' ),
			'segment'     => array( 'Segment',                      'Twilio',                'US', 'analytics',   true,  array( 'cdn\\.segment\\.com/analytics\\.js', 'api\\.segment\\.io/v1' ),                                            array( 'ajs_anonymous_id', 'ajs_user_id' ),                    'https://segment.com/legal/privacy/' ),
			'optimizely'  => array( 'Optimizely',                   'Optimizely',            'US', 'preferences', true,  array( 'cdn\\.optimizely\\.com/js/' ),                                                                             array( 'optimizelyEndUserId' ),                               'https://www.optimizely.com/privacy/' ),
			'vwo'         => array( 'VWO (Visual Website Optimizer)','VWO',                  'IN', 'preferences', true,  array( 'dev\\.visualwebsiteoptimizer\\.com', 'dev\\.vwo\\.com' ),                                                  array( '_vwo_uuid_v2', '_vis_opt_s' ),                         'https://vwo.com/privacy-policy/' ),
			'criteo'      => array( 'Criteo retargeting',           'Criteo',                'FR', 'advertising', true,  array( 'static\\.criteo\\.net', 'sslwidget\\.criteo\\.com' ),                                                      array( 'uid' ),                                               'https://www.criteo.com/privacy/' ),
			'adroll'      => array( 'AdRoll',                       'NextRoll',              'US', 'advertising', true,  array( 's\\.adroll\\.com', 'a\\.adroll\\.com' ),                                                                   array( '__adroll', '__ar_v4' ),                               'https://www.adroll.com/about/privacy' ),
			'taboola'     => array( 'Taboola',                      'Taboola',               'IL', 'advertising', true,  array( 'cdn\\.taboola\\.com/libtrc' ),                                                                              array( 'taboola_select_user_id' ),                            'https://www.taboola.com/policies/privacy-policy' ),
			'outbrain'    => array( 'Outbrain',                     'Outbrain',              'IL', 'advertising', true,  array( 'amplify\\.outbrain\\.com/cp/obtp\\.js' ),                                                                   array( 'apnxs', 'obuid' ),                                    'https://www.outbrain.com/legal/privacy' ),
			'klaviyo'     => array( 'Klaviyo',                      'Klaviyo',               'US', 'advertising', true,  array( 'static-tracking\\.klaviyo\\.com', 'a\\.klaviyo\\.com' ),                                                   array( '__kla_id' ),                                          'https://www.klaviyo.com/privacy' ),
			'mailchimp'   => array( 'Mailchimp Mandrill / Mc.js',   'Intuit Mailchimp',      'US', 'advertising', true,  array( 'chimpstatic\\.com/mcjs-connected', 'list-manage\\.com' ),                                                  array( '_mcid' ),                                             'https://mailchimp.com/legal/privacy/' ),
			'hubspot'     => array( 'HubSpot tracking',             'HubSpot',               'US', 'advertising', true,  array( 'js\\.hs-scripts\\.com/', 'js\\.hs-analytics\\.net/analytics' ),                                            array( '__hssc', '__hssrc', '__hstc', 'hubspotutk' ),         'https://legal.hubspot.com/privacy-policy' ),
			'intercom'    => array( 'Intercom messenger',           'Intercom',              'US', 'functional',  true,  array( 'widget\\.intercom\\.io', 'js\\.intercomcdn\\.com' ),                                                       array( 'intercom-id-', 'intercom-session-' ),                  'https://www.intercom.com/legal/privacy' ),
			'drift'       => array( 'Drift chat',                   'Salesloft',             'US', 'functional',  true,  array( 'js\\.driftt\\.com', 'js\\.drift\\.com' ),                                                                  array( 'drift_aid' ),                                         'https://www.drift.com/privacy-policy/' ),
			'crisp'       => array( 'Crisp chat',                   'Crisp IM',              'FR', 'functional',  true,  array( 'client\\.crisp\\.chat/l\\.js' ),                                                                            array( 'crisp-client%2Fsession%2F' ),                          'https://crisp.chat/en/privacy/' ),
			'tawkto'      => array( 'Tawk.to chat',                 'Tawk.to',               'CY', 'functional',  true,  array( 'embed\\.tawk\\.to' ),                                                                                       array( 'TawkConnectionTime' ),                                'https://www.tawk.to/data-protection/' ),
			'zendesk'     => array( 'Zendesk Chat (Zopim)',         'Zendesk',               'US', 'functional',  true,  array( 'static\\.zdassets\\.com/ekr/snippet\\.js', 'v2\\.zopim\\.com' ),                                           array( '__zlcmid' ),                                          'https://www.zendesk.com/company/agreements-and-terms/privacy-notice/' ),
			'helpscout'   => array( 'Help Scout Beacon',            'Help Scout',            'US', 'functional',  true,  array( 'beacon-v2\\.helpscout\\.net' ),                                                                             array( 'hsBeacon\\.' ),                                       'https://www.helpscout.com/company/legal/privacy/' ),
			'recaptcha'   => array( 'Google reCAPTCHA',             'Google LLC',           'US', 'functional',  false, array( 'www\\.google\\.com/recaptcha', 'www\\.gstatic\\.com/recaptcha' ),                                          array( '_GRECAPTCHA' ),                                       'https://policies.google.com/privacy' ),
			'cloudflare'  => array( 'Cloudflare bot management',    'Cloudflare',            'US', 'functional',  false, array( 'cdn-cgi/scripts/' ),                                                                                        array( '__cf_bm', 'cf_clearance' ),                            'https://www.cloudflare.com/privacypolicy/' ),
			'youtube'     => array( 'YouTube embed',                'Google LLC',            'US', 'social',      true,  array( 'youtube\\.com/embed', 'youtube-nocookie\\.com' ),                                                          array( 'VISITOR_INFO1_LIVE', 'YSC' ),                          'https://policies.google.com/privacy' ),
			'vimeo'       => array( 'Vimeo embed',                  'Vimeo',                 'US', 'social',      true,  array( 'player\\.vimeo\\.com', 'f\\.vimeocdn\\.com' ),                                                             array( 'vuid' ),                                              'https://vimeo.com/privacy' ),
			'soundcloud'  => array( 'SoundCloud embed',             'SoundCloud',            'DE', 'social',      true,  array( 'w\\.soundcloud\\.com/player' ),                                                                             array(),                                                       'https://soundcloud.com/pages/privacy' ),
			'spotify'     => array( 'Spotify embed',                'Spotify',               'SE', 'social',      true,  array( 'open\\.spotify\\.com/embed' ),                                                                              array(),                                                       'https://www.spotify.com/legal/privacy-policy/' ),
			'gmaps'       => array( 'Google Maps',                  'Google LLC',            'US', 'functional',  true,  array( 'maps\\.googleapis\\.com/maps/api/js', 'maps\\.google\\.com/maps' ),                                        array( 'NID' ),                                               'https://policies.google.com/privacy' ),
			'gfonts'      => array( 'Google Fonts',                 'Google LLC',            'US', 'functional',  true,  array( 'fonts\\.googleapis\\.com', 'fonts\\.gstatic\\.com' ),                                                      array(),                                                       'https://policies.google.com/privacy' ),
			'addthis'     => array( 'AddThis sharing',              'Oracle',                'US', 'social',      true,  array( 's7\\.addthis\\.com', 'm\\.addthisedge\\.com' ),                                                            array( '__atuvc' ),                                           'https://www.oracle.com/legal/privacy/' ),
			'addtoany'    => array( 'AddToAny',                     'AddToAny',              'US', 'social',      true,  array( 'static\\.addtoany\\.com/menu/page\\.js' ),                                                                  array(),                                                       'https://www.addtoany.com/privacy' ),
			'sharethis'   => array( 'ShareThis',                    'ShareThis',             'US', 'social',      true,  array( 'platform-api\\.sharethis\\.com' ),                                                                          array( '__stid', '__unam' ),                                  'https://sharethis.com/privacy/' ),
			'gravatar'    => array( 'Gravatar',                     'Automattic',            'US', 'functional',  true,  array( 'secure\\.gravatar\\.com/avatar' ),                                                                          array(),                                                       'https://automattic.com/privacy/' ),
			'jsdelivr'    => array( 'jsDelivr CDN',                 'Cloudflare/jsDelivr',   'US', 'functional',  false, array( 'cdn\\.jsdelivr\\.net' ),                                                                                    array(),                                                       'https://www.jsdelivr.com/privacy-policy-jsdelivr-net' ),
			'unpkg'       => array( 'unpkg CDN',                    'unpkg',                 'US', 'functional',  false, array( 'unpkg\\.com' ),                                                                                              array(),                                                       'https://unpkg.com/' ),
			'cdnjs'       => array( 'cdnjs (Cloudflare)',           'Cloudflare',            'US', 'functional',  false, array( 'cdnjs\\.cloudflare\\.com' ),                                                                                array(),                                                       'https://www.cloudflare.com/privacypolicy/' ),
			'stripe'      => array( 'Stripe.js',                    'Stripe',                'US', 'functional',  true,  array( 'js\\.stripe\\.com', 'm\\.stripe\\.network' ),                                                              array( '__stripe_mid', '__stripe_sid' ),                       'https://stripe.com/privacy' ),
			'paypal'      => array( 'PayPal SDK',                   'PayPal',                'US', 'functional',  true,  array( 'www\\.paypal\\.com/sdk/js', 'www\\.paypalobjects\\.com' ),                                                array( 'tsrce', 'l7_az', 'enforce_policy' ),                  'https://www.paypal.com/privacy' ),
			'klarna'      => array( 'Klarna On-site Messaging',     'Klarna',                'SE', 'functional',  true,  array( 'js\\.klarna\\.com' ),                                                                                       array(),                                                       'https://www.klarna.com/external-content/legal/privacy/' ),
			'sezzle'      => array( 'Sezzle widget',                'Sezzle',                'US', 'functional',  true,  array( 'widget\\.sezzle\\.com/v1' ),                                                                                array(),                                                       'https://sezzle.com/privacy' ),
			'algolia'     => array( 'Algolia search',               'Algolia',               'FR', 'functional',  false, array( 'cdn\\.jsdelivr\\.net/algoliasearch', 'cdn\\.jsdelivr\\.net/npm/algoliasearch' ),                          array(),                                                       'https://www.algolia.com/policies/privacy/' ),
			'cloudfront'  => array( 'AWS CloudFront',               'Amazon',                'US', 'functional',  false, array( 'cloudfront\\.net' ),                                                                                        array( 'CloudFront-Key-Pair-Id' ),                            'https://aws.amazon.com/privacy/' ),
			'recurly'     => array( 'Recurly.js',                   'Recurly',               'US', 'functional',  true,  array( 'js\\.recurly\\.com/v4' ),                                                                                   array(),                                                       'https://recurly.com/legal/privacy' ),
			'auth0'       => array( 'Auth0',                        'Okta',                  'US', 'functional',  true,  array( 'cdn\\.auth0\\.com/js/auth0' ),                                                                              array( 'auth0' ),                                             'https://www.okta.com/privacy-policy/' ),
			'firebase'    => array( 'Firebase',                     'Google LLC',            'US', 'functional',  true,  array( 'firebase\\.google\\.com', 'gstatic\\.com/firebasejs' ),                                                    array( 'firebase' ),                                          'https://policies.google.com/privacy' ),
			'matomo'      => array( 'Matomo / Piwik',               'Matomo',                'NZ', 'analytics',   true,  array( 'matomo\\.js', '/matomo\\.php', 'piwik\\.js', '/piwik\\.php' ),                                            array( '_pk_id\\.', '_pk_ses\\.' ),                            'https://matomo.org/privacy-policy/' ),
			'plausible'   => array( 'Plausible Analytics',          'Plausible Insights',    'EE', 'analytics',   false, array( 'plausible\\.io/js/plausible' ),                                                                            array(),                                                       'https://plausible.io/privacy-focused-web-analytics' ),
			'fathom'      => array( 'Fathom Analytics',             'Conva Ventures',        'CA', 'analytics',   false, array( 'cdn\\.usefathom\\.com/script\\.js' ),                                                                       array(),                                                       'https://usefathom.com/privacy' ),
			'simpleanalyt'=> array( 'Simple Analytics',             'Simple Analytics',      'NL', 'analytics',   false, array( 'scripts\\.simpleanalyticscdn\\.com' ),                                                                      array(),                                                       'https://docs.simpleanalytics.com/privacy' ),
			'shareaholic' => array( 'Shareaholic',                  'Shareaholic',           'US', 'social',      true,  array( 'cdn\\.shareaholic\\.net' ),                                                                                 array(),                                                       'https://www.shareaholic.com/privacy' ),
			'usercentrics'=> array( 'Usercentrics CMP',             'Usercentrics',          'DE', 'preferences', false, array( 'app\\.usercentrics\\.eu', 'privacy-proxy\\.usercentrics\\.eu' ),                                          array(),                                                       'https://usercentrics.com/privacy-policy/' ),
			'cookiebot'   => array( 'Cookiebot CMP',                'Cookiebot (Usercentrics)','DE', 'preferences', false, array( 'consent\\.cookiebot\\.com' ),                                                                              array( 'CookieConsent' ),                                     'https://www.cookiebot.com/en/privacy-policy/' ),
			'onetrust'    => array( 'OneTrust CMP',                 'OneTrust',              'US', 'preferences', false, array( 'cdn\\.cookielaw\\.org', 'cmp\\.osano\\.com' ),                                                             array( 'OptanonConsent', 'OptanonAlertBoxClosed' ),           'https://www.onetrust.com/privacy/' ),
			'cookieyes'   => array( 'CookieYes',                    'CookieYes',             'IN', 'preferences', false, array( 'cdn-cookieyes\\.com', 'app\\.cookieyes\\.com' ),                                                          array( 'cookieyes-consent' ),                                  'https://www.cookieyes.com/privacy-policy/' ),
			'iubenda'     => array( 'iubenda CMP',                  'iubenda',               'IT', 'preferences', false, array( 'cdn\\.iubenda\\.com' ),                                                                                     array( '_iub_cs-' ),                                          'https://www.iubenda.com/privacy-policy/' ),
			'wpforms'     => array( 'WPForms',                      'WPForms',               'US', 'functional',  false, array( 'wpforms\\.com/wpforms' ),                                                                                   array(),                                                       'https://wpforms.com/privacy/' ),
			'cf7'         => array( 'Contact Form 7',               'Takayuki Miyoshi',      'JP', 'functional',  false, array( 'contact-form-7' ),                                                                                          array(),                                                       'https://contactform7.com/privacy-policy/' ),
			'wpemoji'     => array( 'WordPress emoji',              'WordPress',             'US', 'functional',  false, array( 's\\.w\\.org/images/core/emoji' ),                                                                           array(),                                                       'https://wordpress.org/about/privacy/' ),
			'jetpack'     => array( 'Jetpack stats',                'Automattic',            'US', 'analytics',   true,  array( 'stats\\.wp\\.com', 'pixel\\.wp\\.com' ),                                                                   array( 'tk_ai', 'tk_lr', 'tk_or' ),                            'https://automattic.com/privacy/' ),
			'wpquads'     => array( 'WPQuads ads',                  'WPQuads',               'DE', 'advertising', true,  array( 'wpquads\\.com' ),                                                                                            array(),                                                       'https://wpquads.com/privacy/' ),
			'amazon_aff'  => array( 'Amazon Associates',            'Amazon',                'US', 'advertising', true,  array( 'amazon-adsystem\\.com', 'assoc-amazon\\.com' ),                                                            array( 'ad-id', 'ad-privacy' ),                                'https://aws.amazon.com/privacy/' ),
			'shopify'     => array( 'Shopify Buy SDK',              'Shopify',               'CA', 'functional',  true,  array( 'sdks\\.shopifycdn\\.com/buy-button' ),                                                                      array(),                                                       'https://www.shopify.com/legal/privacy' ),
			'sumo'        => array( 'Sumo (Sumo.com)',              'Sumo',                  'US', 'advertising', true,  array( 'load\\.sumome\\.com', 'sumo\\.com' ),                                                                       array( '__smToken' ),                                          'https://sumo.com/privacy' ),
			'salesforce'  => array( 'Salesforce / Pardot',          'Salesforce',            'US', 'advertising', true,  array( 'pi\\.pardot\\.com', 'go\\.pardot\\.com' ),                                                                 array( 'pardot', 'visitor_id' ),                              'https://www.salesforce.com/company/privacy/' ),
			'marketo'     => array( 'Marketo Munchkin',             'Adobe',                 'US', 'advertising', true,  array( 'munchkin\\.marketo\\.net' ),                                                                                 array( '_mkto_trk' ),                                         'https://www.adobe.com/privacy/policy.html' ),
		);

		$out = array();
		foreach ( $rows as $slug => $r ) {
			$out[ (string) $slug ] = array(
				'name'             => (string) $r[0],
				'vendor'           => (string) $r[1],
				'country'          => (string) $r[2],
				'category'         => (string) $r[3],
				'consent_required' => (bool)   $r[4],
				'patterns'         => (array)  $r[5],
				'cookies'          => (array)  $r[6],
				'docs'             => (string) $r[7],
			);
		}
		return $out;
	}

	public static function get( string $slug ) : ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Match an HTML body against every tracker pattern; returns slugs => first
	 * matching evidence string.
	 *
	 * @return array<string,string>
	 */
	public static function match_html( string $html ) : array {
		$hits = array();
		foreach ( self::all() as $slug => $row ) {
			foreach ( $row['patterns'] as $pattern ) {
				if ( '' === $pattern ) {
					continue;
				}
				if ( @preg_match( '#' . $pattern . '#i', $html, $m ) ) {
					$hits[ $slug ] = isset( $m[0] ) ? mb_substr( (string) $m[0], 0, 240 ) : $pattern;
					break;
				}
			}
		}
		return $hits;
	}

	/**
	 * Match a single cookie name (case-sensitive prefix-aware) to a slug.
	 */
	public static function match_cookie_name( string $name ) : string {
		foreach ( self::all() as $slug => $row ) {
			foreach ( $row['cookies'] as $needle ) {
				$needle = (string) $needle;
				if ( '' === $needle ) {
					continue;
				}
				if ( '_' === substr( $needle, -1 ) || '-' === substr( $needle, -1 ) || '.' === substr( $needle, -1 ) ) {
					if ( 0 === strpos( $name, $needle ) ) {
						return (string) $slug;
					}
				} elseif ( $name === $needle ) {
					return (string) $slug;
				}
			}
		}
		return '';
	}

	/**
	 * @return array<string,string>
	 */
	public static function categories() : array {
		return array(
			'analytics'   => __( 'Analytics',   'eurocomply-eprivacy' ),
			'advertising' => __( 'Advertising', 'eurocomply-eprivacy' ),
			'social'      => __( 'Social',      'eurocomply-eprivacy' ),
			'functional'  => __( 'Functional',  'eurocomply-eprivacy' ),
			'preferences' => __( 'Preferences', 'eurocomply-eprivacy' ),
		);
	}
}
