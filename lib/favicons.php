<?php
const FAVICONS_DIR = DATA_PATH . '/favicons/';
const DEFAULT_FAVICON = PUBLIC_PATH . '/themes/icons/default_favicon.ico';

function isImgMime(string $content): bool {
	//Based on https://github.com/ArthurHoaro/favicon/blob/3a4f93da9bb24915b21771eb7873a21bde26f5d1/src/Favicon/Favicon.php#L311-L319
	if ($content == '') {
		return false;
	}
	if (!extension_loaded('fileinfo')) {
		return true;
	}
	$isImage = true;
	try {
		/** @var finfo $fInfo */
		$fInfo = finfo_open(FILEINFO_MIME_TYPE);
		/** @var string $content */
		$content = finfo_buffer($fInfo, $content);
		$isImage = strpos($content, 'image') !== false;
		finfo_close($fInfo);
	} catch (Exception $e) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
	}
	return $isImage;
}

/** @param array<int,int|bool> $curlOptions */
function downloadHttp(string &$url, array $curlOptions = []): string {
	syslog(LOG_INFO, 'FreshRSS Favicon GET ' . $url);
	$url = checkUrl($url);
	if (!$url) {
		return '';
	}
	/** @var CurlHandle $ch */
	$ch = curl_init($url);
	curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_USERAGENT => FRESHRSS_USERAGENT,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING => '',	//Enable all encodings
		]);
	curl_setopt_array($ch, $curlOptions);
	/** @var string $response */
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
	if (!empty($info['url'])) {
		$url2 = checkUrl($info['url']);
		if ($url2 != '') {
			$url = $url2;	//Possible redirect
		}
	}
	return $info['http_code'] == 200 ? $response : '';
}

function searchFavicon(string &$url): string {
	$dom = new DOMDocument();
	$html = downloadHttp($url);
	if ($html != '' && @$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
		$rels = array('shortcut icon', 'icon');
		$links = $dom->getElementsByTagName('link');
		foreach ($rels as $rel) {
			foreach ($links as $link) {
				if ($link->hasAttribute('rel') && $link->hasAttribute('href') &&
					strtolower(trim($link->getAttribute('rel'))) === $rel) {
					$href = trim($link->getAttribute('href'));
					if (substr($href, 0, 2) === '//') {
						// Case of protocol-relative URLs
						if (preg_match('%^(https?:)//%i', $url, $matches)) {
							$href = $matches[1] . $href;
						} else {
							$href = 'https:' . $href;
						}
					}
					if (!checkUrl($href, false)) {
						$href = SimplePie_IRI::absolutize($url, $href);
					}
					$favicon = downloadHttp($href, array(
							CURLOPT_REFERER => $url,
						));
					if (isImgMime($favicon)) {
						return $favicon;
					}
				}
			}
		}
	}
	return '';
}

function download_favicon(string $url, string $dest): bool {
	$url = trim($url);
	$favicon = searchFavicon($url);
	if ($favicon == '') {
		$rootUrl = preg_replace('%^(https?://[^/]+).*$%i', '$1/', $url);
		if ($rootUrl != $url) {
			$url = $rootUrl;
			$favicon = searchFavicon($url);
		}
		if ($favicon == '') {
			$link = $rootUrl . 'favicon.ico';
			$favicon = downloadHttp($link, array(
					CURLOPT_REFERER => $url,
				));
			if (!isImgMime($favicon)) {
				$favicon = '';
			}
		}
	}
	return ($favicon != '' && file_put_contents($dest, $favicon)) ||
		@copy(DEFAULT_FAVICON, $dest);
}
