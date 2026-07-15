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
		if ($subPath) $subPath .= '/';
		$path = "../../../data/uploads/" . $subPath;
	} else {
		$subPath = "";
		$path = "../../../data/uploads/";
	}
	$path = tsl($path);

	// check if host uses Linux (used for displaying permissions
	$isUnixHost = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? false : true);
	$path_parts = pathinfo($_SERVER['PHP_SELF']);
	$dir = str_replace("/plugins/i18n_gallery/browser", "", $path_parts['dirname']);
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
			// not a upload file
		} elseif (is_dir($path . $file)) {
			$dirsArray[$dircount]['name'] = $file;
			$dircount++;
		} else {
			$ext = @strtolower(substr($file, strrpos($file, '.') + 1));
			if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'png'  || $ext == 'webp') {
				$ss = @stat($path . $file);
				list($width, $height) = getimagesize($path . $file);
				$filesArray[] = ['name' => $file, 'date' => @date('M j, Y', $ss['ctime']), 'size' => fSize($ss['size']), 'bytes' => $ss['size'], 'width' => $width, 'height' => $height, 'title' => @$info['title'], 'tags' => @$info['tags'], 'description' => @$info['description'], 'debug' => @$info['debug']];
				$totalsize = $totalsize + $ss['size'];
				$count++;
			}
		}
	}
	$filesSorted = subval_sort($filesArray, 'name');
	$dirsSorted = subval_sort($dirsArray, 'name');

	$pathParts = explode("/", $subPath);
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
			.wrapper,
			#maincontent,
			#imageTable {
				width: 100%;
			}

			#imagebrowser {
				background: #f5f6f8;
			}

			#imagebrowser .main {
				border: none !important;
				background: transparent;
				padding: 20px 24px 32px;
				box-sizing: border-box;
			}

			.ib-header {
				display: flex;
				align-items: baseline;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 8px;
				margin-bottom: 14px;
			}

			.ib-header h3 {
				margin: 0;
				font-size: 20px;
				font-weight: 700;
				color: #1a1a2e;
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
				margin-bottom: 20px;
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
				grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
				gap: 14px;
			}

			.ib-card {
				display: flex;
				flex-direction: column;
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 10px;
				overflow: hidden;
				text-decoration: none;
				color: inherit;
				cursor: pointer;
				transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
			}

			.ib-card:hover {
				transform: translateY(-2px);
				box-shadow: 0 6px 16px rgba(39, 100, 231, .14);
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

			.ib-card-folder img {
				width: 46px;
				height: 46px;
				opacity: .85;
			}

			.ib-card-body {
				padding: 8px 10px 10px;
				border-top: 1px solid #f0f1f3;
			}

			.ib-card-name {
				font-size: 12.5px;
				font-weight: 600;
				color: #1a1a2e;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.ib-card-meta {
				margin-top: 2px;
				font-size: 11px;
				color: #9199a6;
				display: flex;
				justify-content: space-between;
				gap: 6px;
			}

			.ib-card-desc {
				margin-top: 4px;
				font-size: 11px;
				color: #6b7280;
				line-height: 1.35;
			}

			.ib-card-desc b {
				color: #1a1a2e;
			}

			.ib-footer {
				margin-top: 22px;
				font-size: 12.5px;
				color: #9199a6;
			}

			.ib-footer b {
				color: #1a1a2e;
			}

			.ib-empty {
				grid-column: 1 / -1;
				text-align: center;
				padding: 48px 16px;
				color: #9199a6;
				font-size: 13.5px;
			}
		</style>
	</head>

	<body id="imagebrowser">
		<div class="wrapper">
			<div id="maincontent">
				<div class="main">
					<div class="ib-header">
						<h3><?php i18n('UPLOADED_FILES'); ?></h3>
					</div>

					<div class="ib-breadcrumb">
						<svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="16" height="16" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path fill="#f90" d="M4 20q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4h6l2 2h8q.825 0 1.413.588T22 8v10q0 .825-.587 1.413T20 20z"></path></svg> <a href="?func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&amp;autoclose=<?php echo htmlspecialchars($autoclose, ENT_QUOTES); ?>">uploads</a>
						<?php
						foreach ($pathParts as $pathPart) {
							if ($pathPart != '') {
								$urlPath .= $pathPart;
								?>
								<span class="sep">/</span>
								<a href="?path=<?php echo htmlspecialchars($urlPath, ENT_QUOTES); ?>&amp;func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&autoclose=1"><?php echo htmlspecialchars($pathPart, ENT_QUOTES); ?></a>
								<?php
								$urlPath .= '/';
							}
						}
						?>
					</div>

					<div class="ib-grid" id="imageTable">
						<?php
							if (count((array)$dirsSorted) != 0) {
								foreach ((array)$dirsSorted as $upload) {
									$p = $subPath . $upload['name'];
						?>
						<a class="ib-card" href="imagebrowser.php?path=<?php echo htmlspecialchars($p, ENT_QUOTES); ?>&amp;func=<?php echo $func; ?>&amp;w=<?php echo $w; ?>&amp;h=<?php echo $h; ?>&autoclose=1&CKEditor=post-content&count=<?php echo (int) ($_GET['count'] ?? 0); ?>" title="<?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?>">
							<div class="ib-card-folder">
								<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path fill="#f90" d="M4 20q-.825 0-1.412-.587T2 18V6q0-.825.588-1.412T4 4h6l2 2h8q.825 0 1.413.588T22 8H4v10l2.4-8h17.1l-2.575 8.575q-.2.65-.737 1.038T19 20z"></path></svg>
							</div>
							<div class="ib-card-body">
								<div class="ib-card-name"><?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?></div>
							</div>
						</a>
						<?php
								}
							}

							$metadata = [];
							if (count((array)$filesSorted) != 0) {
								foreach ((array)$filesSorted as $upload) {
									$onclick = 'submitLink(' . count($metadata) . ')';
									$metadata[] = ['url' => $subPath . $upload['name'], 'size' => $upload['bytes'], 'width' => $upload['width'], 'height' => $upload['height'], 'title' => $upload['title'], 'tags' => $upload['tags'], 'description' => $upload['description']];
									if ($isUnixHost && defined('GSDEBUG') && function_exists('posix_getpwuid')) {
										$filePerms = substr(sprintf('%o', fileperms($path . $upload['name'])), -4);
										$fileOwner = posix_getpwuid(fileowner($path . $upload['name']));
									}
									$ib_title_attr = i18n_r('SELECT_FILE') . ': ' . $upload['name'];
									if (isset($filePerms) && isset($fileOwner['name'])) {
										$ib_title_attr .= ' (' . $fileOwner['name'] . '/' . $filePerms . ')';
									}
						?>
						<div class="ib-card images" onclick="<?php echo $onclick; ?>" title="<?php echo htmlspecialchars($ib_title_attr, ENT_QUOTES); ?>">
							<img class="ib-card-thumb" src="<?php echo $SITEURL . 'data/uploads/' . $subPath . $upload['name']; ?>" alt="<?php echo htmlspecialchars($upload['name'], ENT_QUOTES); ?>" />
							<div class="ib-card-body">
								<div class="ib-card-name"><?php echo htmlspecialchars($upload['name']); ?></div>
								<div class="ib-card-meta">
									<span><?php echo $upload['width']; ?>&times;<?php echo $upload['height']; ?></span>
									<span><?php echo $upload['size']; ?></span>
								</div>
								<?php if (@$upload['title'] || @$upload['tags'] || @$upload['description']): ?>
								<div class="ib-card-desc">
									<?php if (@$upload['title']) echo '<b>' . htmlspecialchars($upload['title']) . '</b><br/>'; ?>
									<?php if (@$upload['tags']) echo '<i>' . htmlspecialchars(implode(', ', $upload['tags'])) . '</i><br/>'; ?>
									<?php if (@$upload['description']) echo preg_replace('/\r?\n/', '<br/>', htmlspecialchars($upload['description'])); ?>
								</div>
								<?php endif; ?>
							</div>
						</div>
						<?php if ($debug) echo '<div class="ib-card-desc" style="grid-column:1/-1;"><pre>' . htmlspecialchars(@$upload['debug']) . '</pre></div>'; ?>
						<?php
								}
							}

							if (count((array)$dirsSorted) == 0 && count((array)$filesSorted) == 0) {
								echo '<div class="ib-empty">' . i18n_r('TOTAL_FILES') . ': 0</div>';
							}
						?>
					</div>

					<p class="ib-footer"><b><?php echo count((array)$filesSorted); ?></b> <?php i18n('TOTAL_FILES'); ?> (<?php echo fSize($totalsize); ?>)</p>

					<?php // foreach ($metadata as &$m) if (!@$m['title']) $m['title'] = basename($m['url']); ?>

				<script type='text/javascript'>
					// <![CDATA[
					var metadata = <?php echo json_encode($metadata); ?>;

					// ]]>
				</script>

				<script>
					function submitLink(e) {
						let linker = document.querySelectorAll('.images img')[e].getAttribute('src');
						console.log(linker);
						window.opener.document.querySelector(`*[name="fbimage"]`).value = linker;
						window.close();
					}
				</script>

				</div>
			</div>
		</div>
	</body>

</html>