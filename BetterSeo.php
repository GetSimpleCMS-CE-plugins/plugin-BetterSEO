<?php

# get correct id for plugin
$thisfile = basename(__FILE__, ".php");

# register plugin
register_plugin(
	$thisfile, 		//Plugin id
	'BetterSEO',	//Plugin name
	'3.3', 			//Plugin version
	'CE Team', 		//Plugin author
	'https://getsimple-ce.ovh/donate', //author website
	'Make Get Simple CMS SEO better!', //Plugin description
	'plugins', 		//page type - on which admin tab to display
	'betterSEO' 	//main function (administration)
);

# activate filter 

# add a link in the admin tab 'theme'
add_action('plugins-sidebar', 'createSideMenu', [$thisfile, 'BetterSEO Settings']);

# functions

function get_seoheader($full = true)
{
	///file
	$folder = GSDATAOTHERPATH . 'betterSEO/';
	
	$geofile = $folder . 'geocheck.txt';
	$geocodefile = $folder . 'geocode.txt';
	
	$facebookcheckfile = $folder . 'facebookcheck.txt';
	$fbcustomfile = $folder . 'fbcustom.txt';
	$fbimagefile = $folder . 'fbimage.txt';
	
	$multifieldfile = $folder . 'fbmultifield.txt';
	
	$dublinfile = $folder . 'dublin.txt';
	$dublincheckfile = $folder . 'dublincheck.txt';
	
	$twitterfile = $folder . 'twitter.txt';
	$twittercheckfile = $folder . 'twittercheck.txt';
	
	$applefile = $folder . 'apple.txt';
	$applecheckfile = $folder . 'applecheck.txt';
	
	$faviconfile = $folder . 'favicon.txt';
	
	$jsonldfile = $folder . 'jsonldcheck.txt';
	$jsonldcodefile = $folder . 'jsonldcode.txt';

	$homepagetitlefile = $folder . 'homepagetitle.txt';

	///
	$homepagetitle = file_exists($homepagetitlefile) ? file_get_contents($homepagetitlefile) : 'normal';
	
	if ($homepagetitle === 'normal') {
		if (return_page_slug() == 'index') {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		} else {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		};
	} elseif ($homepagetitle === 'titlefirst') {
		if (return_page_slug() == 'index') {
			$newSeoTitle = get_site_name($echo = false) . ' | ' . get_page_title($echo = false);
		} else {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		};
	} elseif ($homepagetitle === 'titleonly') {
		if (return_page_slug() == 'index') {
			$newSeoTitle = get_site_name($echo = false);
		} else {
			$newSeoTitle = get_page_title($echo = false) . ' | ' . get_site_name($echo = false);
		};
	}

	function descSeo()
	{
		if (get_page_meta_desc($echo = false) == '') {
			global $content;
			$desc = strip_decode($content);
			if (getDef('GSCONTENTSTRIP', true))
			$desc = strip_content($desc);
			$desc = cleanHtml($desc, ['style', 'script']); // remove unwanted elements that strip_tags fails to remove
			$desc = getExcerpt($desc, 160); // grab 160 chars
			$desc = strip_whitespace($desc); // remove newlines, tab chars
			$desc = str_replace('"', '', $desc); // remove double quotes
			$desc = encode_quotes($desc);
			$desc = trim($desc);
			return $desc;
		} else {
			return get_page_meta_desc($echo = false);
		}
	}

	$seo = '	<!-- Basic Header Needs
		================================================== -->
		<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1.0, shrink-to-fit=no" />
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<base href="' . get_site_url($echo = false) . '">
		<title>' . $newSeoTitle . '</title>
		<meta name="description" content="' . descSeo() . '">
		<meta name="robots" content="index, follow">
		<meta name="copyright" content="' . get_site_name($echo = false) . '">
		<meta http-equiv="last-modified" content="' . get_page_date('D, j M Y G:i:s', $echo = false) . ' GMT">
		<link rel="canonical" href="' . get_page_url($echo = true) . '">
		
	';
	
	if (file_exists($applecheckfile) && file_get_contents($applecheckfile) !== '') {
		$seo .= '	<!-- Apple Web App Tags
		================================================== -->
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-title" content="' . get_site_name($echo = false) . '">
		
	';
	}

	if (file_exists($facebookcheckfile) && file_get_contents($facebookcheckfile) !== '') {
		$imageseo = '';

		if (file_exists($fbimagefile) && file_get_contents($fbimagefile) !== '') {
			$imageseo = file_get_contents($fbimagefile);
		}

		if (file_exists($multifieldfile) && file_get_contents($multifieldfile) !== '') {
			$content = file_get_contents($multifieldfile);
			$imageseo = r_multifields($content);
		}

		if (file_exists($fbcustomfile) && file_get_contents($fbcustomfile) !== '') {
			$content = file_get_contents($fbcustomfile);
			$imageseo = return_custom_field($content);
		}

		$seo .= '	<!-- Facebook Open Graph protocol
		=================================================== -->
		<meta property="og:type" content="article">
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
	
	if (file_exists($twittercheckfile) && file_get_contents($twittercheckfile) !== '') {
		$seo .= '	<!-- Twitter/X Card
		=================================================== -->
		<meta name="twitter:title" content="' . $newSeoTitle . '">
		<meta name="twitter:description" content="' . descSeo() . '">';
		
		if (!empty($imageseo)) {
			$seo .= '
		<meta name="twitter:image" content="' . $imageseo . '">
		<meta name="twitter:card" content="' . $imageseo . '">';
		}
		
		$seo .= '
		
	';
	}

	if (file_exists($dublincheckfile) && file_get_contents($dublincheckfile) !== '') {
		$seo .= '	<!-- Dublin Core Metadata
		=================================================== -->
		<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />
		<meta name="DC.Format" content="text/html" />
		<meta name="DC.Type" content="article" />
		<meta name="DC.Language" content="' . (file_exists($dublinfile) ? file_get_contents($dublinfile) : '') . '" />
		<meta name="DC.Title" content="' . get_page_clean_title($echo = false) . '" />
		<meta name="DC.Creator" content="' . get_site_name($echo = false) . '"/>
		<meta name="DC.Date" content="' . get_page_date('D, j M Y G:i:s', $echo = false) . ' GMT">
		
	';
	}

	if (file_exists($faviconfile) && file_get_contents($faviconfile) !== '') {
		$seo .= '	<!-- Favicons
		=================================================== -->
		<link rel="icon" type="image/png" sizes="36x36" href="' . get_theme_url($echo = false) . '/fav/android-icon-36x36.png">
		<link rel="icon" type="image/png" sizes="48x48" href="' . get_theme_url($echo = false) . '/fav/android-icon-48x48.png">
		<link rel="icon" type="image/png" sizes="72x72" href="' . get_theme_url($echo = false) . '/fav/android-icon-72x72.png">
		<link rel="icon" type="image/png" sizes="96x96" href="' . get_theme_url($echo = false) . '/fav/android-icon-96x96.png">
		<link rel="icon" type="image/png" sizes="144x144" href="' . get_theme_url($echo = false) . '/fav/android-icon-144x144.png">
		<link rel="icon" type="image/png" sizes="192x192" href="' . get_theme_url($echo = false) . '/fav/android-icon-192x192.png">
		
		<link rel="apple-touch-icon" sizes="192x192" href="' . get_theme_url($echo = false) . '/fav/apple-icon.png">
		<link rel="apple-touch-icon" sizes="57x57" href="' . get_theme_url($echo = false) . '/fav/apple-icon-57x57.png">
		<link rel="apple-touch-icon" sizes="60x60" href="' . get_theme_url($echo = false) . '/fav/apple-icon-60x60.png">
		<link rel="apple-touch-icon" sizes="72x72" href="' . get_theme_url($echo = false) . '/fav/apple-icon-72x72.png">
		<link rel="apple-touch-icon" sizes="76x76" href="' . get_theme_url($echo = false) . '/fav/apple-icon-76x76.png">
		<link rel="apple-touch-icon" sizes="114x114" href="' . get_theme_url($echo = false) . '/fav/apple-icon-114x114.png">
		<link rel="apple-touch-icon" sizes="120x120" href="' . get_theme_url($echo = false) . '/fav/apple-icon-120x120.png">
		<link rel="apple-touch-icon" sizes="144x144" href="' . get_theme_url($echo = false) . '/fav/apple-icon-144x144.png">
		<link rel="apple-touch-icon" sizes="152x152" href="' . get_theme_url($echo = false) . '/fav/apple-icon-152x152.png">
		<link rel="apple-touch-icon" sizes="180x180" href="' . get_theme_url($echo = false) . '/fav/apple-icon-180x180.png">
		<link rel="apple-touch-icon" sizes="192x192" href="' . get_theme_url($echo = false) . '/fav/apple-icon-precomposed.png">
		
		<link rel="shortcut icon" type="image/x-icon" href="' . get_theme_url($echo = false) . '/fav/favicon.ico" />
		<link rel="icon" type="image/png" sizes="16x16" href="' . get_theme_url($echo = false) . '/fav/favicon-16x16.png">
		<link rel="icon" type="image/png" sizes="32x32" href="' . get_theme_url($echo = false) . '/fav/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="' . get_theme_url($echo = false) . '/fav/favicon-96x96.png">
		
		<meta name="msapplication-TileImage" content="' . get_theme_url($echo = false) . '/fav/ms-icon-310x310.png">
		<meta name="msapplication-config" content="' . get_theme_url($echo = false) . '/fav/browserconfig.xml" />
		<meta name="msapplication-TileColor" content="#ffffff">
		<link rel="manifest" href="' . get_theme_url($echo = false) . '/fav/manifest.json">
		<meta name="theme-color" content="#ffffff">
		
	';
	}

	if (file_exists($geofile) && file_get_contents($geofile) !== '') {
		$seo .= '	<!-- GeoLocation Meta Tags / Geotagging -->
' . (file_exists($geocodefile) ? file_get_contents($geocodefile) : '');
$seo .= '

	';
	}

	if (file_exists($jsonldfile) && file_get_contents($jsonldfile) !== '') {
		$seo .= '	<!-- JSON-LD Schema -->
<script type="application/ld+json">
' . (file_exists($jsonldcodefile) ? file_get_contents($jsonldcodefile) : '') . '
</script>

';
	}
	
	echo $seo;

	// script queue
	get_scripts_frontend();

	exec_action('theme-header');
}

function betterSEO()
{
	///file
	$folder = GSDATAOTHERPATH . 'betterSEO/';
	
	$fbimagefile = $folder . 'fbimage.txt';
	$imageseo = '';
	if (file_exists($fbimagefile) && file_get_contents($fbimagefile) !== '') {
		$imageseo = file_get_contents($fbimagefile);
	}
	
	$geofile = $folder . 'geocheck.txt';
	$geocodefile = $folder . 'geocode.txt';
	
	$facebookcheckfile = $folder . 'facebookcheck.txt';
	$fbcustomfile = $folder . 'fbcustom.txt';
	$multifieldfile = $folder . 'fbmultifield.txt';
	$fbimagefile = $folder . 'fbimage.txt';

	$dublinfile = $folder . 'dublin.txt';
	$dublincheckfile = $folder . 'dublincheck.txt';

	$twitterfile = $folder . 'twitter.txt';
	$twittercheckfile = $folder . 'twittercheck.txt';

	$applefile = $folder . 'apple.txt';
	$applecheckfile = $folder . 'applecheck.txt';
	
	$faviconfile = $folder . 'favicon.txt';
	
	$jsonldfile = $folder . 'jsonldcheck.txt';
	$jsonldcodefile = $folder . 'jsonldcode.txt';

	$homepagetitlefile = $folder . 'homepagetitle.txt';

	///
	global $SITEURL;
	global $USR;

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
			.ul-linker{
				list-style-type:none;
				margin:0 !important;
				padding:0;
			}
			.ul-linker li{
				padding:10px;border-bottom:solid 1px #ddd;
			}
			.ul-linker li:nth-child(2n){
				background:#fafafa;
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
			#jsonld-div input {color:blue}
		</style>

		<script>
		var imageseo = ' . json_encode($imageseo ?? "") . ';
		</script>
		
		<h3 style="font-weight:bold;font-style:italic;font-size:1.3rem;">Better Seo Plugin</h3>

		<div class="tab">
			<div class="tab-item tab-item-active"><p>Setup</p></div>
			<div class="tab-item"><p>Help</p></div>
		</div>

		<div class="tab-content-1">
			<form method="post" class="seoguy">
				
				<h3>Homepage Title</h3>

				<select name="homepagetitle" class="seoguy-select">
					<option value="normal">Normal</option>
					<option value="titlefirst">Website Name | Page Title</option>
					<option value="titleonly">Only Website Name</option>
				</select>
				
				<hr>

				<h3 style="margin-top:20px;"> Favicons</h3>
				<p class="leader"> Icons need to be included in a folder named "fav" within your themes dir. (Visit generator <a href="https://www.favicon-generator.org/" target="_blank">here</a>.)</p>

				<label >
					<input type="checkbox" name="favicon">
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<hr>
				
				<h3>Dublin Core</h3>
				<p class="leader">Metadata Element Set</p>
				
				<label >
					<input type="checkbox" name="dublincheck">
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<p>Language code (eg: en, es, de, pl, etc.)</p>
				<input type="text" style="width:100%;padding:10px;box-sizing:border-box;" name="dublin" placeholder="en" value="' . (file_exists($dublinfile) ? file_get_contents($dublinfile) : '') . '">

				<hr>

				<h3>GeoLocation</h3>

				<p class="leader">GeoLocation (Visit generator  <a target="_blank" href="https://www.geo-tag.de/generator/en.html">here</a>.)</p>

				<label >
					<input type="checkbox" name="geocheck">

					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<textarea name="geocode" style="height:150px; color:blue;">' . (file_exists($geocodefile) ? file_get_contents($geocodefile) : '') . '</textarea>
				
				<hr>
				
				<h3 style="margin-top:20px;">Facebook</h3>
				<p class="leader">og:image (for FB, Twitter, etc.)</p>
			
				<label >
					<input type="checkbox" name="facebookcheck">
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<p>custom_field name (requires I18N Custom Fields plugin): </p>
				<input type="text" name="fbcustom" value="' . (file_exists($fbcustomfile) ? file_get_contents($fbcustomfile) : '') . '" style="width:100%; padding:10px; box-sizing:border-box; color:blue;" placeholder="my-customField">
				
				<br>

				<p>multiField name (requires MultiField plugin): </p>
				<input type="text" name="multifieldcustom" value="' . (file_exists($multifieldfile) ? file_get_contents($multifieldfile) : '') . '" style="width:100%; padding:10px; box-sizing:border-box; color:blue;" placeholder="my-multiField">
			 
				
				<p> or Static image:</p>
				<input type="text" style="width:100%; padding:10px; box-sizing:border-box; color:blue" name="fbimage" value="' . (file_exists($fbimagefile) ? file_get_contents($fbimagefile) : '') . '" placeholder="Image URL">
				<button style="background: orangered; color: #fff; border: none; padding: 10px 15px; cursor: pointer; border-radius: 7px; width: 20%; margin-top: 20px;" onclick="event.preventDefault();window.open(`' . $SITEURL . 'plugins/BetterSeo/files/imagebrowser.php?&func=multifield[]&count=0`,`myWindow`,`tolbar=no,scrollbars=no,menubar=no,width=500,height=500`)">Select Photo</button>

				<hr>
				
				<h3>Twitter/X Card</h3>
				<p class="leader">When being shared on Twitter/X, these tags help you control the preview.</p>
				
				<label >
					<input type="checkbox" name="twittercheck">
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<hr>
				
				<h3>Apple Web App Tags</h3>
				<p class="leader">Enhance the iOS/PWA mobile experience.</p>
				
				<label >
					<input type="checkbox" name="applecheck">
					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<hr>

				<h3>JSON-LD Schema </h3>
				<p class="leader">Schema markup also known as "structured data".</p>

				<label >
					<input type="checkbox" name="jsonldcheck">

					<div class="checkbox">
						<span class="checkbox-circle"></span>
					</div>
				</label>

				<div class="row" id="jsonld-div" style="display:flex;margin-top:20px;">
					<div class="col5" style="width:50%;padding-right:20px;">
						<select name="jsontype" id="jsontype" class="seoguy-select">
							<option value="Local Business">Local Business</option>
							<option value="Organization">Organization</option>
							<option value="Person">Person</option>
						</select>

						<div id="localBusiness" style="display:none;">
							<p style="margin-bottom:0px;">Type:</p>
							<select id="bType" class="seoguy-select">
								<option value="">- Select Business Type -</option>
								<option>Animal Shelter</option>
								<optgroup label="Automotive" id="automotive">
									<option>Automotive Business</option>
									<option>Auto Body Shop</option>
									<option>Auto Dealer</option>
									<option>Auto Parts Store</option>
									<option>Auto Rental</option>
									<option>Auto Repair</option>
									<option>Auto Wash</option>
									<option>Gas Station</option>
									<option>Motorcycle Dealer</option>
									<option>Motorcycle Repair</option>
								</optgroup>
								<option>Child Care</option>
								<option>Dry Cleaning Or Laundry</option>
								<optgroup label="Emergency">
									<option>Emergency Service</option>
									<option>Fire Station</option>
									<option>Hospital</option>
									<option>Police Station</option>
								</optgroup>
								<option>Employment Agency</option>
								<optgroup label="Entertainment">
									<option>Entertainment Business</option>
									<option>Adult Entertainment</option>
									<option>Amusement Park</option>
									<option>Art Gallery</option>
									<option>Casino</option>
									<option>Comedy Club</option>
									<option>Movie Theater</option>
									<option>Night Club</option>
								</optgroup>
								<optgroup label="Financial">
									<option>Accounting Service</option>
									<option>Automated Teller</option>
									<option>Bank Or Credit Union</option>
									<option>Insurance Agency</option>
								</optgroup>
								<optgroup label="Food">
									<option>Food Establishment</option>
									<option>Bakery</option>
									<option>Bar Or Pub</option>
									<option>Brewery</option>
									<option>Cafe Or Coffee Shop</option>
									<option>Fast Food Restaurant</option>
									<option>Ice Cream Shop</option>
									<option>Restaurant</option>
									<option>Winery</option>
								</optgroup>
								<option>Government Office</option>
								<option>Post Office</option>
								<optgroup label="Health And Beauty">
									<option>Health And Beauty Business</option>
									<option>Beauty Salon</option>
									<option>Day Spa</option>
									<option>Hair Salon</option>
									<option>Health Club</option>
									<option>Nail Salon</option>
									<option>Tattoo Parlor</option>
								</optgroup>
								<optgroup label="Home And Construction">
									<option>Home And Construction Business</option>
									<option>Electrician</option>
									<option>General Contractor</option>
									<option>HVAC Business</option>
									<option>House Painter</option>
									<option>Locksmith</option>
									<option>Moving Company</option>
									<option>Plumber</option>
									<option>RoofingContractor</option>
								</optgroup>
								<option>Internet Cafe</option>
								<option>Library</option>
								<optgroup label="Lodging">
									<option>Lodging Business</option>
									<option>Bed And Breakfast</option>
									<option>Hostel</option>
									<option>Hotel</option>
									<option>Motel</option>
								</optgroup>
								<optgroup label="Medical/Dental">
									<option>Dentist</option>
									<option>Diagnostic Lab</option>
									<option>Hospital</option>
									<option>Medical Clinic</option>
									<option>Optician</option>
									<option>Pharmacy</option>
									<option>Physician</option>
									<option>Veterinary Care</option>
								</optgroup>
								<optgroup label="Professional Services">
									<option>Professional Service</option>
									<option>Accounting Service</option>
									<option>Attorney</option>
									<option>Dentist</option>
									<option>Electrician</option>
									<option>General Contractor</option>
									<option>House Painter</option>
									<option>Locksmith</option>
									<option>Notary</option>
									<option>Plumber</option>
									<option>Roofing Contractor</option>
								</optgroup>
								<option>Radio Station</option>
								<option>Real Estate Agent</option>
								<option>Recycling Center</option>
								<option>Self Storage</option>
								<option>Shopping Center</option>
								<optgroup label="Sports Activities">
									<option>Sports Activity Location</option>
									<option>Bowling Alley</option>
									<option>Exercise Gym</option>
									<option>Golf Course</option>
									<option>Health Club</option>
									<option>Public Swimming Pool</option>
									<option>Ski Resort</option>
									<option>Sports Club</option>
									<option>Stadium Or Arena</option>
									<option>Tennis Complex</option>
								</optgroup>
								<optgroup label="Stores">
									<option>Store</option>
									<option>Auto Parts Store</option>
									<option>Bike Store</option>
									<option>Book Store</option>
									<option>Clothing Store</option>
									<option>Computer Store</option>
									<option>Convenience Store</option>
									<option>Department Store</option>
									<option>Electronics Store</option>
									<option>Florist</option>
									<option>Furniture Store</option>
									<option>Garden Store</option>
									<option>Grocery Store</option>
									<option>Hardware Store</option>
									<option>Hobby Shop</option>
									<option>Home Goods Store</option>
									<option>Jewelry Store</option>
									<option>Liquor Store</option>
									<option>Mens Clothing Store</option>
									<option>Mobile Phone Store</option>
									<option>Movie Rental Store</option>
									<option>Music Store</option>
									<option>Office Equipment Store</option>
									<option>Outlet Store</option>
									<option>Pawn Shop</option>
									<option>Pet Store</option>
									<option>Shoe Store</option>
									<option>Sporting Goods Store</option>
									<option>Tire Shop</option>
									<option>Toy Store</option>
									<option>Wholesale Store</option>
								</optgroup>
								<option>Television Station</option>
								<option>Tourist Information Center</option>
								<option>Travel Agency</option>
							</select>
							
							<p>Business Name:</p>
							<input type="text" id="name" class="seoguy-input">
								
							<p>Telephone:</p>
							<input type="text" id="telephone" class="seoguy-input" placeholder="+1 (000) 000-0000">
							
							<p>Description:</p>
							<textarea rows="3" style="height:150px;" id="description" class="seoguy-input"></textarea>
							
							<p>Address:</p>
							<input type="text" id="streetAddress" class="seoguy-input">
							
							<div id="poBoxDiv" style="display: none;">
								<p>PO Box:</p>
								<input type="text" id="postOfficeBoxNumber" class="seoguy-input">
							</div>
							
							<p>City:</p>
							<input type="text" id="addressLocality" class="seoguy-input">
							
							<p>State/Region:</p>
							<input type="text" id="addressRegion" class="seoguy-input">
							
							<p>Zip/Postal Code:</p>
							<input type="text" id="postalCode" class="seoguy-input">
							
							<p>Country:</p>
							<input type="text" id="addressCountry" class="seoguy-input">
							
							<p>Latitude:</p>
							<input type="text" id="latitude" class="seoguy-input">
							
							<p>Longitude:</p>
							<input type="text" id="longitude" class="seoguy-input">
							
							<p>Google Map URL:</p>
							<input type="text" id="hasMap" class="seoguy-input">
							
							<p>Logo:</p>
							<input type="text" id="logo" class="seoguy-input">
							
							<p>Business Hours:</p>
							' . generateBusinessHoursFields() . '
							
							<p style="margin-bottom:0px;">Price range:</p>
							<select id="priceRange" class="seoguy-select">
								<option value="" selected="selected">- Choose -</option>
								<option value="$">$ = Inexpensive, usually $10 and under</option>
								<option value="$$">$$ = Moderately expensive, usually between $10-$25</option>
								<option value="$$$">$$$ = Expensive, usually between $25-$45</option>
								<option value="$$$$">$$$$ = Very Expensive, usually $50 and up</option>
							</select>
						</div>

						<div id="Organization" style="display:none;">
							<p style="margin-bottom:0px;">Type:</p>
							<select id="oType" class="seoguy-select">
								<option value="">- Select -</option>
								<option value="Organization">Organization</option>
								<option value="Corporation">Corporation</option>
								<option value="Educational Organization">Educational Organization</option>
								<option value="Government Organization">Government Organization</option>
								<option value="Local Business">Local Business</option>
								<option value="NGO">NGO</option>
								<option value="Performing Group">Performing Group</option>
								<option value="SportsTeam">SportsTeam</option>
							</select>
							
							<p>Organization Name:</p>
							<input type="text" id="orgName" class="seoguy-input">
								
							<p>Telephone:</p>
							<input type="text" id="orgTelephone" class="seoguy-input" placeholder="+1 (000) 000-0000">
							
							<p>Description:</p>
							<textarea rows="3" style="height:150px;" id="orgDescription" class="seoguy-input"></textarea>
							
							<p>Address:</p>
							<input type="text" id="orgStreetAddress" class="seoguy-input">
							
							<p>City:</p>
							<input type="text" id="orgAddressLocality" class="seoguy-input">
							
							<p>State/Region:</p>
							<input type="text" id="orgAddressRegion" class="seoguy-input">
							
							<p>Zip/Postal Code:</p>
							<input type="text" id="orgPostalCode" class="seoguy-input">
							
							<p>Country:</p>
							<input type="text" id="orgAddressCountry" class="seoguy-input">
							
							<p>Google Map URL:</p>
							<input type="text" id="orgHasMap" class="seoguy-input">
							
							<p>Logo:</p>
							<input type="text" id="orglogo" class="seoguy-input">
						</div>

						<div id="person" style="display:none;">
							<p>Name:</p>
							<input type="text" id="nameP" class="seoguy-input">
							
							<p>Job Title:</p>
							<input type="text" id="jobTitle" class="seoguy-input">
							
							<p>Height:</p>
							<input type="text" id="height" class="seoguy-input" placeholder="182 cm">
							
							<p>Gender:</p>
							<select id="gender" class="seoguy-select">
								<option value="">- Choose -</option>
								<option value="male">male</option>
								<option value="female">female</option>
							</select>
							
							<p>Telephone:</p>
							<input type="text" id="personTelephone" class="seoguy-input" placeholder="+1 (000) 000-0000">
							
							<p>Address:</p>
							<input type="text" id="streetAddressP" class="seoguy-input">
							
							<p>City:</p>
							<input type="text" id="addressLocalityP" class="seoguy-input">
							
							<p>State/Region:</p>
							<input type="text" id="addressRegionP" class="seoguy-input">
							
							<p>Zip/Postal Code:</p>
							<input type="text" id="postalCodeP" class="seoguy-input">
							
							<p>Country:</p>
							<input type="text" id="addressCountryP" class="seoguy-input">
							
							<p>Birth Date (YYYY-MM-DD):</p>
							<input type="text" id="birthDate" class="seoguy-input" placeholder="YYYY-MM-DD" maxlength="10">
							
							<p>Alumni Of:</p>
							<input type="text" id="alumniOf" class="seoguy-input">
							
							<p>Nationality:</p>
							<input type="text" id="nationality" class="seoguy-input" placeholder="American">
						</div>
					</div>
					
					<div class="col7" style="width:50%;">
						<p>Generated JSON-LD Code:</p>
						<textarea name="jsonldcode" id="jsonldcode" class="seoguy-input" style="height:800px;color:hotpink" readonly>' . (file_exists($jsonldcodefile) ? file_get_contents($jsonldcodefile) : '') . '</textarea>
					</div>
				</div>

				<script>
				// Toggle JSON-LD block 
				document.addEventListener("DOMContentLoaded", function() {
					const checkbox = document.querySelector(\'input[name="jsonldcheck"]\');
					const jsonldDiv = document.getElementById("jsonld-div");

					function toggleJsonldDiv() {
						if (checkbox.checked) {
							jsonldDiv.style.display = "flex";
						} else {
							jsonldDiv.style.display = "none";
						}
					}

					toggleJsonldDiv();
					checkbox.addEventListener("change", toggleJsonldDiv);
				});
				</script>

				<script>
				// Handle type selection
				document.getElementById("jsontype").addEventListener("change", function() {
					// Hide all forms
					document.getElementById("localBusiness").style.display = "none";
					document.getElementById("Organization").style.display = "none";
					document.getElementById("person").style.display = "none";
					
					// Show selected form
					if(this.value === "Local Business") {
						document.getElementById("localBusiness").style.display = "block";
					} else if(this.value === "Organization") {
						document.getElementById("Organization").style.display = "block";
					} else if(this.value === "Person") {
						document.getElementById("person").style.display = "block";
					}
					
					generateJsonLd();
				});

				// Add event listeners to all form fields
				const inputs = document.querySelectorAll(".col5 input, .col5 select, .col5 textarea");
				inputs.forEach(input => {
					input.addEventListener("change", generateJsonLd);
					input.addEventListener("keyup", generateJsonLd);
				});

				// Function to generate JSON-LD
				function generateJsonLd() {
					const type = document.getElementById("jsontype").value;
					let jsonData = {};
					
					if(type === "Local Business") {
						jsonData["@context"] = "https://schema.org";
						// Use selected business type or default to LocalBusiness
						const selectedType = document.getElementById("bType").value;
						jsonData["@type"] = selectedType || "LocalBusiness";
						
						// Add all the fields
						if(document.getElementById("name").value) {
							jsonData["name"] = document.getElementById("name").value;
						}
						
						if(document.getElementById("telephone").value) {
							jsonData["telephone"] = document.getElementById("telephone").value;
						}
						
						if(document.getElementById("description").value) {
							jsonData["description"] = document.getElementById("description").value;
						}
						
						if(imageseo && imageseo.trim() !== "") {
							jsonData["image"] = imageseo;
						}
							
						// Address
						let address = {};
						if(document.getElementById("streetAddress").value) {
							address["streetAddress"] = document.getElementById("streetAddress").value;
						}
						if(document.getElementById("addressLocality").value) {
							address["addressLocality"] = document.getElementById("addressLocality").value;
						}
						if(document.getElementById("addressRegion").value) {
							address["addressRegion"] = document.getElementById("addressRegion").value;
						}
						if(document.getElementById("postalCode").value) {
							address["postalCode"] = document.getElementById("postalCode").value;
						}
						if(document.getElementById("addressCountry").value) {
							address["addressCountry"] = document.getElementById("addressCountry").value;
						}
						
						if(Object.keys(address).length > 0) {
							jsonData["address"] = {
								"@type": "PostalAddress",
								...address
							};
						}
						
						// Geo coordinates
						if(document.getElementById("latitude").value && document.getElementById("longitude").value) {
							jsonData["geo"] = {
								"@type": "GeoCoordinates",
								"latitude": document.getElementById("latitude").value,
								"longitude": document.getElementById("longitude").value
							};
						}
						
						// Google Map URL
						if(document.getElementById("hasMap").value) {
							jsonData["hasMap"] = document.getElementById("hasMap").value;
						}
						
						// Business Logo
						if(document.getElementById("logo").value) {
							jsonData["logo"] = document.getElementById("logo").value;
						}
						
						// Opening hours
						const days = ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"];
						let openingHours = [];
						days.forEach(day => {
							const checkbox = document.querySelector(`input[name="day"][value="${day}"]`);
							if(checkbox && checkbox.checked) {
								const openTime = document.querySelector(`select[name="${day}_open"]`).value;
								const closeTime = document.querySelector(`select[name="${day}_close"]`).value;
								if(openTime && closeTime) {
									openingHours.push(`${day} ${openTime}-${closeTime}`);
								}
							}
						});
						
						if(openingHours.length > 0) {
							jsonData["openingHoursSpecification"] = openingHours.map(time => {
								const parts = time.split(" ");
								return {
									"@type": "OpeningHoursSpecification",
									"dayOfWeek": parts[0],
									"opens": parts[1].split("-")[0],
									"closes": parts[1].split("-")[1]
								};
							});
						}
						
						// Price range
						if(document.getElementById("priceRange").value) {
							jsonData["priceRange"] = document.getElementById("priceRange").value;
						}
						
					} else if(type === "Organization") {
						// Similar structure for Organization
						jsonData["@context"] = "https://schema.org";
						// Use selected organization type or default to Organization
						const selectedType = document.getElementById("oType").value;
						jsonData["@type"] = selectedType || "Organization";
						
						if(document.getElementById("orgName").value) {
							jsonData["name"] = document.getElementById("orgName").value;
						}
						
						if(document.getElementById("orgTelephone").value) {
							jsonData["telephone"] = document.getElementById("orgTelephone").value;
						}
						
						if(document.getElementById("orgDescription").value) {
							jsonData["description"] = document.getElementById("orgDescription").value;
						}
						
						if(imageseo && imageseo.trim() !== "") {
							jsonData["image"] = imageseo;
						}
						
						// Address
						let address = {};
						if(document.getElementById("orgStreetAddress").value) {
							address["streetAddress"] = document.getElementById("orgStreetAddress").value;
						}
						if(document.getElementById("orgAddressLocality").value) {
							address["addressLocality"] = document.getElementById("orgAddressLocality").value;
						}
						if(document.getElementById("orgAddressRegion").value) {
							address["addressRegion"] = document.getElementById("orgAddressRegion").value;
						}
						if(document.getElementById("orgPostalCode").value) {
							address["postalCode"] = document.getElementById("orgPostalCode").value;
						}
						if(document.getElementById("orgAddressCountry").value) {
							address["addressCountry"] = document.getElementById("orgAddressCountry").value;
						}
						
						if(Object.keys(address).length > 0) {
							jsonData["address"] = {
								"@type": "PostalAddress",
								...address
							};
						}
						
						if(document.getElementById("orgHasMap").value) {
							jsonData["hasMap"] = document.getElementById("orgHasMap").value;
						}
						
						if(document.getElementById("orglogo").value) {
							jsonData["logo"] = document.getElementById("orglogo").value;
						}
						
					} else if(type === "Person") {
						// Similar structure for Person
						jsonData["@context"] = "https://schema.org";
						jsonData["@type"] = "Person";
						
						if(imageseo && imageseo.trim() !== "") {
							jsonData["image"] = imageseo;
						}
						
						if(document.getElementById("nameP").value) {
							jsonData["name"] = document.getElementById("nameP").value;
						}
						
						if(document.getElementById("jobTitle").value) {
							jsonData["jobTitle"] = document.getElementById("jobTitle").value;
						}
						
						if(document.getElementById("height").value) {
							jsonData["height"] = document.getElementById("height").value;
						}
						
						if(document.getElementById("gender").value) {
							jsonData["gender"] = document.getElementById("gender").value;
						}
						
						if(document.getElementById("personTelephone").value) {
							jsonData["telephone"] = document.getElementById("personTelephone").value;
						}
						
						if(document.getElementById("birthDate").value) {
							jsonData["birthDate"] = document.getElementById("birthDate").value;
						}
						
						if(document.getElementById("alumniOf").value) {
							jsonData["alumniOf"] = document.getElementById("alumniOf").value;
						}
						
						if(document.getElementById("nationality").value) {
							jsonData["nationality"] = document.getElementById("nationality").value;
						}
						
						// Address
						let address = {};
						if(document.getElementById("streetAddressP").value) {
							address["streetAddress"] = document.getElementById("streetAddressP").value;
						}
						if(document.getElementById("addressLocalityP").value) {
							address["addressLocality"] = document.getElementById("addressLocalityP").value;
						}
						if(document.getElementById("addressRegionP").value) {
							address["addressRegion"] = document.getElementById("addressRegionP").value;
						}
						if(document.getElementById("postalCodeP").value) {
							address["postalCode"] = document.getElementById("postalCodeP").value;
						}
						if(document.getElementById("addressCountryP").value) {
							address["addressCountry"] = document.getElementById("addressCountryP").value;
						}
						
						if(Object.keys(address).length > 0) {
							jsonData["address"] = {
								"@type": "PostalAddress",
								...address
							};
						}
					}
					
					// Update the textarea with pretty-printed JSON
					document.getElementById("jsonldcode").value = JSON.stringify(jsonData, null, 2);
				}
				
				// Load saved data if exists
				if(document.getElementById("jsonldcode").value) {
					try {
						const savedData = JSON.parse(document.getElementById("jsonldcode").value);
						
						// Determine type from saved data
						if(savedData["@type"]) {
							if(savedData["@type"] && (savedData["@type"].includes("Business") || 
								savedData["@type"] === "Store" || 
								document.getElementById("bType").innerHTML.includes(savedData["@type"]))) {
								document.getElementById("jsontype").value = "Local Business";
								document.getElementById("localBusiness").style.display = "block";
								
								// Populate local business fields
								if(savedData["name"]) document.getElementById("name").value = savedData["name"];
								if(savedData["telephone"]) document.getElementById("telephone").value = savedData["telephone"];
								if(savedData["description"]) document.getElementById("description").value = savedData["description"];
								if(savedData["hasMap"]) document.getElementById("hasMap").value = savedData["hasMap"];
								if(savedData["logo"]) document.getElementById("logo").value = savedData["logo"];
								
								// Set business type
								if(savedData["@type"] && document.getElementById("bType")) {
									const bTypeSelect = document.getElementById("bType");
									for(let i = 0; i < bTypeSelect.options.length; i++) {
										if(bTypeSelect.options[i].text === savedData["@type"]) {
											bTypeSelect.selectedIndex = i;
											break;
										}
									}
								}
								
								// Address
								if(savedData["address"]) {
									const addr = savedData["address"];
									if(addr["streetAddress"]) document.getElementById("streetAddress").value = addr["streetAddress"];
									if(addr["addressLocality"]) document.getElementById("addressLocality").value = addr["addressLocality"];
									if(addr["addressRegion"]) document.getElementById("addressRegion").value = addr["addressRegion"];
									if(addr["postalCode"]) document.getElementById("postalCode").value = addr["postalCode"];
									if(addr["addressCountry"]) document.getElementById("addressCountry").value = addr["addressCountry"];
								}
								
								// Geo
								if(savedData["geo"]) {
									document.getElementById("latitude").value = savedData["geo"]["latitude"] || "";
									document.getElementById("longitude").value = savedData["geo"]["longitude"] || "";
								}
								
								// Opening hours
								if(savedData["openingHoursSpecification"]) {
									savedData["openingHoursSpecification"].forEach(hours => {
										const day = hours["dayOfWeek"];
										const checkbox = document.querySelector(`input[name="day"][value="${day}"]`);
										if(checkbox) {
											checkbox.checked = true;
											toggleTimeContainer(checkbox);
											
											const openTime = hours["opens"];
											const closeTime = hours["closes"];
											
											const openSelect = document.querySelector(`select[name="${day}_open"]`);
											const closeSelect = document.querySelector(`select[name="${day}_close"]`);
											
											if(openSelect && openTime) openSelect.value = openTime;
											if(closeSelect && closeTime) closeSelect.value = closeTime;
										}
									});
								}
								
								// Price range
								if(savedData["priceRange"]) document.getElementById("priceRange").value = savedData["priceRange"];
								
							} else if(savedData["@type"] && (savedData["@type"].includes("Organization") || 
								document.getElementById("oType").innerHTML.includes(savedData["@type"]))) {
								document.getElementById("jsontype").value = "Organization";
								document.getElementById("Organization").style.display = "block";
								
								// Populate organization fields
								if(savedData["name"]) document.getElementById("orgName").value = savedData["name"];
								if(savedData["telephone"]) document.getElementById("orgTelephone").value = savedData["telephone"];
								if(savedData["description"]) document.getElementById("orgDescription").value = savedData["description"];
								if(savedData["hasMap"]) document.getElementById("orgHasMap").value = savedData["hasMap"];
								if(savedData["logo"]) document.getElementById("orglogo").value = savedData["logo"];
								
								// Set organization type
								if(savedData["@type"] && document.getElementById("oType")) {
									const oTypeSelect = document.getElementById("oType");
									for(let i = 0; i < oTypeSelect.options.length; i++) {
										if(oTypeSelect.options[i].value === savedData["@type"]) {
											oTypeSelect.selectedIndex = i;
											break;
										}
									}
								}
								
								// Address
								if(savedData["address"]) {
									const addr = savedData["address"];
									if(addr["streetAddress"]) document.getElementById("orgStreetAddress").value = addr["streetAddress"];
									if(addr["addressLocality"]) document.getElementById("orgAddressLocality").value = addr["addressLocality"];
									if(addr["addressRegion"]) document.getElementById("orgAddressRegion").value = addr["addressRegion"];
									if(addr["postalCode"]) document.getElementById("orgPostalCode").value = addr["postalCode"];
									if(addr["addressCountry"]) document.getElementById("orgAddressCountry").value = addr["addressCountry"];
								}
								
							} else if(savedData["@type"] === "Person") {
								document.getElementById("jsontype").value = "Person";
								document.getElementById("person").style.display = "block";
								
								// Populate person fields
								if(savedData["name"]) document.getElementById("nameP").value = savedData["name"];
								if(savedData["jobTitle"]) document.getElementById("jobTitle").value = savedData["jobTitle"];
								if(savedData["height"]) document.getElementById("height").value = savedData["height"];
								if(savedData["gender"]) document.getElementById("gender").value = savedData["gender"];
								if(savedData["telephone"]) document.getElementById("personTelephone").value = savedData["telephone"];
								if(savedData["birthDate"]) document.getElementById("birthDate").value = savedData["birthDate"];
								if(savedData["alumniOf"]) document.getElementById("alumniOf").value = savedData["alumniOf"];
								if(savedData["nationality"]) document.getElementById("nationality").value = savedData["nationality"];
								
								// Address
								if(savedData["address"]) {
									const addr = savedData["address"];
									if(addr["streetAddress"]) document.getElementById("streetAddressP").value = addr["streetAddress"];
									if(addr["addressLocality"]) document.getElementById("addressLocalityP").value = addr["addressLocality"];
									if(addr["addressRegion"]) document.getElementById("addressRegionP").value = addr["addressRegion"];
									if(addr["postalCode"]) document.getElementById("postalCodeP").value = addr["postalCode"];
									if(addr["addressCountry"]) document.getElementById("addressCountryP").value = addr["addressCountry"];
								}
							}
						}
					} catch(e) {
						console.error("Error parsing saved JSON-LD:", e);
					}
				}

				// Function to toggle time container visibility
				function toggleTimeContainer(checkbox) {
					const day = checkbox.value;
					const container = document.getElementById(`time-container-${day}`);
					if(checkbox.checked) {
						container.style.display = "block";
					} else {
						container.style.display = "none";
					}
				}

				// Add event listeners to day checkboxes
				document.querySelectorAll("input[name=\"day\"]").forEach(checkbox => {
					checkbox.addEventListener("change", function() {
						toggleTimeContainer(this);
						generateJsonLd();
					});
				});

				// Add event listeners to time selectors
				document.querySelectorAll("select[name$=\"_open\"], select[name$=\"_close\"]").forEach(select => {
					select.addEventListener("change", generateJsonLd);
				});

				// Trigger change event to show correct form
				document.getElementById("jsontype").dispatchEvent(new Event("change"));
				</script>

				<hr>

				<input type="submit" name="submit" class="submit" value="Save Settings">
			</form>
		</div>

		<div class="tab-content-2">
			<h3>How to use it?</h3>

			<p class="leader">To install, include (if your template uses <span style="color:blue;">get_header()</span> replace it with this function) </p>

			<code class="seocode"> &#60;?php  get_seoheader();?&#62; </code>

			<p style="margin:10px 0;" class="leader">before</p>
			<code class="seocode"> &#60;/head&#62; </code>

			<br><br>

			<h3>More Info:</h4>

			<ul class="leader ul-linker">	
				<li>GeoLocation Meta Tags / Geotagging. Used for custom results in Google. Generator <a href="https://www.geo-tag.de/generator/en.html" target="_blank">here.</a></li>
				<li>Open Graph protocol, more info <a href="https://ogp.me/" target="_blank">here.</a></li>
				<li>Dublin Core Metadata, more info <a href="http://purl.org/dc/elements/1.1/" target="_blank">here.</a></li>
				<li>Favicons. Generator <a href="https://www.favicon-generator.org/" target="_blank">here.</a></li>
				<li>JSON-LD, more info <a href="https://json-ld.org/" target="_blank">here.</a></li>
				<li>Twitter/X Cards, more info <a href="https://developer.x.com/en/docs/x-for-websites/cards/overview/abou" target="_blank">here.</a></li>
				<li>Apple Web App Tags, more info <a href="https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariHTMLRef/Articles/MetaTags.html" target="_blank">here.</a></li>
			</ul>
		</div>

		<script>
			document.querySelector(".tab-content-2").style.display="none";

			document.querySelectorAll(".tab-item")[0].addEventListener("click",(e)=>{
				e.preventDefault();
				document.querySelectorAll(".tab-item").forEach(x=>{x.classList.remove("tab-item-active")});
				document.querySelectorAll(".tab-item")[0].classList.add("tab-item-active");
				document.querySelector(".tab-content-1").style.display="block";
				document.querySelector(".tab-content-2").style.display="none";
			});

			document.querySelectorAll(".tab-item")[1].addEventListener("click",(e)=>{
				e.preventDefault();
				document.querySelectorAll(".tab-item").forEach(x=>{x.classList.remove("tab-item-active")});
				document.querySelectorAll(".tab-item")[1].classList.add("tab-item-active");
				document.querySelector(".tab-content-1").style.display="none";
				document.querySelector(".tab-content-2").style.display="block";
			});

			if("' . (file_exists($homepagetitlefile) ? file_get_contents($homepagetitlefile) : '') . '"!==""){
				document.querySelector(".seoguy-select").value = "' . (file_exists($homepagetitlefile) ? file_get_contents($homepagetitlefile) : '') . '"
			}

			if("' . (file_exists($geofile) ? file_get_contents($geofile) : '') . '"=="on"){
				document.querySelector(`input[name="geocheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="geocheck"]`).checked = false;
			}

			if("' . (file_exists($facebookcheckfile) ? file_get_contents($facebookcheckfile) : '') . '"=="on"){
				document.querySelector(`input[name="facebookcheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="facebookcheck"]`).checked = false;
			}

			if("' . (file_exists($dublincheckfile) ? file_get_contents($dublincheckfile) : '') . '"=="on"){
				document.querySelector(`input[name="dublincheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="dublincheck"]`).checked = false;
			}

			if("' . (file_exists($twittercheckfile) ? file_get_contents($twittercheckfile) : '') . '"=="on"){
				document.querySelector(`input[name="twittercheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="twittercheck"]`).checked = false;
			}

			if("' . (file_exists($applecheckfile) ? file_get_contents($applecheckfile) : '') . '"=="on"){
				document.querySelector(`input[name="applecheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="applecheck"]`).checked = false;
			}

			if("' . (file_exists($faviconfile) ? file_get_contents($faviconfile) : '') . '"=="on"){
				document.querySelector(`input[name="favicon"]`).checked = true;
			}else{
				document.querySelector(`input[name="favicon"]`).checked = false;
			}

			if("' . (file_exists($jsonldfile) ? file_get_contents($jsonldfile) : '') . '"=="on"){
				document.querySelector(`input[name="jsonldcheck"]`).checked = true;
			}else{
				document.querySelector(`input[name="jsonldcheck"]`).checked = false;
			}
		</script>
		
		<div id="paypal" class="" style="padding-top:30px;">
			<p>Made with <span class="credit-icon">❤️</span> especially for "<b>'.$USR.'</b>". Is this plugin useful to you?
			 <a href="https://getsimple-ce.ovh/donate" target="_blank" class="donateButton">Buy Us A Coffee <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-opacity="0" d="M17 14v4c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-4Z"><animate fill="freeze" attributeName="fill-opacity" begin="0.8s" dur="0.5s" values="0;1"/></path><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke-dasharray="48" stroke-dashoffset="48" d="M17 9v9c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-9Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="48;0"/></path><path stroke-dasharray="14" stroke-dashoffset="14" d="M17 9h3c0.55 0 1 0.45 1 1v3c0 0.55 -0.45 1 -1 1h-3"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.6s" dur="0.2s" values="14;0"/></path><mask id="lineMdCoffeeHalfEmptyFilledLoop0"><path stroke="#fff" d="M8 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M12 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M16 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4"><animateMotion calcMode="linear" dur="3s" path="M0 0v-8" repeatCount="indefinite"/></path></mask><rect width="24" height="0" y="7" fill="currentColor" mask="url(#lineMdCoffeeHalfEmptyFilledLoop0)"><animate fill="freeze" attributeName="y" begin="0.8s" dur="0.6s" values="7;2"/><animate fill="freeze" attributeName="height" begin="0.8s" dur="0.6s" values="0;5"/></rect></g></svg></a></p>
		</div>
		';

	echo $html;

	if (isset($_POST['submit'])) {
		$geocheck = isset($_POST['geocheck']) ? 'on' : '';
		$geocode = isset($_POST['geocode']) ? $_POST['geocode'] : '';
		
		$facebookcheck = isset($_POST['facebookcheck']) ? 'on' : '';
		$fbcustom = isset($_POST['fbcustom']) ? $_POST['fbcustom'] : '';
		$fbimage = isset($_POST['fbimage']) ? $_POST['fbimage'] : '';
		$multifieldcustom = isset($_POST['multifieldcustom']) ? $_POST['multifieldcustom'] : '';
		
		$dublincheck = isset($_POST['dublincheck']) ? 'on' : '';
		$dublin = isset($_POST['dublin']) ? $_POST['dublin'] : '';
		
		$twittercheck = isset($_POST['twittercheck']) ? 'on' : '';
		$twitter = isset($_POST['twitter']) ? $_POST['twitter'] : '';
		
		$applecheck = isset($_POST['applecheck']) ? 'on' : '';
		$apple = isset($_POST['apple']) ? $_POST['apple'] : '';
		
		$faviconcheck = isset($_POST['favicon']) ? 'on' : '';
		
		$jsonldcheck = isset($_POST['jsonldcheck']) ? 'on' : '';
		$jsonldcode = isset($_POST['jsonldcode']) ? $_POST['jsonldcode'] : '';
		
		$homepagetitle = isset($_POST['homepagetitle']) ? $_POST['homepagetitle'] : 'normal';

		// Set up the folder name and its permissions
		// Note the constant GSDATAOTHERPATH, which points to /path/to/getsimple/data/other/
		$folder = GSDATAOTHERPATH . 'betterSEO/';
		
		$geofile = $folder . 'geocheck.txt';
		$geocodefile = $folder . 'geocode.txt';
		
		$facebookcheckfile = $folder . 'facebookcheck.txt';
		$fbcustomfile = $folder . 'fbcustom.txt';
		$fbimagefile = $folder . 'fbimage.txt';
		$multifieldfile = $folder . 'fbmultifield.txt';

		$dublinfile = $folder . 'dublin.txt';
		$dublincheckfile = $folder . 'dublincheck.txt';

		$twitterfile = $folder . 'twitter.txt';
		$twittercheckfile = $folder . 'twittercheck.txt';

		$applefile = $folder . 'apple.txt';
		$applecheckfile = $folder . 'applecheck.txt';
		
		$faviconfile = $folder . 'favicon.txt';
		
		$jsonldfile = $folder . 'jsonldcheck.txt';
		$jsonldcodefile = $folder . 'jsonldcode.txt';

		$homepagetitlefile = $folder . 'homepagetitle.txt';

		$chmod_mode = 0755;
		$folder_exists = file_exists($folder) || mkdir($folder, $chmod_mode);

		// Save the file (assuming that the folder indeed exists)
		if ($folder_exists) {
			file_put_contents($geofile, $geocheck);
			file_put_contents($geocodefile, $geocode);
			
			file_put_contents($facebookcheckfile, $facebookcheck);
			file_put_contents($fbcustomfile, $fbcustom);
			file_put_contents($fbimagefile, $fbimage);
			file_put_contents($multifieldfile, $multifieldcustom);

			file_put_contents($dublinfile, $dublin);
			file_put_contents($dublincheckfile, $dublincheck);

			file_put_contents($twitterfile, $twitter);
			file_put_contents($twittercheckfile, $twittercheck);

			file_put_contents($applefile, $apple);
			file_put_contents($applecheckfile, $applecheck);
			
			file_put_contents($faviconfile, $faviconcheck);
			
			file_put_contents($jsonldfile, $jsonldcheck);
			file_put_contents($jsonldcodefile, $jsonldcode);

			file_put_contents($homepagetitlefile, $homepagetitle);
		}

		echo '<script>window.location.href = window.location.href;</script>';
	}
}

function generateBusinessHoursFields() {
	$days = [
		'Mo' => 'Monday',
		'Tu' => 'Tuesday',
		'We' => 'Wednesday',
		'Th' => 'Thursday',
		'Fr' => 'Friday',
		'Sa' => 'Saturday',
		'Su' => 'Sunday'
	];

	$timeOptions = '';
	for ($h = 0; $h < 24; $h++) {
		for ($m = 0; $m < 60; $m += 30) {
			$time = sprintf("%02d:%02d", $h, $m);
			$timeOptions .= '<option value="' . $time . '">' . $time . '</option>';
		}
	}

	$html = '';
	foreach ($days as $code => $day) {
		$html .= '
		<label style="display: block; margin-bottom: 10px;">
			<input type="checkbox" name="day" value="' . $code . '" class="dow">
			' . $day . '
			<div id="time-container-' . $code . '" class="time-container">
				<div class="time-row">
					<div class="time-col">
						<label>Open</label>
						<select name="' . $code . '_open" class="seoguy-select">
							' . $timeOptions . '
						</select>
					</div>
					<div class="time-col">
						<label>Close</label>
						<select name="' . $code . '_close" class="seoguy-select">
							' . $timeOptions . '
						</select>
					</div>
				</div>
			</div>
		</label>';
	}

	return $html;
}
?>