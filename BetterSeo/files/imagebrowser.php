<?php

	include('../../../gsconfig.php');
	$admin = defined('GSADMIN') ? GSADMIN : 'admin';
	include("../../../".$admin."/inc/common.php");
	$loggedin = cookie_check();
	if (!$loggedin) die("Not logged in!");
	if (!defined('IN_GS')) {
		die('you cannot load this page directly.');
	}

	i18n_merge('i18n_gallery', substr($LANG, 0, 2));
	i18n_merge('i18n_gallery', 'en');

	if (isset($_GET['path'])) {
		$subPath = preg_replace('/\.+\//', '', $_GET['path']);
		if ($subPath) $subPath = trim($subPath, '/') . '/';
		$path = "../../../data/uploads/" . $subPath;
	} else {
		$subPath = "";
		$path = "../../../data/uploads/";
	}
	$path = tsl($path);

	// Handle File Upload
	$upload_status = null;
	$upload_error = null;

	if (isset($_POST['upload_submit'])) {
		if (empty($_POST['nonce']) || !check_nonce($_POST['nonce'], 'betterseo_img_upload', '')) {
			$upload_error = 'Security check failed (CSRF).';
		} elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			$upload_error = 'Upload error or no file selected.';
		} else {
			$raw_filename = basename($_FILES['file']['name']);
			$clean_name = function_exists('clean_img_name') ? clean_img_name($raw_filename) : preg_replace('/[^a-zA-Z0-9._-]/', '', $raw_filename);
			$ext = strtolower(pathinfo($clean_name, PATHINFO_EXTENSION));
			$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

			if (!in_array($ext, $allowed, true)) {
				$upload_error = 'Invalid image extension (' . htmlspecialchars($ext, ENT_QUOTES) . '). Allowed: jpg, jpeg, png, gif, webp.';
			} else {
				$target_file = $path . $clean_name;
				// Prevent overwriting existing file by adding suffix
				$name_base = pathinfo($clean_name, PATHINFO_FILENAME);
				$i = 1;
				while (file_exists($target_file)) {
					$clean_name = $name_base . '_' . $i . '.' . $ext;
					$target_file = $path . $clean_name;
					$i++;
				}

				if (@move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
					@chmod($target_file, 0644);
					$upload_status = 'File uploaded successfully: ' . htmlspecialchars($clean_name, ENT_QUOTES);
				} else {
					$upload_error = 'Failed to move uploaded file to target folder.';
				}
			}
		}
	}

	// check if host uses Linux (used for displaying permissions)
	$isUnixHost = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? false : true);
	$path_parts = pathinfo($_SERVER['PHP_SELF']);
	$dir = str_replace("/plugins/BetterSeo/files", "", $path_parts['dirname']);
	$fullPath = htmlentities("http://" . $_SERVER['SERVER_NAME'] . ($dir == '/' ? "" : $dir) . "/data/uploads/", ENT_QUOTES);
	$sitepath = htmlentities("http://" . $_SERVER['SERVER_NAME'] . ($dir == '/' ? "" : $dir) . "/", ENT_QUOTES);

	$func = preg_replace('/[^\w]/', '', @$_GET['func'] ?? '');
	$w = (int) @$_GET['w'];
	$h = (int) @$_GET['h'];
	if (!$w && !$h) {
		$w = 160;
		$h = 120;
	}
	$autoclose = @$_GET['autoclose'];
	$debug = @$_GET['debug'];

	global $LANG;
	$LANG_header = preg_replace('/(?:(?<=([a-z]{2}))).*/', '', $LANG);
	$count = "0";
	$dircount = "0";
	$counter = "0";
	$totalsize = 0;
	$filesArray = [];
	$dirsArray = [];

	clearstatcache();
	$dir_handle = opendir($path) or die("Unable to open $path");
	while ($file = readdir($dir_handle)) {
		if ($file == "." || $file == ".." || $file == ".htaccess") {
			// not an upload file
		} elseif (is_dir($path . $file)) {
			$dirsArray[$dircount]['name'] = $file;
			$dircount++;
		} else {
			$ext = @strtolower(substr($file, strrpos($file, '.') + 1));
			if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'png'  || $ext == 'webp') {
				$ss = @stat($path . $file);
				list($width, $height) = @getimagesize($path . $file);
				$filesArray[] = [
					'name'        => $file,
					'date'        => @date('M j, Y', $ss['ctime']),
					'size'        => fSize($ss['size']),
					'bytes'       => $ss['size'],
					'width'       => $width,
					'height'      => $height,
					'title'       => @$info['title'],
					'tags'        => @$info['tags'],
					'description' => @$info['description'],
					'debug'       => @$info['debug']
				];
				$totalsize = $totalsize + $ss['size'];
				$count++;
			}
		}
	}
	closedir($dir_handle);

	$filesSorted = subval_sort($filesArray, 'name');
	$dirsSorted = subval_sort($dirsArray, 'name');

	$pathParts = explode("/", trim($subPath, '/'));
	$urlPath = "";

?>
<!DOCTYPE html>
<html lang="<?php echo $LANG_header; ?>">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?php echo i18n_r('FILE_BROWSER'); ?></title>
		<link rel="shortcut icon" href="../../../<?php echo $admin; ?>/favicon.png" type="image/x-icon" />
		<link rel="stylesheet" type="text/css" href="../../../<?php echo $admin; ?>/template/style.php?v=<?php echo GSVERSION; ?>" media="screen" />
		<style>
			body#imagebrowser {
				background: #f5f6f8;
				margin: 0;
				padding: 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			}

			#imagebrowser .main {
				border: none !important;
				background: transparent;
				padding: 16px 20px 32px;
				box-sizing: border-box;
				width: 100%;
			}

			.ib-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 12px;
				margin-bottom: 14px;
			}

			.ib-header h3 {
				margin: 0;
				font-size: 19px;
				font-weight: 700;
				color: #1a1a2e;
			}

			.ib-upload-box {
				display: flex;
				align-items: center;
				gap: 8px;
				background: #fff;
				border: 1px dashed #2764e7;
				padding: 6px 12px;
				border-radius: 8px;
			}

			.ib-upload-box input[type="file"] {
				font-size: 12px;
				color: #4b5563;
			}

			.ib-upload-btn {
				background: #2764e7;
				color: #fff;
				border: none;
				padding: 6px 14px;
				font-size: 12px;
				font-weight: 600;
				border-radius: 5px;
				cursor: pointer;
				transition: background .15s ease;
			}

			.ib-upload-btn:hover {
				background: #1d4ed8;
			}

			.ib-alert {
				padding: 9px 14px;
				border-radius: 6px;
				margin-bottom: 14px;
				font-size: 13px;
			}

			.ib-alert-success {
				background: #def7ec;
				color: #03543f;
				border: 1px solid #bcf0da;
			}

			.ib-alert-error {
				background: #fde8e8;
				color: #9b1c1c;
				border: 1px solid #f8b4b4;
			}

			.ib-breadcrumb {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: 4px;
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				padding: 8px 14px;
				margin-bottom: 16px;
				font-size: 13.5px;
				color: #6b7280;
			}

			.ib-breadcrumb a {
				color: #2764e7;
				text-decoration: none;
				font-weight: 600;
				padding: 2px 4px;
				border-radius: 4px;
				transition: background .12s ease, color .12s ease;
			}

			.ib-breadcrumb a:hover {
				background: #eef3fe;
				color: #0094f0;
			}

			.ib-breadcrumb .sep {
				color: #c7cad1;
				padding: 0 1px;
			}

			.ib-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(95px, 1fr));
				gap: 12px;
			}

			.ib-card {
				display: flex;
				flex-direction: column;
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				overflow: hidden;
				text-decoration: none;
				color: inherit;
				cursor: pointer;
				transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
			}

			.ib-card:hover {
				transform: translateY(-2px);
				box-shadow: 0 5px 12px rgba(39, 100, 231, .14);
				border-color: #b9cdfa;
			}

			.ib-card-thumb {
				width: 100%;
				aspect-ratio: 1 / 1;
				object-fit: cover;
				display: block;
				background: #f0f2f5;
			}

			.ib-card-folder {
				display: flex;
				align-items: center;
				justify-content: center;
				aspect-ratio: 1 / 1;
				background: linear-gradient(155deg, #eef3fe, #f7f9ff);
			}

			.ib-card-body {
				padding: 6px 8px 8px;
				border-top: 1px solid #f0f1f3;
			}

			.ib-card-name {
				font-size: 11.5px;
				font-weight: 600;
				color: #1a1a2e;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.ib-card-meta {
				margin-top: 2px;
				font-size: 10px;
				color: #9199a6;
				display: flex;
				justify-content: space-between;
				gap: 4px;
			}

			.ib-footer {
				margin-top: 20px;
				font-size: 12px;
				color: #9199a6;
			}

			.ib-empty {
				grid-column: 1 / -1;
				text-align: center;
				padding: 36px 16px;
				color: #9199a6;
				font-size: 13px;
			}
		</style>
	</head>

	<body id="imagebrowser">
		<div class="main">
			<div class="ib-header">
				<h3><?php i18n('UPLOADED_FILES'); ?></h3>
				
				<!-- File Upload Form -->
				<form method="post" enctype="multipart/form-data" class="ib-upload-box">
					<input type="hidden" name="nonce" value="<?php echo get_nonce('betterseo_img_upload', ''); ?>" />
					<input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp" required />
					<button type="submit" name="upload_submit" class="ib-upload-btn"><?php echo i18n_r('UPLOAD'); ?></button>
				</form>
			</div>

			<?php if ($upload_status): ?>
				<div class="ib-alert ib-alert-success"><?php echo $upload_status; ?></div>
			<?php endif; ?>

			<?php if ($upload_error): ?>
				<div class="ib-alert ib-alert-error"><?php echo $upload_error; ?></div>
			<?php endif; ?>

			<div class="ib-breadcrumb">
				<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="16" height="16" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path fill="#f90" d="M4 20q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4h6l2 2h8q.825 0 1.413.588T22 8v10q0 .825-.587 1.413T20 20z"></path></svg> 
				<a href="?func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&amp;autoclose=<?php echo htmlspecialchars($autoclose, ENT_QUOTES); ?>">uploads</a>
				<?php
				foreach ($pathParts as $pathPart) {
					if ($pathPart !== '') {
						$urlPath .= ($urlPath !== '' ? '/' : '') . $pathPart;
						?>
						<span class="sep">/</span>
						<a href="?path=<?php echo htmlspecialchars($urlPath, ENT_QUOTES); ?>&amp;func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&amp;autoclose=1"><?php echo htmlspecialchars($pathPart, ENT_QUOTES); ?></a>
						<?php
					}
				}
				?>
			</div>

			<div class="ib-grid" id="imageTable">
				<?php
					if (!empty($dirsSorted)) {
						foreach ($dirsSorted as $upload) {
							$p = $subPath . $upload['name'];
				?>
				<a class="ib-card" href="imagebrowser.php?path=<?php echo htmlspecialchars($p, ENT_QUOTES); ?>&amp;func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&amp;autoclose=1" title="<?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?>">
					<div class="ib-card-folder">
						<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path fill="#f90" d="M4 20q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4h6l2 2h8q.825 0 1.413.588T22 8H4v10l2.4-8h17.1l-2.575 8.575q-.2.65-.737 1.038T19 20z"></path></svg>
					</div>
					<div class="ib-card-body">
						<div class="ib-card-name"><?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?></div>
					</div>
				</a>
				<?php
						}
					}

					$metadata = [];
					if (!empty($filesSorted)) {
						foreach ($filesSorted as $upload) {
							$imageUrl = $SITEURL . 'data/uploads/' . $subPath . $upload['name'];
							$metadata[] = ['url' => $imageUrl, 'name' => $upload['name']];
				?>
				<div class="ib-card images" onclick="submitLink('<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>')" title="<?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?>">
					<img class="ib-card-thumb" src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?>" loading="lazy" />
					<div class="ib-card-body">
						<div class="ib-card-name"><?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?></div>
						<div class="ib-card-meta">
							<span><?php echo (int)$upload['width']; ?>&times;<?php echo (int)$upload['height']; ?></span>
							<span><?php echo $upload['size']; ?></span>
						</div>
					</div>
				</div>
				<?php
						}
					}

					if (empty($dirsSorted) && empty($filesSorted)) {
						echo '<div class="ib-empty">' . i18n_r('TOTAL_FILES') . ': 0</div>';
					}
				?>
			</div>

			<p class="ib-footer"><b><?php echo count($filesSorted); ?></b> <?php i18n('TOTAL_FILES'); ?> (<?php echo fSize($totalsize); ?>)</p>

			<script>
				function submitLink(url) {
					if (window.opener && !window.opener.closed) {
						var target = window.opener.document.querySelector('*[name="fbimage"]');
						if (target) {
							target.value = url;
							// Dispatch native change/input events
							target.dispatchEvent(new Event('input', { bubbles: true }));
							target.dispatchEvent(new Event('change', { bubbles: true }));
						}
						// If the parent page has a direct preview updater defined, invoke it
						if (typeof window.opener.updateFbImagePreview === 'function') {
							window.opener.updateFbImagePreview();
						}
					}
					window.close();
				}
			</script>
		</div>
	</body>
</html>