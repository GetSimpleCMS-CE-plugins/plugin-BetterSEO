<?php

# get correct id for plugin
$thisfile = basename(__FILE__, ".php");

# add in this plugin's language file
i18n_merge('BetterSeo') || i18n_merge('BetterSeo', 'en_US');

# register plugin
register_plugin(
	$thisfile, 		// Plugin id
	i18n_r('BetterSeo/LANG_Title'),	// Plugin name
	'5.0', 			// Plugin version
	'CE Team', 		// Plugin author
	'https://getsimple-ce.ovh/donate', // Author website
	i18n_r('BetterSeo/LANG_Description'), // Plugin description
	'plugins', 		// Page type - on which admin tab to display
	'betterSEO' 	// Main function (administration)
);

# add a link in the admin tab 'theme'
add_action('plugins-sidebar', 'createSideMenu', array($thisfile, i18n_r('BetterSeo/LANG_Settings')));

# per-page SEO controls (robots + Article schema) on the Edit Page screen
add_action('edit-extras', 'BetterSeo_edit_panel');
add_action('changedata-save', 'BetterSeo_save_page_meta');

/* ==========================================================================
   STORAGE ENGINE & DATA ACCESS HELPERS
   ========================================================================== */

if (!function_exists('BetterSeo_default_settings')) {
	function BetterSeo_default_settings() {
		return [
			'version' => 1,
			'general' => [
				'homepagetitle' => 'normal',
			],
			'verification' => [
				'google' => '',
				'bing'   => '',
			],
			'favicon' => [
				'enabled' => false,
			],
			'dublin_core' => [
				'enabled'  => false,
				'language' => '',
			],
			'geolocation' => [
				'enabled' => false,
				'code'    => '',
			],
			'facebook' => [
				'enabled'      => false,
				'custom_field' => '',
				'multifield'   => '',
				'image'        => '',
			],
			'twitter' => [
				'enabled'  => false,
				'username' => '',
			],
			'apple' => [
				'enabled' => false,
			],
			'jsonld' => [
				'enabled' => false,
				'data'    => [],
			],
			'pages' => [],
		];
	}
}

if (!function_exists('BetterSeo_get_settings_file_path')) {
	function BetterSeo_get_settings_file_path() {
		return GSDATAOTHERPATH . 'betterSEO/settings.json';
	}
}

if (!function_exists('BetterSeo_ensure_directory_protection')) {
	function BetterSeo_ensure_directory_protection($dir) {
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$htaccess = rtrim($dir, '/\\') . '/.htaccess';
		if (!file_exists($htaccess)) {
			@file_put_contents($htaccess, "Require all denied\n");
		}
	}
}

if (!function_exists('BetterSeo_load_settings')) {
	function BetterSeo_load_settings($force_reload = false) {
		static $cached_settings = null;

		if ($cached_settings !== null && !$force_reload) {
			return $cached_settings;
		}

		$file = BetterSeo_get_settings_file_path();
		$folder = dirname($file);
		BetterSeo_ensure_directory_protection($folder);

		// If settings.json doesn't exist, execute automatic migration
		if (!file_exists($file)) {
			$migrated = BetterSeo_migrate_legacy_data();
			if ($migrated !== false) {
				$cached_settings = $migrated;
				return $cached_settings;
			}
			$defaults = BetterSeo_default_settings();
			BetterSeo_save_settings($defaults);
			$cached_settings = $defaults;
			return $cached_settings;
		}

		$content = @file_get_contents($file);
		if ($content === false || trim($content) === '') {
			$cached_settings = BetterSeo_default_settings();
			return $cached_settings;
		}

		$decoded = json_decode($content, true);
		if (!is_array($decoded)) {
			$cached_settings = BetterSeo_default_settings();
			return $cached_settings;
		}

		$defaults = BetterSeo_default_settings();
		$settings = array_replace_recursive($defaults, $decoded);
		if (isset($decoded['pages']) && is_array($decoded['pages'])) {
			$settings['pages'] = $decoded['pages'];
		}
		if (isset($decoded['jsonld']['data']) && is_array($decoded['jsonld']['data'])) {
			$settings['jsonld']['data'] = $decoded['jsonld']['data'];
		}

		$cached_settings = $settings;
		return $cached_settings;
	}
}

if (!function_exists('BetterSeo_save_settings')) {
	function BetterSeo_save_settings(array $settings) {
		$file = BetterSeo_get_settings_file_path();
		$folder = dirname($file);
		BetterSeo_ensure_directory_protection($folder);

		$json_data = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($json_data === false) {
			return false;
		}

		$temp_file = $folder . '/settings.json.tmp.' . uniqid('', true);
		$written = @file_put_contents($temp_file, $json_data, LOCK_EX);

		if ($written !== false) {
			if (@rename($temp_file, $file)) {
				BetterSeo_load_settings(true);
				return true;
			}
			@unlink($temp_file);
		}

		$direct_write = @file_put_contents($file, $json_data, LOCK_EX);
		if ($direct_write !== false) {
			BetterSeo_load_settings(true);
			return true;
		}

		return false;
	}
}

if (!function_exists('BetterSeo_get_setting')) {
	function BetterSeo_get_setting($path, $default = null) {
		$settings = BetterSeo_load_settings();
		$keys = explode('.', $path);
		$current = $settings;

		foreach ($keys as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return $default;
			}
			$current = $current[$key];
		}

		return $current;
	}
}

if (!function_exists('BetterSeo_update_setting')) {
	function BetterSeo_update_setting($path, $value) {
		$settings = BetterSeo_load_settings();
		$keys = explode('.', $path);
		$current = &$settings;

		foreach ($keys as $key) {
			if (!isset($current[$key]) || !is_array($current[$key])) {
				$current[$key] = [];
			}
			$current = &$current[$key];
		}

		$current = $value;
		return BetterSeo_save_settings($settings);
	}
}

/**
 * Gets SEO settings for a specific page slug.
 */
if (!function_exists('BetterSeo_get_page_meta')) {
	function BetterSeo_get_page_meta($slug) {
		$slug = (string) $slug;
		if ($slug === '') return [];
		$settings = BetterSeo_load_settings();
		return $settings['pages'][$slug] ?? [];
	}
}

/**
 * Performs lossless migration from pre-v5.0 storage files.
 */
if (!function_exists('BetterSeo_migrate_legacy_data')) {
	function BetterSeo_migrate_legacy_data() {
		$folder = GSDATAOTHERPATH . 'betterSEO/';
		if (!is_dir($folder)) {
			return false;
		}

		$legacy_files = [
			'homepagetitle.txt', 'googleverify.txt', 'bingverify.txt', 'favicon.txt',
			'dublin.txt', 'dublincheck.txt', 'geocheck.txt', 'geocode.txt',
			'facebookcheck.txt', 'fbcustom.txt', 'fbmultifield.txt', 'fbimage.txt',
			'twitter.txt', 'twittercheck.txt', 'applecheck.txt', 'jsonldcheck.txt',
			'jsonldcode.json'
		];

		$has_legacy = false;
		foreach ($legacy_files as $f) {
			if (file_exists($folder . $f)) {
				$has_legacy = true;
				break;
			}
		}

		$pages_dir = $folder . 'pages/';
		if (is_dir($pages_dir)) {
			$page_files = glob($pages_dir . '*.json');
			if (!empty($page_files)) {
				$has_legacy = true;
			}
		}

		if (!$has_legacy) {
			return false;
		}

		// Create safe backup prior to migration
		$backup_dir = $folder . 'backup_pre_v5_' . date('Ymd_His') . '/';
		@mkdir($backup_dir, 0755, true);

		foreach ($legacy_files as $lf) {
			if (file_exists($folder . $lf)) {
				@copy($folder . $lf, $backup_dir . $lf);
			}
		}

		if (is_dir($pages_dir)) {
			$bp_pages = $backup_dir . 'pages/';
			@mkdir($bp_pages, 0755, true);
			$page_files = glob($pages_dir . '*.json');
			if (is_array($page_files)) {
				foreach ($page_files as $pf) {
					@copy($pf, $bp_pages . basename($pf));
				}
			}
		}

		$read_file = function($filename) use ($folder) {
			$path = $folder . $filename;
			return file_exists($path) ? trim((string)file_get_contents($path)) : '';
		};

		$read_bool = function($filename) use ($read_file) {
			$val = $read_file($filename);
			return $val !== '' && $val !== '0';
		};

		$migrated = BetterSeo_default_settings();
		$migrated['version'] = 1;

		// General
		$hp = $read_file('homepagetitle.txt');
		$migrated['general']['homepagetitle'] = !empty($hp) ? $hp : 'normal';

		// Verification
		$migrated['verification']['google'] = $read_file('googleverify.txt');
		$migrated['verification']['bing']   = $read_file('bingverify.txt');

		// Favicon
		$migrated['favicon']['enabled'] = $read_bool('favicon.txt');

		// Dublin Core
		$migrated['dublin_core']['enabled']  = $read_bool('dublincheck.txt');
		$migrated['dublin_core']['language'] = $read_file('dublin.txt');

		// Geolocation
		$migrated['geolocation']['enabled'] = $read_bool('geocheck.txt');
		$migrated['geolocation']['code']    = file_exists($folder . 'geocode.txt') ? (string)file_get_contents($folder . 'geocode.txt') : '';

		// Facebook
		$migrated['facebook']['enabled']      = $read_bool('facebookcheck.txt');
		$migrated['facebook']['custom_field'] = $read_file('fbcustom.txt');
		$migrated['facebook']['multifield']   = $read_file('fbmultifield.txt');
		$migrated['facebook']['image']        = $read_file('fbimage.txt');

		// Twitter
		$migrated['twitter']['enabled']  = $read_bool('twittercheck.txt');
		$migrated['twitter']['username'] = $read_file('twitter.txt');

		// Apple
		$migrated['apple']['enabled'] = $read_bool('applecheck.txt');

		// JSON-LD
		$migrated['jsonld']['enabled'] = $read_bool('jsonldcheck.txt');
		if (file_exists($folder . 'jsonldcode.json')) {
			$jld_raw = file_get_contents($folder . 'jsonldcode.json');
			$jld_decoded = json_decode($jld_raw, true);
			$migrated['jsonld']['data'] = is_array($jld_decoded) ? $jld_decoded : [];
		}

		// Pages
		if (is_dir($pages_dir)) {
			$page_files = glob($pages_dir . '*.json');
			if (is_array($page_files)) {
				foreach ($page_files as $pfile) {
					$slug = basename($pfile, '.json');
					if ($slug !== '') {
						$content = @file_get_contents($pfile);
						$data = json_decode($content, true);
						if (is_array($data)) {
							$migrated['pages'][$slug] = [
								'noindex'  => !empty($data['noindex']),
								'nofollow' => !empty($data['nofollow']),
							];
						}
					}
				}
			}
		}

		// Save and verify
		$target_file = BetterSeo_get_settings_file_path();
		$encoded = json_encode($migrated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$written = @file_put_contents($target_file, $encoded, LOCK_EX);

		if ($written !== false) {
			$verify = @json_decode(file_get_contents($target_file), true);
			if (is_array($verify) && isset($verify['version'])) {
				foreach ($legacy_files as $lf) {
					if (file_exists($folder . $lf)) {
						@unlink($folder . $lf);
					}
				}
				if (is_dir($pages_dir)) {
					$page_files = glob($pages_dir . '*.json');
					if (is_array($page_files)) {
						foreach ($page_files as $pf) {
							@unlink($pf);
						}
					}
					@rmdir($pages_dir);
				}
				return $migrated;
			}
		}

		return false;
	}
}

/* ==========================================================================
   FRONTEND & EDIT INTERFACE HELPERS
   ========================================================================== */

if (!function_exists('descSeo')) {
	function descSeo() {
		if (get_page_meta_desc($echo = false) == '') {
			global $content;
			$desc = strip_decode($content);
			$desc = exec_filter('content', $desc);
			if (getDef('GSCONTENTSTRIP', true))
				$desc = strip_content($desc);
			$desc = cleanHtml($desc, ['style', 'script']);
			$desc = getExcerpt($desc, 160); // grab 160 chars
			$desc = strip_whitespace($desc);
			$desc = str_replace('"', '', $desc);
			$desc = encode_quotes($desc);
			$desc = trim($desc);
			return $desc;
		} else {
			return get_page_meta_desc($echo = false);
		}
	}
}

if (!function_exists('descJSON')) {
	function descJSON() {
		if (get_page_meta_desc($echo = false) == '') {
			global $content;
			$desc2 = strip_decode($content);
			$desc2 = exec_filter('content', $desc2);
			if (getDef('GSCONTENTSTRIP', true))
				$desc2 = strip_content($desc2);
			$desc2 = cleanHtml($desc2, ['style', 'script']);
			$desc2 = getExcerpt($desc2, 860);
			$desc2 = strip_whitespace($desc2);
			$desc2 = trim($desc2);
			return $desc2;
		} else {
			return get_page_meta_desc($echo = false);
		}
	}
}

if (!function_exists('betterseo_json_str')) {
	function betterseo_json_str($value) {
		$json = json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$inner = substr($json, 1, -1);
		return str_replace('</', '<\/', $inner);
	}
}

// Renders the checkboxes and SERP preview on the Edit Page screen (hooked to 'edit-extras')
if (!function_exists('BetterSeo_edit_panel')) {
	function BetterSeo_edit_panel() {
		global $SITEURL;
		$slug = isset($_GET['id']) ? (string)$_GET['id'] : '';
		$pagedata = BetterSeo_get_page_meta($slug);

		$noindex  = !empty($pagedata['noindex']);
		$nofollow = !empty($pagedata['nofollow']);

		// Description fallback for preview
		global $metad, $content;
		$betterseo_desc_fallback = '';
		try {
			if (trim((string) $metad) !== '') {
				$betterseo_desc_fallback = trim((string) $metad);
			} elseif (function_exists('exec_filter') && function_exists('getExcerpt') && function_exists('cleanHtml')) {
				$desc2 = (string) $content;
				$desc2 = function_exists('strip_decode') ? strip_decode($desc2) : html_entity_decode($desc2, ENT_QUOTES, 'UTF-8');
				$desc2 = exec_filter('content', $desc2);
				if (!function_exists('getDef') || getDef('GSCONTENTSTRIP', true)) {
					$desc2 = strip_tags($desc2);
				}
				$desc2 = cleanHtml($desc2, ['style', 'script']);
				$desc2 = getExcerpt($desc2, 860);
				$desc2 = trim(preg_replace('/\s+/', ' ', $desc2));
				$betterseo_desc_fallback = $desc2;
			}
		} catch (\Throwable $e) {
			$betterseo_desc_fallback = '';
		}

		// Preview OG image resolving
		$betterseo_preview_image = '';
		$settings = BetterSeo_load_settings();

		if (!empty($settings['facebook']['enabled'])) {
			if (!empty($settings['facebook']['image'])) {
				$betterseo_preview_image = $settings['facebook']['image'];
			} elseif (!empty($settings['facebook']['multifield'])) {
				$fieldName = $settings['facebook']['multifield'];
				$multiFieldFile = GSDATAOTHERPATH . 'multiField/' . $slug . '.json';
				if (file_exists($multiFieldFile)) {
					$multiFieldData = json_decode(file_get_contents($multiFieldFile), true);
					if (is_array($multiFieldData)) {
						foreach ($multiFieldData as $key => $value) {
							if (isset($value['label']) && $value['label'] == $fieldName) {
								$imageseo_raw = html_entity_decode($value['value'], ENT_QUOTES, 'UTF-8');
								if (filter_var($imageseo_raw, FILTER_VALIDATE_URL)) {
									$betterseo_preview_image = $imageseo_raw;
								} elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $imageseo_raw, $matches)) {
									$betterseo_preview_image = $matches[1];
								}
								break;
							}
						}
					}
				}
			} elseif (!empty($settings['facebook']['custom_field'])) {
				$fieldName = $settings['facebook']['custom_field'];
				if (function_exists('return_custom_field')) {
					$imageseo_raw = return_custom_field($fieldName);
					if (filter_var($imageseo_raw, FILTER_VALIDATE_URL)) {
						$betterseo_preview_image = $imageseo_raw;
					} elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $imageseo_raw, $matches)) {
						$betterseo_preview_image = $matches[1];
					}
				}
			}
		}
		?>
		
		<style>
		#metadata_window .bseo-align input{width:auto;}
		#metadata_window .bseo-align p{margin-left:20px;}
		</style>
		<div class="clear"></div>
		<div class="rightopt bseo-align" style="width:50%;">
			<p class="inline clearfix">
				<input type="checkbox" id="betterseo_noindex" name="betterseo_noindex"<?php echo $noindex ? ' checked="checked"' : ''; ?> />&nbsp;&nbsp;&nbsp;
				<label for="betterseo_noindex"><?php echo i18n_r('BetterSeo/LANG_Noindex'); ?></label>
			</p>
			<p class="inline clearfix">
				<input type="checkbox" id="betterseo_nofollow" name="betterseo_nofollow"<?php echo $nofollow ? ' checked="checked"' : ''; ?> />&nbsp;&nbsp;&nbsp;
				<label for="betterseo_nofollow"><?php echo i18n_r('BetterSeo/LANG_Nofollow'); ?></label>
			</p>
		</div>

		<div class="clear"></div>
		<div style="width:100%;box-sizing:border-box;padding:10px 0 20px;">
			<details class="bseo-serp-details">
				<summary style="cursor:pointer;font-weight:bold;padding:8px 0;"><?php echo i18n_r('BetterSeo/LANG_Serp_Preview'); ?></summary>
				<div id="bseo-serp-preview" style="border:1px solid #ddd;border-radius:8px;padding:15px 20px;margin:10px 0;background:#fff;font-family:arial,sans-serif;max-width:600px;">
					<div id="bseo-serp-url" style="color:#202124;font-size:14px;line-height:1.3;"></div>
					<div id="bseo-serp-title" style="color:#1a0dab;font-size:20px;line-height:1.3;margin:2px 0;overflow-wrap:break-word;"></div>
					<div style="display:flex;gap:12px;align-items:flex-start;">
						<div id="bseo-serp-desc" style="color:#4d5156;font-size:14px;line-height:1.4;overflow-wrap:break-word;flex:1;"></div>
						<img id="bseo-serp-image" style="display:none;width:120px;height:80px;object-fit:cover;border-radius:6px;flex-shrink:0;" alt="" />
					</div>
				</div>
			</details>
		</div>

		<script>
		(function () {
			var titleInput = document.getElementById('post-title');
			var descInput  = document.getElementById('post-metad');
			var slugInput  = document.getElementById('post-id');

			var serpUrl   = document.getElementById('bseo-serp-url');
			var serpTitle = document.getElementById('bseo-serp-title');
			var serpDesc  = document.getElementById('bseo-serp-desc');
			var serpImage = document.getElementById('bseo-serp-image');
			if (!serpUrl || !serpTitle || !serpDesc) return;

			var siteBase = <?php echo json_encode(rtrim($SITEURL, '/')); ?>;
			var titlePlaceholder = <?php echo json_encode(i18n_r('BetterSeo/LANG_Serp_Title_Placeholder')); ?>;
			var descFallback = <?php echo json_encode($betterseo_desc_fallback); ?>;
			var descPlaceholder = <?php echo json_encode(i18n_r('BetterSeo/LANG_Serp_Desc_Placeholder')); ?>;
			var previewImage = <?php echo json_encode($betterseo_preview_image); ?>;

			if (serpImage && previewImage) {
				serpImage.src = previewImage;
				serpImage.style.display = '';
			}

			function truncate(str, len) {
				if (!str) return '';
				return str.length > len ? str.substring(0, len - 1) + '\u2026' : str;
			}

			function updatePreview() {
				var title = titleInput ? titleInput.value : '';
				var desc  = descInput ? descInput.value : '';
				var slug  = slugInput ? slugInput.value : '';

				var displayUrl = siteBase.replace(/^https?:\/\//, '');
				if (slug) displayUrl += '/' + slug;

				serpUrl.textContent = displayUrl;
				serpTitle.textContent = truncate(title, 60) || titlePlaceholder;
				serpDesc.textContent = truncate(desc, 155) || truncate(descFallback, 155) || descPlaceholder;
			}

			[titleInput, descInput, slugInput].forEach(function (el) {
				if (el) el.addEventListener('input', updatePreview);
			});

			updatePreview();
		})();
		</script>
		<?php
	}
}

// Persists per-page settings to central settings.json (hooked to 'changedata-save')
if (!function_exists('BetterSeo_save_page_meta')) {
	function BetterSeo_save_page_meta() {
		global $url, $existingurl;
		$url = (string) $url;
		$existingurl = (string) $existingurl;
		if ($url === '') return;

		$settings = BetterSeo_load_settings();

		// Transfer data if slug has changed
		if ($existingurl !== '' && $existingurl !== $url && isset($settings['pages'][$existingurl])) {
			$settings['pages'][$url] = $settings['pages'][$existingurl];
			unset($settings['pages'][$existingurl]);
		}

		$settings['pages'][$url] = [
			'noindex'  => isset($_POST['betterseo_noindex']),
			'nofollow' => isset($_POST['betterseo_nofollow']),
		];

		BetterSeo_save_settings($settings);
	}
}

/* ==========================================================================
   FRONTEND SEO HEADER RENDERING
   ========================================================================== */

function get_seoheader($full = true) {
	$settings = BetterSeo_load_settings();

	// Per-page overrides
	$current_slug = (string) return_page_slug();
	$betterseo_pagemeta = BetterSeo_get_page_meta($current_slug);
	$betterseo_robots_index  = !empty($betterseo_pagemeta['noindex'])  ? 'noindex'  : 'index';
	$betterseo_robots_follow = !empty($betterseo_pagemeta['nofollow']) ? 'nofollow' : 'follow';

	// Content Type: "website" for the homepage, "article" for every other page
	$betterseo_content_type = ($current_slug === 'index') ? 'website' : 'article';

	$homepagetitle = $settings['general']['homepagetitle'] ?? 'normal';
	if ($homepagetitle === 'normal') {
		$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
	} elseif ($homepagetitle === 'titlefirst') {
		if ($current_slug === 'index') {
			$newSeoTitle = get_site_name($echo = false) . ' | ' . get_page_title($echo = false);
		} else {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		}
	} elseif ($homepagetitle === 'titleonly') {
		if ($current_slug === 'index') {
			$newSeoTitle = get_site_name($echo = false);
		} else {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		}
	} else {
		$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
	}

	$seo = '<!-- Basic Header Needs
	================================================== -->
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1.0, shrink-to-fit=no" />
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<base href="' . get_site_url($echo = false) . '">
	<title>' . $newSeoTitle . '</title>
	<meta name="description" content="' . descSeo() . '">
	<meta name="robots" content="' . $betterseo_robots_index . ', ' . $betterseo_robots_follow . '">
	<meta name="copyright" content="' . get_site_name($echo = false) . '">
	<meta http-equiv="last-modified" content="' . get_page_date('D, j M Y G:i:s', $echo = false) . ' GMT">
	<link rel="canonical" href="' . get_page_url($echo = true) . '">
		
	';

	// Site verification
	$google_verify = $settings['verification']['google'] ?? '';
	$bing_verify   = $settings['verification']['bing'] ?? '';

	if ($google_verify !== '' || $bing_verify !== '') {
		$seo .= '<!-- Search Engine Verification
	================================================== -->
	';
		if ($google_verify !== '') {
			$seo .= '<meta name="google-site-verification" content="' . htmlspecialchars($google_verify, ENT_QUOTES) . '">
	';
		}
		if ($bing_verify !== '') {
			$seo .= '<meta name="msvalidate.01" content="' . htmlspecialchars($bing_verify, ENT_QUOTES) . '">
	
	';
		}
	}

	// Apple Web App Tags
	if (!empty($settings['apple']['enabled'])) {
		$seo .= '<!-- Apple Web App Tags
	================================================== -->
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="' . get_site_name($echo = false) . '">
		
	';
	}

	// Resolve Open Graph & Twitter Image
	$imageseo = '';
	if (function_exists('nc_get_current_seo_image') && nc_get_current_seo_image() !== '') {
		$imageseo = nc_get_current_seo_image();
	} elseif (function_exists('nc_get_current_featured_image') && nc_get_current_featured_image() !== '') {
		$imageseo = nc_get_current_featured_image();
	} elseif (!empty($settings['facebook']['enabled'])) {
		if (!empty($settings['facebook']['image'])) {
			$imageseo = $settings['facebook']['image'];
		}

		if (empty($imageseo) && !empty($settings['facebook']['multifield'])) {
			$content_field = $settings['facebook']['multifield'];
			if (function_exists('r_multiFields')) {
				$imageseo = r_multiFields($content_field);
			}
		}

		if (empty($imageseo) && !empty($settings['facebook']['custom_field'])) {
			$content_field = $settings['facebook']['custom_field'];
			if (function_exists('return_custom_field')) {
				$imageseo = return_custom_field($content_field);
			}
		}
	}

	// Facebook Open Graph
	if (!empty($settings['facebook']['enabled'])) {
		$seo .= '<!-- Facebook Open Graph protocol
	=================================================== -->
	<meta property="og:type" content="' . $betterseo_content_type . '">
	<meta property="og:site_name" content="' . get_site_name($echo = false) . '">
	<meta property="og:title" content="' . $newSeoTitle . '">
	<meta property="og:description" content="' . descSeo() . '">
	<meta property="og:url" content="' . get_page_url($echo = true) . '">';
		
		if (!empty($imageseo)) {
			$seo .= '
	<meta property="og:image" content="' . $imageseo . '">';
		}
		
		$seo .= '
	
	';
	}

	// Twitter/X Cards
	if (!empty($settings['twitter']['enabled'])) {
		$seo .= '<!-- Twitter/X Card
	=================================================== -->
	<meta name="twitter:card" content="summary_large_image">';
		
		$twitter_user = $settings['twitter']['username'] ?? '';
		if ($twitter_user !== '') {
			$seo .= '
	<meta name="twitter:site" content="' . htmlspecialchars($twitter_user, ENT_QUOTES) . '" />';
		}
		
		$seo .= '
	<meta name="twitter:title" content="' . $newSeoTitle . '">
	<meta name="twitter:description" content="' . descSeo() . '">';
		
		if (!empty($imageseo)) {
			$seo .= '
	<meta name="twitter:image" content="' . $imageseo . '">';
		}
		
		$seo .= '
	
	';
	}

	// Dublin Core Metadata
	if (!empty($settings['dublin_core']['enabled'])) {
		$dublin_lang = $settings['dublin_core']['language'] ?? '';
		$seo .= '<!-- Dublin Core Metadata
	=================================================== -->
	<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />
	<meta name="DC.Format" content="text/html" />
	<meta name="DC.Type" content="' . $betterseo_content_type . '" />
	<meta name="DC.Language" content="' . htmlspecialchars($dublin_lang, ENT_QUOTES) . '" />
	<meta name="DC.Title" content="' . get_page_clean_title($echo = false) . '" />
	<meta name="DC.Creator" content="' . get_site_name($echo = false) . '"/>
	<meta name="DC.Date" content="' . get_page_date('D, j M Y G:i:s', $echo = false) . ' GMT">
	
	';
	}

	// Favicons
	if (!empty($settings['favicon']['enabled'])) {
		$theme_url = get_theme_url($echo = false);
		$seo .= '<!-- Favicons
	=================================================== -->
	<link rel="icon" type="image/png" sizes="36x36" href="' . $theme_url . '/fav/android-icon-36x36.png">
	<link rel="icon" type="image/png" sizes="48x48" href="' . $theme_url . '/fav/android-icon-48x48.png">
	<link rel="icon" type="image/png" sizes="72x72" href="' . $theme_url . '/fav/android-icon-72x72.png">
	<link rel="icon" type="image/png" sizes="96x96" href="' . $theme_url . '/fav/android-icon-96x96.png">
	<link rel="icon" type="image/png" sizes="144x144" href="' . $theme_url . '/fav/android-icon-144x144.png">
	<link rel="icon" type="image/png" sizes="192x192" href="' . $theme_url . '/fav/android-icon-192x192.png">
	
	<link rel="apple-touch-icon" sizes="192x192" href="' . $theme_url . '/fav/apple-icon.png">
	<link rel="apple-touch-icon" sizes="57x57" href="' . $theme_url . '/fav/apple-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="' . $theme_url . '/fav/apple-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="' . $theme_url . '/fav/apple-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="' . $theme_url . '/fav/apple-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="' . $theme_url . '/fav/apple-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="' . $theme_url . '/fav/apple-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="' . $theme_url . '/fav/apple-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="' . $theme_url . '/fav/apple-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="' . $theme_url . '/fav/apple-icon-180x180.png">
	<link rel="apple-touch-icon" sizes="192x192" href="' . $theme_url . '/fav/apple-icon-precomposed.png">
	
	<link rel="shortcut icon" type="image/x-icon" href="' . $theme_url . '/fav/favicon.ico" />
	<link rel="icon" type="image/png" sizes="16x16" href="' . $theme_url . '/fav/favicon-16x16.png">
	<link rel="icon" type="image/png" sizes="32x32" href="' . $theme_url . '/fav/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="96x96" href="' . $theme_url . '/fav/favicon-96x96.png">
	
	<meta name="msapplication-TileImage" content="' . $theme_url . '/fav/ms-icon-310x310.png">
	<meta name="msapplication-config" content="' . $theme_url . '/fav/browserconfig.xml" />
	<meta name="msapplication-TileColor" content="#ffffff">
	<link rel="manifest" href="' . get_theme_url($echo = false) . '/fav/manifest.json">
	<meta name="theme-color" content="#ffffff">
	
	';
	}

	// Geolocation
	if (!empty($settings['geolocation']['enabled'])) {
		$geo_code = $settings['geolocation']['code'] ?? '';
		$seo .= '<!-- GeoLocation Meta Tags / Geotagging -->
' . $geo_code . '

	';
	}

	// JSON-LD Schema
	if (!empty($settings['jsonld']['enabled'])) {
		$jsonldData = $settings['jsonld']['data'] ?? [];

		if (!empty($jsonldData) && is_array($jsonldData) && !empty($jsonldData['@type'])) {
			$jsonldOutput = '
{
  "@context": "https://schema.org",
  "@type": "' . betterseo_json_str($jsonldData['@type']) . '"';

			if (isset($jsonldData['name'])) {
				$jsonldOutput .= ',
  "name": "' . betterseo_json_str($jsonldData['name']) . '"';
			}

			$jsonldOutput .= ',
  "url": "' . betterseo_json_str(get_page_url(true)) . '",
  "description": "' . betterseo_json_str(descJSON()) . '"';

			if (!empty($imageseo)) {
				$jsonldOutput .= ',
  "image": "' . betterseo_json_str($imageseo) . '"';
			}

			if (isset($jsonldData['telephone'])) {
				$jsonldOutput .= ',
  "telephone": "' . betterseo_json_str($jsonldData['telephone']) . '"';
			}

			if (isset($jsonldData['gender'])) {
				$jsonldOutput .= ',
  "gender": "' . betterseo_json_str($jsonldData['gender']) . '"';
			}

			if (isset($jsonldData['nationality'])) {
				$jsonldOutput .= ',
  "nationality": "' . betterseo_json_str($jsonldData['nationality']) . '"';
			}

			if (isset($jsonldData['birthPlace'])) {
				$jsonldOutput .= ',
  "birthPlace": "' . betterseo_json_str($jsonldData['birthPlace']) . '"';
			}

			if (isset($jsonldData['birthDate'])) {
				$jsonldOutput .= ',
  "birthDate": "' . betterseo_json_str($jsonldData['birthDate']) . '"';
			}

			if (isset($jsonldData['jobTitle'])) {
				$jsonldOutput .= ',
  "jobTitle": "' . betterseo_json_str($jsonldData['jobTitle']) . '"';
			}

			if (isset($jsonldData['alumniOf'])) {
				$jsonldOutput .= ',
  "alumniOf": "' . betterseo_json_str($jsonldData['alumniOf']) . '"';
			}

			// Address
			if (isset($jsonldData['address']) && is_array($jsonldData['address'])) {
				$jsonldOutput .= ',
  "address": {
	"@type": "PostalAddress"';

				if (isset($jsonldData['address']['streetAddress'])) {
					$jsonldOutput .= ',
	"streetAddress": "' . betterseo_json_str($jsonldData['address']['streetAddress']) . '"';
				}

				if (isset($jsonldData['address']['addressLocality'])) {
					$jsonldOutput .= ',
	"addressLocality": "' . betterseo_json_str($jsonldData['address']['addressLocality']) . '"';
				}

				if (isset($jsonldData['address']['addressRegion'])) {
					$jsonldOutput .= ',
	"addressRegion": "' . betterseo_json_str($jsonldData['address']['addressRegion']) . '"';
				}

				if (isset($jsonldData['address']['postalCode'])) {
					$jsonldOutput .= ',
	"postalCode": "' . betterseo_json_str($jsonldData['address']['postalCode']) . '"';
				}

				if (isset($jsonldData['address']['addressCountry'])) {
					$jsonldOutput .= ',
	"addressCountry": "' . betterseo_json_str($jsonldData['address']['addressCountry']) . '"';
				}

				$jsonldOutput .= '
  }';
			}

			// Geo coordinates
			if (isset($jsonldData['geo']) && is_array($jsonldData['geo'])) {
				$jsonldOutput .= ',
  "geo": {
	"@type": "GeoCoordinates"';

				if (isset($jsonldData['geo']['latitude'])) {
					$jsonldOutput .= ',
	"latitude": "' . betterseo_json_str($jsonldData['geo']['latitude']) . '"';
				}

				if (isset($jsonldData['geo']['longitude'])) {
					$jsonldOutput .= ',
	"longitude": "' . betterseo_json_str($jsonldData['geo']['longitude']) . '"';
				}

				$jsonldOutput .= '
  }';
			}

			if (isset($jsonldData['hasMap'])) {
				$jsonldOutput .= ',
  "hasMap": "' . betterseo_json_str($jsonldData['hasMap']) . '"';
			}

			if (isset($jsonldData['priceRange'])) {
				$jsonldOutput .= ',
  "priceRange": "' . betterseo_json_str($jsonldData['priceRange']) . '"';
			}

			if (isset($jsonldData['openingHoursSpecification']) && is_array($jsonldData['openingHoursSpecification'])) {
				$jsonldOutput .= ',
  "openingHoursSpecification": [';

				$specCount = count($jsonldData['openingHoursSpecification']);
				foreach ($jsonldData['openingHoursSpecification'] as $index => $spec) {
					$day = isset($spec['dayOfWeek'][0]) ? $spec['dayOfWeek'][0] : '';
					$jsonldOutput .= '
	{
	  "@type": "OpeningHoursSpecification",
	  "dayOfWeek": ["' . betterseo_json_str($day) . '"],
	  "opens": "' . betterseo_json_str($spec['opens'] ?? '') . '",
	  "closes": "' . betterseo_json_str($spec['closes'] ?? '') . '"
	}';
					if ($index < $specCount - 1) {
						$jsonldOutput .= ',';
					}
				}

				$jsonldOutput .= '
  ]';
			}

			$jsonldOutput .= '
}';

			$seo .= '<!-- JSON-LD Schema -->
<script type="application/ld+json">' . $jsonldOutput . '
</script>

';
		}
	}

	// Per-page Article schema
	if ($betterseo_content_type === 'article') {
		$betterseo_pageslug = (string) return_page_slug();
		$betterseo_pagexml_path = GSDATAPAGESPATH . $betterseo_pageslug . '.xml';
		$betterseo_pubdate = '';
		$betterseo_author = '';
		if (file_exists($betterseo_pagexml_path)) {
			$betterseo_pagexml = @simplexml_load_file($betterseo_pagexml_path);
			if ($betterseo_pagexml !== false) {
				$betterseo_pubdate = (string) $betterseo_pagexml->pubDate;
				$betterseo_author = (string) $betterseo_pagexml->author;
			}
		}

		$articleOutput = '
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "' . betterseo_json_str(get_page_title($echo = false)) . '",
  "description": "' . betterseo_json_str(descJSON()) . '",
  "url": "' . betterseo_json_str(get_page_url(true)) . '"';

		$betterseo_pubdate_iso = $betterseo_pubdate !== '' ? date('c', strtotime($betterseo_pubdate)) : '';
		if ($betterseo_pubdate_iso !== '') {
			$articleOutput .= ',
  "datePublished": "' . betterseo_json_str($betterseo_pubdate_iso) . '",
  "dateModified": "' . betterseo_json_str($betterseo_pubdate_iso) . '"';
		}

		if ($betterseo_author !== '') {
			$articleOutput .= ',
  "author": {
	"@type": "Person",
	"name": "' . betterseo_json_str($betterseo_author) . '"
  }';
		}

		$articleOutput .= ',
  "publisher": {
	"@type": "Organization",
	"name": "' . betterseo_json_str(get_site_name($echo = false)) . '"
  }';

		if (!empty($imageseo)) {
			$articleOutput .= ',
  "image": "' . betterseo_json_str($imageseo) . '"';
		}

		$articleOutput .= '
}';

		$seo .= '	<!-- Article Schema (per-page) -->
<script type="application/ld+json">' . $articleOutput . '
</script>

';
	}

	echo $seo;

	get_scripts_frontend();

	exec_action('theme-header');
}

/* ==========================================================================
   ADMINISTRATION PANEL
   ========================================================================== */

function betterSEO() {
	global $SITEURL, $USR;

	// PROCESS SAVE BEFORE PRODUCING ANY OUTPUT
	if (isset($_POST['submit'])) {
		if (empty($_POST['nonce']) || !check_nonce($_POST['nonce'], 'save', '')) {
			die('CSRF detected!');
		}

		$settings = BetterSeo_load_settings();

		// General
		$settings['general']['homepagetitle'] = $_POST['homepagetitle'] ?? 'normal';

		// Verification
		$settings['verification']['google'] = trim($_POST['googleverify'] ?? '');
		$settings['verification']['bing']   = trim($_POST['bingverify'] ?? '');

		// Favicon
		$settings['favicon']['enabled'] = isset($_POST['favicon']);

		// Dublin Core
		$settings['dublin_core']['enabled']  = isset($_POST['dublincheck']);
		$settings['dublin_core']['language'] = trim($_POST['dublin'] ?? '');

		// GeoLocation
		$settings['geolocation']['enabled'] = isset($_POST['geocheck']);
		$settings['geolocation']['code']    = trim($_POST['geocode'] ?? '');

		// Facebook
		$settings['facebook']['enabled']      = isset($_POST['facebookcheck']);
		$settings['facebook']['custom_field'] = trim($_POST['fbcustom'] ?? '');
		$settings['facebook']['multifield']   = trim($_POST['multifieldcustom'] ?? '');
		$settings['facebook']['image']        = trim($_POST['fbimage'] ?? '');

		// Twitter
		$settings['twitter']['enabled']  = isset($_POST['twittercheck']);
		$settings['twitter']['username'] = trim($_POST['twitter'] ?? '');

		// Apple
		$settings['apple']['enabled'] = isset($_POST['applecheck']);

		// JSON-LD
		$settings['jsonld']['enabled'] = isset($_POST['jsonldcheck']);

		$jsonldData = [];
		$jsonType = $_POST['jsontype'] ?? '';

		if ($jsonType) {
			$jsonldData['@context'] = 'https://schema.org';
			$jsonldData['@type'] = $jsonType;

			switch ($jsonType) {
				case 'Local Business':
					$bType = $_POST['bType'] ?? '';
					if ($bType) {
						$jsonldData['@type'] = $bType;
					}
					
					if (!empty($_POST['bName'])) $jsonldData['name'] = trim($_POST['bName']);
					if (!empty($_POST['bTelephone'])) $jsonldData['telephone'] = trim($_POST['bTelephone']);
					if (!empty($_POST['bGoogleMap'])) $jsonldData['hasMap'] = trim($_POST['bGoogleMap']);
					if (!empty($_POST['bPriceRange'])) $jsonldData['priceRange'] = trim($_POST['bPriceRange']);
					
					$address = [];
					if (!empty($_POST['bAddress'])) $address['streetAddress'] = trim($_POST['bAddress']);
					if (!empty($_POST['bCity'])) $address['addressLocality'] = trim($_POST['bCity']);
					if (!empty($_POST['bState'])) $address['addressRegion'] = trim($_POST['bState']);
					if (!empty($_POST['bPostalCode'])) $address['postalCode'] = trim($_POST['bPostalCode']);
					if (!empty($_POST['bCountry'])) $address['addressCountry'] = trim($_POST['bCountry']);
					
					if (!empty($address)) {
						$address['@type'] = 'PostalAddress';
						$jsonldData['address'] = $address;
					}
					
					$geo = [];
					if (!empty($_POST['bLatitude'])) $geo['latitude'] = trim($_POST['bLatitude']);
					if (!empty($_POST['bLongitude'])) $geo['longitude'] = trim($_POST['bLongitude']);
					
					if (!empty($geo)) {
						$geo['@type'] = 'GeoCoordinates';
						$jsonldData['geo'] = $geo;
					}
					
					$openingHoursSpecification = [];
					$days = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
					$dayNames = [
						'Mo' => 'Monday',
						'Tu' => 'Tuesday',
						'We' => 'Wednesday',
						'Th' => 'Thursday',
						'Fr' => 'Friday',
						'Sa' => 'Saturday',
						'Su' => 'Sunday'
					];

					foreach ($days as $day) {
						if (isset($_POST['day']) && in_array($day, $_POST['day'])) {
							$openTimes = $_POST[$day . '_open'] ?? [];
							$closeTimes = $_POST[$day . '_close'] ?? [];
							
							for ($i = 0; $i < count($openTimes); $i++) {
								if (!empty($openTimes[$i]) && !empty($closeTimes[$i])) {
									$openingHoursSpecification[] = [
										'@type' => 'OpeningHoursSpecification',
										'dayOfWeek' => [$dayNames[$day]],
										'opens' => $openTimes[$i],
										'closes' => $closeTimes[$i]
									];
								}
							}
						}
					}

					if (!empty($openingHoursSpecification)) {
						$jsonldData['openingHoursSpecification'] = $openingHoursSpecification;
					}
					break;
					
				case 'Organization':
					$oType = $_POST['oType'] ?? 'Organization';
					if ($oType) {
						$jsonldData['@type'] = $oType;
					}
					
					if (!empty($_POST['oName'])) $jsonldData['name'] = trim($_POST['oName']);
					if (!empty($_POST['oTelephone'])) $jsonldData['telephone'] = trim($_POST['oTelephone']);
					if (!empty($_POST['oGoogleMap'])) $jsonldData['hasMap'] = trim($_POST['oGoogleMap']);
					
					$geo = [];
					if (!empty($_POST['oLatitude'])) $geo['latitude'] = trim($_POST['oLatitude']);
					if (!empty($_POST['oLongitude'])) $geo['longitude'] = trim($_POST['oLongitude']);
					
					if (!empty($geo)) {
						$geo['@type'] = 'GeoCoordinates';
						$jsonldData['geo'] = $geo;
					}
					
					$address = [];
					if (!empty($_POST['oAddress'])) $address['streetAddress'] = trim($_POST['oAddress']);
					if (!empty($_POST['oCity'])) $address['addressLocality'] = trim($_POST['oCity']);
					if (!empty($_POST['oState'])) $address['addressRegion'] = trim($_POST['oState']);
					if (!empty($_POST['oPostalCode'])) $address['postalCode'] = trim($_POST['oPostalCode']);
					if (!empty($_POST['oCountry'])) $address['addressCountry'] = trim($_POST['oCountry']);
					
					if (!empty($address)) {
						$address['@type'] = 'PostalAddress';
						$jsonldData['address'] = $address;
					}
					break;
					
				case 'Person':
					if (!empty($_POST['pName'])) $jsonldData['name'] = trim($_POST['pName']);
					if (!empty($_POST['pGender'])) $jsonldData['gender'] = trim($_POST['pGender']);
					if (!empty($_POST['pTelephone'])) $jsonldData['telephone'] = trim($_POST['pTelephone']);
					if (!empty($_POST['pNationality'])) $jsonldData['nationality'] = trim($_POST['pNationality']);
					if (!empty($_POST['pBirthPlace'])) $jsonldData['birthPlace'] = trim($_POST['pBirthPlace']);
					if (!empty($_POST['pBirthDate'])) $jsonldData['birthDate'] = trim($_POST['pBirthDate']);
					if (!empty($_POST['pJobTitle'])) $jsonldData['jobTitle'] = trim($_POST['pJobTitle']);
					if (!empty($_POST['pAlumniOf'])) $jsonldData['alumniOf'] = trim($_POST['pAlumniOf']);
					
					$address = [];
					if (!empty($_POST['pAddress'])) $address['streetAddress'] = trim($_POST['pAddress']);
					if (!empty($_POST['pCity'])) $address['addressLocality'] = trim($_POST['pCity']);
					if (!empty($_POST['pState'])) $address['addressRegion'] = trim($_POST['pState']);
					if (!empty($_POST['pPostalCode'])) $address['postalCode'] = trim($_POST['pPostalCode']);
					if (!empty($_POST['pCountry'])) $address['addressCountry'] = trim($_POST['pCountry']);
					
					if (!empty($address)) {
						$address['@type'] = 'PostalAddress';
						$jsonldData['address'] = $address;
					}
					break;
			}
		}

		$settings['jsonld']['data'] = $jsonldData;

		BetterSeo_save_settings($settings);

		// Redirect with success flag
		$target_url = 'load.php?id=BetterSeo&upd=seo-success';
		if (!headers_sent()) {
			redirect($target_url);
			exit;
		} else {
			echo '<script>window.location.href = ' . json_encode($target_url) . ';</script>';
			echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target_url, ENT_QUOTES) . '"></noscript>';
			return;
		}
	}

	// Render native GetSimple CE success message if updated
	if (isset($_GET['upd']) && $_GET['upd'] === 'seo-success') {
		echo '<div class="updated">' . i18n_r('SETTINGS_UPDATED') . '</div>';
	}

	// Load settings for UI rendering
	$settings = BetterSeo_load_settings();
	$imageseo = $settings['facebook']['image'] ?? '';
	$jsonldData = $settings['jsonld']['data'] ?? [];

	$html = '
		<style>
			.seoguy{
				background:#fafafa;
				border:solid 1px #ddd;
				padding:20px;
				box-sizing:border-box;
				display:block;
				width:100%;
			}
			.seoguy p{
				margin:0;
				margin-bottom:10px;
				margin-top:10px;
			}
			.seoguy hr{
				border:none;
				border-bottom:dotted 1px rgba(0,0,0,0.6);
				margin:20px 0;
			}
			.seoguy textarea,input{
				border:solid 1px rgba(0,0,0,0.3);
				border-bottom:solid 3px green;
			}
			.seoguy textarea{
				box-sizing:border-box;
				width:100%;
			}
			.checkbox-circle{
				width:15px;
				height:15px;
				background:#fff;
				display:block;
				border-radius:50%;
				transition:all 250ms linear;
			}
			.seoguy input[type="checkbox"]  + .checkbox{
				width:60px;
				height:25px;
				border-radius:15px;
				background:red;
				display:block;
				padding:5px;
				box-sizing:border-box;
				margin-bottom:10px;
			}
			.seoguy input[type="checkbox"]:checked + .checkbox{
				content:"sd";
				background:green;
				display:block;
			}
			input[type="checkbox"]:checked + .checkbox .checkbox-circle{
				margin-left:35px;
			}
			.seoguy input[type="checkbox"]{
				display:none;
			}
			 .leader{
				font-size:0.9rem;
				font-style:italic;
				color:rgba(0,0,0,0.6);
			}
			.seoguy h3{
				margin:0;
			}
			.seoguy .submit{
				width:200px;
				height:40px;
				display:block;
				padding:10px;
				text-align:center;
				margin-top:20px;  
				background: green !important;
				color: white !important;
				border-radius: 10px;
			}
			.tab{
				width:100%;
				height:50px;
				border-bottom:solid 1px #ddd;
				display:flex;
				margin-bottom:20px;
			}
			.tab-item{
				width:100px;
				height:50px;
				display:flex;
				align-items:center;
				justify-content:center;
				cursor:pointer;
			}
			.tab-item-active{
				border:solid 1px #ddd;
				border-bottom:solid 1px #fff;	
			}
			.tab-item p{
				margin:0;
				padding-bottom:5px;
			}
			.tab-item-active p{
				border-bottom:solid 3px green;	
			}
			.seocode{
				background:#fafafa; color:rgba(0,0,0,0.8); width:100%; border:solid 1px #ddd; display:block; padding:15px; box-sizing:border-box; border-left:solid 5px green; font-style:italic;
			}
			.seoguy-select{
				width:100%;
				padding:10px;
				border:solid 1px rgba(0,0,0,0.3);
				background:#fff;
				margin-top:10px;
				border-bottom:solid 3px green;
			}
			.seoguy-input {
				width: 100%;
				padding: 8px;
				margin-bottom: 10px;
				border: 1px solid #ddd;
				border-bottom: 3px solid green;
				box-sizing: border-box;
			}
			.row {
				display: flex;
				margin-bottom: 20px;
			}
			.col5, .col7 {
				padding: 10px;
			}
			label {
				display: inline-block;
				margin-right: 15px;
			}
			input[type="checkbox"].dow {display: inline-block !important; visibility: visible !important;}
			.time-container {
				display: none;
				margin-top: 10px;
				margin-bottom: 15px;
			}
			.time-row {
				display: flex;
				gap: 10px;
				margin-bottom: 5px;
			}
			.time-col {
				flex: 1;
			}
			.time-col label {
				display: block;
				margin-bottom: 5px;
				font-size: 0.8rem;
			}
			.time-col select {
				width: 100%;
				padding: 5px;
				border: 1px solid #ddd;
			}
			.time-slot {
				border: 1px solid #ddd;
				padding: 10px;
				margin-bottom: 10px;
				border-radius: 5px;
				background: #f9f9f9;
			}
			.time-row {
				display: flex;
				gap: 10px;
				align-items: flex-end;
			}
			.time-col {
				flex: 1;
			}
			.time-col:last-child {
				flex: 0 0 auto;
			}
			.time-col label {
				display: block;
				margin-bottom: 5px;
				font-size: 0.8rem;
			}
			#jsonld-div input {color:blue}
			.donateButton {
				box-shadow:inset 0px 1px 0px 0px #fff6af;
				background:linear-gradient(to bottom, #ffec64 5%, #ffab23 100%);
				background-color:#ffec64;
				border-radius:6px;
				border:1px solid #ffaa22;
				display:inline-block;
				cursor:pointer;
				color:#333333;
				font-family:Arial;
				font-size:15px;
				font-weight:bold;
				padding:6px 24px;
				text-decoration:none!important;
				text-shadow:0px 1px 0px #ffee66;
			}
			.donateButton:hover {
				background:linear-gradient(to bottom, #ffab23 5%, #ffec64 100%);
				background-color:#ffab23;
			}
			.donateButton:active {
				position:relative;
				top:1px;
			}
			hr.style-two {
				border: 0;
				height: 1px;
				background-image: linear-gradient(to right, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0));
				margin-bottom:30px;
			}

			/* Thumbnail preview card */
			.bseo-preview-wrapper {
				display: none;
				margin-top: 14px;
				align-items: center;
				gap: 12px;
				padding: 10px;
				background: #fff;
				border: 1px solid #e0e0e0;
				border-radius: 8px;
				max-width: 480px;
			}
			.bseo-preview-thumb {
				width: 75px;
				height: 75px;
				object-fit: cover;
				border-radius: 6px;
				border: 1px solid #ddd;
				background: #fafafa;
			}
			.bseo-preview-clear {
				background: #e74c3c;
				color: #fff;
				border: none;
				border-radius: 5px;
				padding: 6px 12px;
				font-size: 11px;
				cursor: pointer;
			}
			.bseo-preview-clear:hover {
				background: #c0392b;
			}
		</style>

		<script>
		var imageseo = ' . json_encode($imageseo ?? "") . ';
		</script>
		
		<h3 style="font-weight:bold; font-style:normal; font-size:1.3rem;">' . i18n_r('BetterSeo/LANG_Settings') . '</h3>
		<p>' . i18n_r('BetterSeo/LANG_Description') . '</p>
		<hr class="style-two">
		
		<div class="tab">
			<div class="tab-item tab-item-active" id="tab1"><p><b>' . i18n_r('BetterSeo/LANG_Setup') . '</b></p></div>
			<div class="tab-item" id="tab2"><p><b>' . i18n_r('BetterSeo/LANG_Help') . '</b></p></div>
		</div>

		<div class="tab-content-1">
			<form method="post" class="seoguy">
				<input type="hidden" name="nonce" value="' . get_nonce('save', '') . '">
				
				<h3>' . i18n_r('BetterSeo/LANG_Homepage_Title') . '</h3>

				<select name="homepagetitle" class="seoguy-select">
					<option value="normal"' . (($settings['general']['homepagetitle'] ?? 'normal') === 'normal' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Normal') . '</option>
					<option value="titlefirst"' . (($settings['general']['homepagetitle'] ?? '') === 'titlefirst' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Website_Name') . '</option>
					<option value="titleonly"' . (($settings['general']['homepagetitle'] ?? '') === 'titleonly' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Only_Website_Name') . '</option>
				</select>
				
				<hr>

				<h3>' . i18n_r('BetterSeo/LANG_Site_Verification') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_Site_Verification_Text') . '</p>

				<p>' . i18n_r('BetterSeo/LANG_Google_Verification') . ':</p>
				<input type="text" style="width:100%;padding:10px;box-sizing:border-box;" name="googleverify" placeholder="abc123..." value="' . htmlspecialchars($settings['verification']['google'] ?? '', ENT_QUOTES) . '">

				<p>' . i18n_r('BetterSeo/LANG_Bing_Verification') . ':</p>
				<input type="text" style="width:100%;padding:10px;box-sizing:border-box;" name="bingverify" placeholder="abc123..." value="' . htmlspecialchars($settings['verification']['bing'] ?? '', ENT_QUOTES) . '">

				<hr>

				<h3 style="margin-top:20px;">' . i18n_r('BetterSeo/LANG_Favicons') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_Favicons_Text') . '</p>

				<label>
					<input type="checkbox" name="favicon"' . (!empty($settings['favicon']['enabled']) ? ' checked' : '') . '>
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<hr>
				
				<h3>' . i18n_r('BetterSeo/LANG_DublinCore') . 'Dublin Core</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_DublinCore_Text') . '</p>
				
				<label>
					<input type="checkbox" name="dublincheck"' . (!empty($settings['dublin_core']['enabled']) ? ' checked' : '') . '>
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>
				
				<div id="dublin-div">
					<p>' . i18n_r('BetterSeo/LANG_Language_Code') . '</p>
					<input type="text" style="width:100%;padding:10px;box-sizing:border-box;" name="dublin" placeholder="en" value="' . htmlspecialchars($settings['dublin_core']['language'] ?? '', ENT_QUOTES) . '">
				</div>
				
				<hr>

				<h3>' . i18n_r('BetterSeo/LANG_GeoLocation') . '</h3>

				<p class="leader">' . i18n_r('BetterSeo/LANG_GeoLocation_Text') . '</p>

				<label>
					<input type="checkbox" name="geocheck"' . (!empty($settings['geolocation']['enabled']) ? ' checked' : '') . '>

					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<div id="geo-div">
					<textarea name="geocode" style="height:150px; color:blue;">' . htmlspecialchars($settings['geolocation']['code'] ?? '', ENT_QUOTES) . '</textarea>
				</div>
				
				<hr>
				
				<h3 style="margin-top:20px;">' . i18n_r('BetterSeo/LANG_Facebook') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_Facebook_Text') . '</p>
			
				<label>
					<input type="checkbox" name="facebookcheck"' . (!empty($settings['facebook']['enabled']) ? ' checked' : '') . '>
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<div id="fb-div">
					<p>' . i18n_r('BetterSeo/LANG_Custom_Field_Name') . ': </p>
					<input type="text" name="fbcustom" value="' . htmlspecialchars($settings['facebook']['custom_field'] ?? '', ENT_QUOTES) . '" style="width:100%; padding:10px; box-sizing:border-box; color:blue;" placeholder="my-customField">
					
					<br>

					<p>' . i18n_r('BetterSeo/LANG_MultiField_Name') . ': </p>
					<input type="text" name="multifieldcustom" value="' . htmlspecialchars($settings['facebook']['multifield'] ?? '', ENT_QUOTES) . '" style="width:100%; padding:10px; box-sizing:border-box; color:blue;" placeholder="my-multiField">
				 
					<p>' . i18n_r('BetterSeo/LANG_Static_Image') . ':</p>
					<div style="display:flex;gap:10px;align-items:center;">
						<input type="text" id="bseo_fbimage_input" style="flex:1; padding:10px; box-sizing:border-box; color:blue" name="fbimage" value="' . htmlspecialchars($settings['facebook']['image'] ?? '', ENT_QUOTES) . '" placeholder="Image URL">
						<button type="button" style="background: orangered; color: #fff; border: none; padding: 10px 15px; cursor: pointer; border-radius: 7px; white-space:nowrap;" onclick="window.open(`' . $SITEURL . 'plugins/BetterSeo/files/imagebrowser.php?&func=multifield[]&count=0`,`myWindow`,`toolbar=no,scrollbars=yes,menubar=no,width=680,height=580`)">' . i18n_r('BetterSeo/LANG_Select_Photo') . '</button>
					</div>

					<!-- Thumbnail preview -->
					<div id="bseo_fbimage_preview" class="bseo-preview-wrapper">
						<img id="bseo_fbimage_thumb" class="bseo-preview-thumb" src="" alt="Thumbnail preview">
						<div style="display:flex;flex-direction:column;gap:5px;">
							<span style="font-size:12px;font-weight:600;color:#333;">Selected Image Preview</span>
							<button type="button" id="bseo_clear_image" class="bseo-preview-clear">Remove Image</button>
						</div>
					</div>
				</div>

				<hr>
				
				<h3>' . i18n_r('BetterSeo/LANG_Twitter') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_Twitter_Text') . '</p>
				
				<label>
					<input type="checkbox" name="twittercheck"' . (!empty($settings['twitter']['enabled']) ? ' checked' : '') . '>
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>
				
				<div id="twitter-div">
					<p>' . i18n_r('BetterSeo/LANG_Twitter_Username') . ':</p>
					<input type="text" style="width:100%;padding:10px;box-sizing:border-box;" name="twitter" placeholder="@YourTwitterUsername" value="' . htmlspecialchars($settings['twitter']['username'] ?? '', ENT_QUOTES) . '">
				</div>

				<hr>
				
				<h3>' . i18n_r('BetterSeo/LANG_Apple') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_Apple_Text') . '</p>
				
				<label>
					<input type="checkbox" name="applecheck"' . (!empty($settings['apple']['enabled']) ? ' checked' : '') . '>
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<hr>

				<h3>' . i18n_r('BetterSeo/LANG_JSON_LD') . '</h3>
				<p class="leader">' . i18n_r('BetterSeo/LANG_JSON_LD_Text') . '</p>

				<label>
					<input type="checkbox" name="jsonldcheck"' . (!empty($settings['jsonld']['enabled']) ? ' checked' : '') . '>

					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<div id="jsonld-div">
					<select name="jsontype" id="jsontype" class="seoguy-select">
						<option value="Local Business"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Local Business' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Local_Business') . '</option>
						<option value="Organization"' . (isset($jsonldData['@type']) && in_array($jsonldData['@type'], ['Organization', 'Corporation', 'EducationalOrganization', 'GovernmentOrganization', 'NGO', 'PerformingGroup', 'SportsTeam']) ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Organization') . '</option>
						<option value="Person"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Person' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Person') . '</option>
					</select>

					<div id="localBusiness" style="display:none;">
						<p style="margin-bottom:0px;">' . i18n_r('BetterSeo/LANG_Type') . ':</p>
						<select id="bType" name="bType" class="seoguy-select">
							<option value="">- ' . i18n_r('BetterSeo/LANG_Select_Business_Type') . ' -</option>
							<option value="AnimalShelter"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AnimalShelter' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AnimalShelter') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Automotive') . '">
								<option value="AutomotiveBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutomotiveBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutomotiveBusiness') . '</option>
								<option value="AutoBodyShop"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoBodyShop' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoBodyShop') . '</option>
								<option value="AutoDealer"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoDealer' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoDealer') . '</option>
								<option value="AutoPartsStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoPartsStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoPartsStore') . '</option>
								<option value="AutoRental"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoRental' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoRental') . '</option>
								<option value="AutoRepair"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoRepair' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoRepair') . '</option>
								<option value="AutoWash"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutoWash' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutoWash') . '</option>
								<option value="GasStation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GasStation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GasStation') . '</option>
								<option value="MotorcycleDealer"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MotorcycleDealer' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MotorcycleDealer') . '</option>
								<option value="MotorcycleRepair"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MotorcycleRepair' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MotorcycleRepair') . '</option>
							</optgroup>
							<option value="ChildCare"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ChildCare' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ChildCare') . '</option>
							<option value="DryCleaningOrLaundry"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'DryCleaningOrLaundry' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_DryCleaningOrLaundry') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Emergency') . '">
								<option value="EmergencyService"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'EmergencyService' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_EmergencyService') . '</option>
								<option value="FireStation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'FireStation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_FireStation') . '</option>
								<option value="Hospital"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Hospital' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Hospital') . '</option>
								<option value="PoliceStation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'PoliceStation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_PoliceStation') . '</option>
							</optgroup>
							<option value="EmploymentAgency"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'EmploymentAgency' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_EmploymentAgency') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Entertainment') . '">
								<option value="EntertainmentBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'EntertainmentBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_EntertainmentBusiness') . '</option>
								<option value="AdultEntertainment"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AdultEntertainment' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AdultEntertainment') . '</option>
								<option value="AmusementPark"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AmusementPark' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AmusementPark') . '</option>
								<option value="ArtGallery"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ArtGallery' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ArtGallery') . '</option>
								<option value="Casino"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Casino' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Casino') . '</option>
								<option value="ComedyClub"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ComedyClub' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ComedyClub') . '</option>
								<option value="MovieTheater"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MovieTheater' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MovieTheater') . '</option>
								<option value="NightClub"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'NightClub' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_NightClub') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Financial') . '">
								<option value="FinancialService"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'FinancialService' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_FinancialService') . '</option>
								<option value="AccountingService"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AccountingService' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AccountingService') . '</option>
								<option value="AutomatedTeller"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'AutomatedTeller' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_AutomatedTeller') . '</option>
								<option value="BankOrCreditUnion"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BankOrCreditUnion' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BankOrCreditUnion') . '</option>
								<option value="InsuranceAgency"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'InsuranceAgency' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_InsuranceAgency') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Food_Drink') . '">
								<option value="FoodEstablishment"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'FoodEstablishment' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_FoodEstablishment') . '</option>
								<option value="Bakery"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Bakery' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Bakery') . '</option>
								<option value="BarOrPub"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BarOrPub' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BarOrPub') . '</option>
								<option value="Brewery"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Brewery' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Brewery') . '</option>
								<option value="CafeOrCoffeeShop"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'CafeOrCoffeeShop' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_CafeOrCoffeeShop') . '</option>
								<option value="FastFoodRestaurant"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'FastFoodRestaurant' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_FastFoodRestaurant') . '</option>
								<option value="IceCreamShop"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'IceCreamShop' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_IceCreamShop') . '</option>
								<option value="Restaurant"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Restaurant' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Restaurant') . '</option>
								<option value="Winery"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Winery' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Winery') . '</option>
								<option value="Butcher"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Butcher' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Butcher') . '</option>
								<option value="GroceryStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GroceryStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GroceryStore') . '</option>
								<option value="LiquorStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'LiquorStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_LiquorStore') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Government') . '">
								<option value="GovernmentOffice"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GovernmentOffice' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GovernmentOffice') . '</option>
								<option value="PostOffice"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'PostOffice' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_PostOffice') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Health_Beauty') . '">
								<option value="HealthAndBeautyBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HealthAndBeautyBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HealthAndBeautyBusiness') . '</option>
								<option value="BeautySalon"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BeautySalon' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BeautySalon') . '</option>
								<option value="DaySpa"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'DaySpa' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_DaySpa') . '</option>
								<option value="HairSalon"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HairSalon' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HairSalon') . '</option>
								<option value="HealthClub"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HealthClub' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HealthClub') . '</option>
								<option value="NailSalon"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'NailSalon' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_NailSalon') . '</option>
								<option value="TattooParlor"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'TattooParlor' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_TattooParlor') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Home_Construction') . '">
								<option value="HomeAndConstructionBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HomeAndConstructionBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HomeAndConstructionBusiness') . '</option>
								<option value="Electrician"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Electrician' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Electrician') . '</option>
								<option value="GeneralContractor"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GeneralContractor' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GeneralContractor') . '</option>
								<option value="HVACBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HVACBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HVACBusiness') . '</option>
								<option value="HousePainter"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HousePainter' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HousePainter') . '</option>
								<option value="Locksmith"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Locksmith' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Locksmith') . '</option>
								<option value="MovingCompany"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MovingCompany' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MovingCompany') . '</option>
								<option value="Plumber"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Plumber' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Plumber') . '</option>
								<option value="RoofingContractor"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'RoofingContractor' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_RoofingContractor') . '</option>
								<option value="HardwareStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HardwareStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HardwareStore') . '</option>
							</optgroup>
							<option value="InternetCafe"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'InternetCafe' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_InternetCafe') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Legal') . '">
								<option value="LegalService"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'LegalService' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_LegalService') . '</option>
								<option value="Attorney"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Attorney' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Attorney') . '</option>
								<option value="Notary"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Notary' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Notary') . '</option>
							</optgroup>
							<option value="Library"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Library' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Library') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Lodging') . '">
								<option value="LodgingBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'LodgingBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_LodgingBusiness') . '</option>
								<option value="BedAndBreakfast"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BedAndBreakfast' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BedAndBreakfast') . '</option>
								<option value="Campground"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Campground' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Campground') . '</option>
								<option value="Hostel"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Hostel' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Hostel') . '</option>
								<option value="Hotel"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Hotel' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Hotel') . '</option>
								<option value="Motel"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Motel' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Motel') . '</option>
								<option value="Resort"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Resort' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Resort') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Medical') . '">
								<option value="MedicalBusiness"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MedicalBusiness' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MedicalBusiness') . '</option>
								<option value="Dentist"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Dentist' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Dentist') . '</option>
								<option value="DiagnosticLab"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'DiagnosticLab' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_DiagnosticLab') . '</option>
								<option value="MedicalClinic"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MedicalClinic' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MedicalClinic') . '</option>
								<option value="Pharmacy"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Pharmacy' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Pharmacy') . '</option>
								<option value="Physician"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Physician' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Physician') . '</option>
								<option value="VeterinaryCare"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'VeterinaryCare' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_VeterinaryCare') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Professional_Services') . '">
								<option value="Photographer"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Photographer' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Photographer') . '</option>
								<option value="ProfessionalService"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ProfessionalService' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ProfessionalService') . '</option>
							</optgroup>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Real_Estate') . '">
								<option value="RealEstateAgent"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'RealEstateAgent' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_RealEstateAgent') . '</option>
							</optgroup>
							<option value="RadioStation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'RadioStation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_RadioStation') . '</option>
							<option value="RecyclingCenter"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'RecyclingCenter' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_RecyclingCenter') . '</option>
							<option value="SelfStorage"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'SelfStorage' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_SelfStorage') . '</option>
							<option value="SportsActivityLocation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'SportsActivityLocation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_SportsActivityLocation') . '</option>
							<optgroup label="• ' . i18n_r('BetterSeo/LANG_Shopping') . '">
								<option value="Store"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Store' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Store') . '</option>
								<option value="BikeStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BikeStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BikeStore') . '</option>
								<option value="BookStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'BookStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_BookStore') . '</option>
								<option value="ClothingStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ClothingStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ClothingStore') . '</option>
								<option value="ComputerStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ComputerStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ComputerStore') . '</option>
								<option value="ConvenienceStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ConvenienceStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ConvenienceStore') . '</option>
								<option value="ElectronicsStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ElectronicsStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ElectronicsStore') . '</option>
								<option value="Florist"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Florist' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Florist') . '</option>
								<option value="FurnitureStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'FurnitureStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_FurnitureStore') . '</option>
								<option value="GardenStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GardenStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GardenStore') . '</option>
								<option value="HobbyShop"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'HobbyShop' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_HobbyShop') . '</option>
								<option value="JewelryStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'JewelryStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_JewelryStore') . '</option>
								<option value="MusicStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'MusicStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_MusicStore') . '</option>
								<option value="OfficeEquipmentStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'OfficeEquipmentStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_OfficeEquipmentStore') . '</option>
								<option value="PetStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'PetStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_PetStore') . '</option>
								<option value="ShoeStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ShoeStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ShoeStore') . '</option>
								<option value="ShoppingCenter"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ShoppingCenter' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ShoppingCenter') . '</option>
								<option value="SportingGoodsStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'SportingGoodsStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_SportingGoodsStore') . '</option>
								<option value="ToyStore"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'ToyStore' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_ToyStore') . '</option>
							</optgroup>
							<option value="TelevisionStation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'TelevisionStation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_TelevisionStation') . '</option>
							<option value="TouristInformationCenter"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'TouristInformationCenter' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_TouristInformationCenter') . '</option>
							<option value="TravelAgency"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'TravelAgency' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_TravelAgency') . '</option>
						</select>
						
						<p>' . i18n_r('BetterSeo/LANG_Business_Name') . ':</p>
						<input type="text" name="bName" value="' . (isset($jsonldData['name']) ? htmlspecialchars($jsonldData['name'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Business Name">
						<p>' . i18n_r('BetterSeo/LANG_Telephone') . ':</p>
						<input type="text" name="bTelephone" value="' . (isset($jsonldData['telephone']) ? htmlspecialchars($jsonldData['telephone'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="+1-555-555-5555">
						<p>' . i18n_r('BetterSeo/LANG_Address') . ':</p>
						<input type="text" name="bAddress" value="' . (isset($jsonldData['address']['streetAddress']) ? htmlspecialchars($jsonldData['address']['streetAddress'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="123 Main St">
						<p>' . i18n_r('BetterSeo/LANG_City') . ':</p>
						<input type="text" name="bCity" value="' . (isset($jsonldData['address']['addressLocality']) ? htmlspecialchars($jsonldData['address']['addressLocality'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="City">
						<p>' . i18n_r('BetterSeo/LANG_State_Province') . ':</p>
						<input type="text" name="bState" value="' . (isset($jsonldData['address']['addressRegion']) ? htmlspecialchars($jsonldData['address']['addressRegion'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="State">
						<p>' . i18n_r('BetterSeo/LANG_Postal_Code') . ':</p>
						<input type="text" name="bPostalCode" value="' . (isset($jsonldData['address']['postalCode']) ? htmlspecialchars($jsonldData['address']['postalCode'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="12345">
						<p>' . i18n_r('BetterSeo/LANG_Country') . ':</p>
						<input type="text" name="bCountry" value="' . (isset($jsonldData['address']['addressCountry']) ? htmlspecialchars($jsonldData['address']['addressCountry'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Country">
						
						<p>' . i18n_r('BetterSeo/LANG_Latitude') . ':</p>
						<input type="text" name="bLatitude" value="' . (isset($jsonldData['geo']['latitude']) ? htmlspecialchars($jsonldData['geo']['latitude'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="40.7128">
						<p>' . i18n_r('BetterSeo/LANG_Longitude') . ':</p>
						<input type="text" name="bLongitude" value="' . (isset($jsonldData['geo']['longitude']) ? htmlspecialchars($jsonldData['geo']['longitude'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="-74.0060">
						<p>' . i18n_r('BetterSeo/LANG_Google_Map') . ':</p>
						<input type="text" name="bGoogleMap" value="' . (isset($jsonldData['hasMap']) ? htmlspecialchars($jsonldData['hasMap'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="https://maps.google.com">
						
						<p>' . i18n_r('BetterSeo/LANG_Business_Hours') . ':</p>
							' . generateBusinessHoursFields() . '
							
						<p>' . i18n_r('BetterSeo/LANG_Price_Range') . ':</p>
						<select name="bPriceRange" class="seoguy-select">
							<option value="">- ' . i18n_r('BetterSeo/LANG_Choose') . ' -</option>
							<option value="$"' . (isset($jsonldData['priceRange']) && $jsonldData['priceRange'] == '$' ? ' selected' : '') . '>$ = ' . i18n_r('BetterSeo/LANG_Inexpensive') . '</option>
							<option value="$$"' . (isset($jsonldData['priceRange']) && $jsonldData['priceRange'] == '$$' ? ' selected' : '') . '>$$ = ' . i18n_r('BetterSeo/LANG_Moderately_Expensive') . '</option>
							<option value="$$$"' . (isset($jsonldData['priceRange']) && $jsonldData['priceRange'] == '$$$' ? ' selected' : '') . '>$$$ = ' . i18n_r('BetterSeo/LANG_Expensive') . '</option>
							<option value="$$$$"' . (isset($jsonldData['priceRange']) && $jsonldData['priceRange'] == '$$$$' ? ' selected' : '') . '>$$$$ = ' . i18n_r('BetterSeo/LANG_Very_Expensive') . '</option>
						</select>
					</div>

					<div id="organization" style="display:none;">
						<p style="margin-bottom:0px;">' . i18n_r('BetterSeo/LANG_Type') . ':</p>
						<select id="oType" name="oType" class="seoguy-select">
							<option value="">- ' . i18n_r('BetterSeo/LANG_Select_Org_Type') . ' -</option>
							<option value="Organization"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Organization' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Organization') . '</option>
							<option value="Corporation"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'Corporation' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Corporation') . '</option>
							<option value="EducationalOrganization"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'EducationalOrganization' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_EducationalOrganization') . '</option>
							<option value="GovernmentOrganization"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'GovernmentOrganization' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_GovernmentOrganization') . '</option>
							<option value="NGO"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'NGO' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_NGO') . '</option>
							<option value="PerformingGroup"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'PerformingGroup' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_PerformingGroup') . '</option>
							<option value="SportsTeam"' . (isset($jsonldData['@type']) && $jsonldData['@type'] == 'SportsTeam' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_SportsTeam') . '</option>
						</select>
						
						<p>' . i18n_r('BetterSeo/LANG_Org_Name') . ':</p>
						<input type="text" name="oName" value="' . (isset($jsonldData['name']) ? htmlspecialchars($jsonldData['name'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Organization Name">
						<p>' . i18n_r('BetterSeo/LANG_Telephone') . ':</p>
						<input type="text" name="oTelephone" value="' . (isset($jsonldData['telephone']) ? htmlspecialchars($jsonldData['telephone'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="+1-555-555-5555">
						
						<p>' . i18n_r('BetterSeo/LANG_Address') . ':</p>
						<input type="text" name="oAddress" value="' . (isset($jsonldData['address']['streetAddress']) ? htmlspecialchars($jsonldData['address']['streetAddress'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="123 Main St">
						<p>' . i18n_r('BetterSeo/LANG_City') . ':</p>
						<input type="text" name="oCity" value="' . (isset($jsonldData['address']['addressLocality']) ? htmlspecialchars($jsonldData['address']['addressLocality'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="City">
						<p>' . i18n_r('BetterSeo/LANG_State_Province') . ':</p>
						<input type="text" name="oState" value="' . (isset($jsonldData['address']['addressRegion']) ? htmlspecialchars($jsonldData['address']['addressRegion'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="State">
						<p>' . i18n_r('BetterSeo/LANG_Postal_Code') . ':</p>
						<input type="text" name="oPostalCode" value="' . (isset($jsonldData['address']['postalCode']) ? htmlspecialchars($jsonldData['address']['postalCode'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="12345">
						<p>' . i18n_r('BetterSeo/LANG_Country') . ':</p>
						<input type="text" name="oCountry" value="' . (isset($jsonldData['address']['addressCountry']) ? htmlspecialchars($jsonldData['address']['addressCountry'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Country">
						
						<p>' . i18n_r('BetterSeo/LANG_Latitude') . ':</p>
						<input type="text" name="oLatitude" value="' . (isset($jsonldData['geo']['latitude']) ? htmlspecialchars($jsonldData['geo']['latitude'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="40.7128">
						<p>' . i18n_r('BetterSeo/LANG_Longitude') . ':</p>
						<input type="text" name="oLongitude" value="' . (isset($jsonldData['geo']['longitude']) ? htmlspecialchars($jsonldData['geo']['longitude'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="-74.0060">
						<p>' . i18n_r('BetterSeo/LANG_Google_Map') . ':</p>
						<input type="text" name="oGoogleMap" value="' . (isset($jsonldData['hasMap']) ? htmlspecialchars($jsonldData['hasMap'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="https://maps.google.com">
					</div>

					<div id="person" style="display:none;">
						<p>' . i18n_r('BetterSeo/LANG_Name') . ':</p>
						<input type="text" name="pName" value="' . (isset($jsonldData['name']) ? htmlspecialchars($jsonldData['name'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Full Name">
							
						<p>' . i18n_r('BetterSeo/LANG_Gender') . ':</p>
						<select name="pGender" class="seoguy-select">
							<option value="">- Choose -</option>
							<option value="male"' . (isset($jsonldData['gender']) && $jsonldData['gender'] == 'male' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Male') . '</option>
							<option value="female"' . (isset($jsonldData['gender']) && $jsonldData['gender'] == 'female' ? ' selected' : '') . '>' . i18n_r('BetterSeo/LANG_Female') . '</option>
						</select>
						
						<p>' . i18n_r('BetterSeo/LANG_Birth_Place') . ':</p>
						<input type="text" name="pBirthPlace" value="' . (isset($jsonldData['birthPlace']) ? htmlspecialchars($jsonldData['birthPlace'], ENT_QUOTES) : '') . '" class="seoguy-input" maxlength="10">
						
						<p>' . i18n_r('BetterSeo/LANG_Birth_Date') . ':</p>
						<input type="text" name="pBirthDate" value="' . (isset($jsonldData['birthDate']) ? htmlspecialchars($jsonldData['birthDate'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="YYYY-MM-DD" maxlength="10">
							
						<p>' . i18n_r('BetterSeo/LANG_Nationality') . ':</p>
						<input type="text" name="pNationality" value="' . (isset($jsonldData['nationality']) ? htmlspecialchars($jsonldData['nationality'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="American">
							
						<p>' . i18n_r('BetterSeo/LANG_Job') . ':</p>
						<input type="text" name="pJobTitle" value="' . (isset($jsonldData['jobTitle']) ? htmlspecialchars($jsonldData['jobTitle'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Job Title">
						
						<p>' . i18n_r('BetterSeo/LANG_Alumni') . ':</p>
						<input type="text" name="pAlumniOf" value="' . (isset($jsonldData['alumniOf']) ? htmlspecialchars($jsonldData['alumniOf'], ENT_QUOTES) : '') . '" class="seoguy-input">
							
						<p>' . i18n_r('BetterSeo/LANG_Telephone') . ':</p>
						<input type="text" name="pTelephone" value="' . (isset($jsonldData['telephone']) ? htmlspecialchars($jsonldData['telephone'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="+1-555-555-5555">
						
						<p>' . i18n_r('BetterSeo/LANG_Address') . ':</p>
						<input type="text" name="pAddress" value="' . (isset($jsonldData['address']['streetAddress']) ? htmlspecialchars($jsonldData['address']['streetAddress'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="123 Main St">
						<p>' . i18n_r('BetterSeo/LANG_City') . ':</p>
						<input type="text" name="pCity" value="' . (isset($jsonldData['address']['addressLocality']) ? htmlspecialchars($jsonldData['address']['addressLocality'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="City">
						<p>' . i18n_r('BetterSeo/LANG_State_Province') . ':</p>
						<input type="text" name="pState" value="' . (isset($jsonldData['address']['addressRegion']) ? htmlspecialchars($jsonldData['address']['addressRegion'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="State">
						<p>' . i18n_r('BetterSeo/LANG_Postal_Code') . ':</p>
						<input type="text" name="pPostalCode" value="' . (isset($jsonldData['address']['postalCode']) ? htmlspecialchars($jsonldData['address']['postalCode'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="12345">
						<p>' . i18n_r('BetterSeo/LANG_Country') . ':</p>
						<input type="text" name="pCountry" value="' . (isset($jsonldData['address']['addressCountry']) ? htmlspecialchars($jsonldData['address']['addressCountry'], ENT_QUOTES) : '') . '" class="seoguy-input" placeholder="Country">
					</div>
				</div>

				<input type="submit" name="submit" value="' . i18n_r('BetterSeo/LANG_Save_Settings') . '" class="submit">
			</form>
			
			<div id="paypal" style="padding-top:20px">
				<a href="https://getsimple-ce.ovh/donate" target="_blank" class="donateButton">' . i18n_r('BetterSeo/LANG_PayPal') . '<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-opacity="0" d="M17 14v4c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-4Z"><animate fill="freeze" attributeName="fill-opacity" begin="0.8s" dur="0.5s" values="0;1"></animate></path><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke-dasharray="48" stroke-dashoffset="48" d="M17 9v9c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-9Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="48;0"></animate></path><path stroke-dasharray="14" stroke-dashoffset="14" d="M17 9h3c0.55 0 1 0.45 1 1v3c0 0.55 -0.45 1 -1 1h-3"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.6s" dur="0.2s" values="14;0"></animate></path><mask id="lineMdCoffeeHalfEmptyFilledLoop0"><path stroke="#fff" d="M8 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4M12 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4M16 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4"><animateMotion calcMode="linear" dur="3s" path="M0 0v-8" repeatCount="indefinite"></animateMotion></path></mask><rect width="24" height="0" y="7" fill="currentColor" mask="url(#lineMdCoffeeHalfEmptyFilledLoop0)"><animate fill="freeze" attributeName="y" begin="0.8s" dur="0.6s" values="7;2"></animate><animate fill="freeze" attributeName="height" begin="0.8s" dur="0.6s" values="0;5"></animate></rect></g></svg></a>
			</div>
		</div>
		
		<div class="tab-content-2" style="display:none;">
			<h4 class="w3-margin-top w3-margin-bottom">' . i18n_r('BetterSeo/LANG_Installation') . ':</h4>
			<p>' . i18n_r('BetterSeo/LANG_Installation_Text') . '</p>
			
			<hr>
			
			<h4 class="w3-margin-top w3-margin-bottom">' . i18n_r('BetterSeo/LANG_Info') . ':</h4>
			<ul>
				<li><a href="https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data" target="_blank">Google Structured Data</a></li>
				<li><a href="https://developers.facebook.com/docs/sharing/webmasters/" target="_blank">Facebook Open Graph</a></li>
				<li><a href="https://developer.twitter.com/en/docs/tweets/optimize-with-cards/overview/abouts-cards" target="_blank">Twitter/X Cards</a></li>
				<li><a href="https://www.geo-tag.de/generator/en.html" target="_blank">GeoLocation Generator</a></li>
				<li><a href="https://getsimple-ce.ovh/favicon_generator_suite/" target="_blank">Favicon Generator</a></li>
				<li><a href="https://search.google.com/search-console/about" target="_blank">Google Search Console</a></li>
				<li><a href="https://www.bing.com/webmasters" target="_blank">Bing Webmaster Tools</a></li>
				<li><a href="https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag" target="_blank">Robots Meta Tag Reference</a></li>
				<li><a href="https://schema.org/Article" target="_blank">Schema.org: Article</a></li>
				<li><a href="https://search.google.com/test/rich-results" target="_blank">Google Rich Results Test</a></li>
			</ul>
			
			<hr>
			
			<h4 class="w3-margin-top w3-margin-bottom">' . i18n_r('BetterSeo/LANG_Whats_New') . ':</h4><br>
			<p>
				<b>v5.0</b>
				<ul>
				<li>Consolidated all settings and per-page configuration into a single, unified <code>settings.json</code> file.</li>
				<li>Automated, lossless data migration engine from legacy .txt and .json files with safety backups.</li>
				<li>Atomic file writes with advisory locking (<code>LOCK_EX</code>) for high reliability.</li>
				<li>Added file upload functionality to the image browser.</li>
				<li>Added real-time static image thumbnail preview with removal button.</li>
				<li>Added standard GetSimple CE notification banner on settings update.</li>
				<li>Compatibility verified for PHP 7.4 through PHP 8.5+.</li>
				</ul>
			</p>
			<p>
				<b>v4.0</b>
				<ul>
				<li>Added per-page SEO controls on the Edit Page screen: hide from search engines (noindex) and don\'t follow links (nofollow)</li>
				<li>og:type / DC.Type and Article schema (JSON-LD) are now automatic: website on the homepage, article on every other page</li>
				<li>Added Google Search Console / Bing Webmaster Tools site verification meta tags</li>
				<li>Added a live Search Result Preview on the Edit Page screen</li>
				<li>Security hardening: escaped stored settings output, added CSRF protection</li>
				</ul>
			</p>
		</div>
		
		<script>
		// Tab functionality
		document.querySelectorAll(".tab-item").forEach(function(item) {
			item.addEventListener("click", function() {
				document.querySelectorAll(".tab-item").forEach(function(i) {
					i.classList.remove("tab-item-active");
				});
				document.querySelectorAll(".tab-content-1, .tab-content-2").forEach(function(i) {
					i.style.display = "none";
				});
				this.classList.add("tab-item-active");
				if (this.id === "tab1") {
					document.querySelector(".tab-content-1").style.display = "block";
				} else {
					document.querySelector(".tab-content-2").style.display = "block";
				}
			});
		});
		
		// Toggle Dublin block 
		document.addEventListener("DOMContentLoaded", function() {
			const checkbox = document.querySelector(\'input[name="dublincheck"]\');
			const dublinDiv = document.getElementById("dublin-div");
			function toggleDiv() {
				dublinDiv.style.display = checkbox.checked ? "block" : "none";
			}
			toggleDiv();
			checkbox.addEventListener("change", toggleDiv);
		});
		
		// Toggle Twitter block 
		document.addEventListener("DOMContentLoaded", function() {
			const checkbox = document.querySelector(\'input[name="twittercheck"]\');
			const twitterDiv = document.getElementById("twitter-div");
			function toggleDiv() {
				twitterDiv.style.display = checkbox.checked ? "block" : "none";
			}
			toggleDiv();
			checkbox.addEventListener("change", toggleDiv);
		});
		
		// Toggle GEO block 
		document.addEventListener("DOMContentLoaded", function() {
			const checkbox = document.querySelector(\'input[name="geocheck"]\');
			const geoDiv = document.getElementById("geo-div");
			function toggleDiv() {
				geoDiv.style.display = checkbox.checked ? "block" : "none";
			}
			toggleDiv();
			checkbox.addEventListener("change", toggleDiv);
		});
		
		// Toggle FB block 
		document.addEventListener("DOMContentLoaded", function() {
			const checkbox = document.querySelector(\'input[name="facebookcheck"]\');
			const fbDiv = document.getElementById("fb-div");
			function toggleDiv() {
				fbDiv.style.display = checkbox.checked ? "block" : "none";
			}
			toggleDiv();
			checkbox.addEventListener("change", toggleDiv);
		});
		
		// Toggle JSON-LD block 
		document.addEventListener("DOMContentLoaded", function() {
			const checkbox = document.querySelector(\'input[name="jsonldcheck"]\');
			const jsonldDiv = document.getElementById("jsonld-div");
			function toggleDiv() {
				jsonldDiv.style.display = checkbox.checked ? "block" : "none";
			}
			toggleDiv();
			checkbox.addEventListener("change", toggleDiv);
		});

		// JSON-LD Type selector functionality
		const jsonTypeSelect = document.getElementById("jsontype");
		const localBusinessDiv = document.getElementById("localBusiness");
		const organizationDiv = document.getElementById("organization");
		const personDiv = document.getElementById("person");

		function showJsonFields() {
			localBusinessDiv.style.display = "none";
			organizationDiv.style.display = "none";
			personDiv.style.display = "none";
			
			switch(jsonTypeSelect.value) {
				case "Local Business":
					localBusinessDiv.style.display = "block";
					break;
				case "Organization":
					organizationDiv.style.display = "block";
					break;
				case "Person":
					personDiv.style.display = "block";
					break;
			}
		}
		
		// Add event listeners to day checkboxes
		document.querySelectorAll("input[name=\'day[]\']").forEach(checkbox => {
			checkbox.addEventListener("change", function() {
				toggleTimeContainer(this);
			});
		});

		function toggleTimeContainer(checkbox) {
			const day = checkbox.value;
			const container = document.getElementById("time-container-" + day);
			container.style.display = checkbox.checked ? "block" : "none";
		}
		
		// Time slot additions/removals
		document.addEventListener("click", function(e) {
			if (e.target.classList.contains("add-time-slot")) {
				const day = e.target.getAttribute("data-day");
				const timeSlotsContainer = document.getElementById("time-slots-" + day);
				
				const newSlot = document.createElement("div");
				newSlot.className = "time-slot";
				newSlot.style.marginBottom = "10px";
				newSlot.innerHTML = \'<div class="time-row"><div class="time-col"><label>Open</label><select name="\' + day + \'_open[]" class="seoguy-select">\' + generateTimeOptions() + \'</select></div><div class="time-col"><label>Close</label><select name="\' + day + \'_close[]" class="seoguy-select">\' + generateTimeOptions() + \'</select></div><div class="time-col" style="display: flex; align-items: flex-end;"><button type="button" class="add-time-slot" style="background: green; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer;" data-day="\' + day + \'">+</button><button type="button" class="remove-time-slot" style="background: red; color: white; border: none; padding: 5px 10px; cursor: pointer;">x</button></div></div>\';
				
				timeSlotsContainer.appendChild(newSlot);
			}
			
			if (e.target.classList.contains("remove-time-slot")) {
				const timeSlot = e.target.closest(".time-slot");
				if (timeSlot) {
					timeSlot.remove();
				}
			}
		});

		function generateTimeOptions() {
			let options = \'\';
			for (let h = 0; h < 24; h++) {
				for (let m = 0; m < 60; m += 30) {
					const time = String(h).padStart(2, \'0\') + \':\' + String(m).padStart(2, \'0\');
					options += \'<option value="\' + time + \'">\' + time + \'</option>\';
				}
			}
			return options;
		}

		document.querySelectorAll("input[name=\'day[]\']").forEach(checkbox => {
			toggleTimeContainer(checkbox);
		});

		showJsonFields();
		jsonTypeSelect.addEventListener("change", showJsonFields);

		// Static image thumbnail preview handler
		var fbInput = document.getElementById("bseo_fbimage_input");
		var fbPreviewBox = document.getElementById("bseo_fbimage_preview");
		var fbThumb = document.getElementById("bseo_fbimage_thumb");
		var fbClear = document.getElementById("bseo_clear_image");

		window.updateFbImagePreview = function() {
			var val = fbInput.value.trim();
			if (val !== "") {
				fbThumb.src = val;
				fbPreviewBox.style.display = "flex";
			} else {
				fbThumb.src = "";
				fbPreviewBox.style.display = "none";
			}
		};

		if (fbInput) {
			fbInput.addEventListener("input", window.updateFbImagePreview);
			fbInput.addEventListener("change", window.updateFbImagePreview);
		}

		if (fbClear) {
			fbClear.addEventListener("click", function() {
				fbInput.value = "";
				window.updateFbImagePreview();
			});
		}

		// Run preview on initial view
		window.updateFbImagePreview();
		</script>
	';

	echo $html;
}

function generateBusinessHoursFields() {
	$day_codes = [
		'Mo' => 'Monday',
		'Tu' => 'Tuesday', 
		'We' => 'Wednesday',
		'Th' => 'Thursday',
		'Fr' => 'Friday',
		'Sa' => 'Saturday',
		'Su' => 'Sunday'
	];
	
	$day_labels = [
		'Mo' => i18n_r('BetterSeo/LANG_Monday'),
		'Tu' => i18n_r('BetterSeo/LANG_Tuesday'), 
		'We' => i18n_r('BetterSeo/LANG_Wednesday'),
		'Th' => i18n_r('BetterSeo/LANG_Thursday'),
		'Fr' => i18n_r('BetterSeo/LANG_Friday'),
		'Sa' => i18n_r('BetterSeo/LANG_Saturday'),
		'Su' => i18n_r('BetterSeo/LANG_Sunday')
	];
	
	$settings = BetterSeo_load_settings();
	$jsonldData = $settings['jsonld']['data'] ?? [];

	$html = '';
	foreach ($day_codes as $code => $day_name) {
		$isChecked = false;
		$timeSlots = [];
		
		if (isset($jsonldData['openingHoursSpecification']) && is_array($jsonldData['openingHoursSpecification'])) {
			foreach ($jsonldData['openingHoursSpecification'] as $spec) {
				if (isset($spec['dayOfWeek']) && is_array($spec['dayOfWeek'])) {
					foreach ($spec['dayOfWeek'] as $dayOfWeek) {
						$normalizedDayOfWeek = strtolower(preg_replace('/https?:\/\/schema\.org\//', '', $dayOfWeek));
						$normalizedCurrentDay = strtolower($day_name);
						
						if ($normalizedDayOfWeek === $normalizedCurrentDay || 
							strpos($normalizedDayOfWeek, $normalizedCurrentDay) !== false ||
							strpos($normalizedCurrentDay, $normalizedDayOfWeek) !== false) {
							$isChecked = true;
							$timeSlots[] = [
								'open'  => $spec['opens'] ?? '',
								'close' => $spec['closes'] ?? ''
							];
							break;
						}
					}
				}
			}
		}
		
		if (empty($timeSlots)) {
			$timeSlots[] = ['open' => '', 'close' => ''];
		}
		
		$html .= '
		<label style="display: block; margin-bottom: 20px;">
			<input type="checkbox" name="day[]" value="' . $code . '" class="dow"' . ($isChecked ? ' checked' : '') . '>
			' . $day_labels[$code] . '
			<div id="time-container-' . $code . '" class="time-container" style="' . ($isChecked ? 'display: block;' : 'display: none;') . '">
				<div id="time-slots-' . $code . '">';
		
		foreach ($timeSlots as $index => $slot) {
			$html .= '
				<div class="time-slot" style="margin-bottom: 10px;">
					<div class="time-row">
						<div class="time-col">
							<label>' . i18n_r('BetterSeo/LANG_Open') . '</label>
							<select name="' . $code . '_open[]" class="seoguy-select">';
			
			$html .= generateTimeOptions($slot['open']);
			
			$html .= '</select>
						</div>
						<div class="time-col">
							<label>' . i18n_r('BetterSeo/LANG_Close') . '</label>
							<select name="' . $code . '_close[]" class="seoguy-select">';
			
			$html .= generateTimeOptions($slot['close']);
			
			$html .= '</select>
						</div>
						<div class="time-col" style="display: flex; align-items: flex-end;">
							<button type="button" class="add-time-slot" style="background: green; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer;" data-day="' . $code . '">+</button>';
			
			if ($index > 0) {
				$html .= '<button type="button" class="remove-time-slot" style="background: red; color: white; border: none; padding: 5px 10px; cursor: pointer;">x</button>';
			}
			
			$html .= '
						</div>
					</div>
				</div>';
		}
		
		$html .= '
				</div>
			</div>
		</label>';
	}

	return $html;
}

function generateTimeOptions($selectedTime = '') {
	$options = '';
	for ($h = 0; $h < 24; $h++) {
		for ($m = 0; $m < 60; $m += 30) {
			$time = sprintf("%02d:%02d", $h, $m);
			$selected = ($time === $selectedTime) ? ' selected' : '';
			$options .= '<option value="' . $time . '"' . $selected . '>' . $time . '</option>';
		}
	}
	return $options;
}
?>