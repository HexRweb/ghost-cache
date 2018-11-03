<?php
// Requires the following extensions:
//  - xml
//  - curl
//  Tested using php-7.0-cli with php7.0-xml and php7.0-curl
// @todo: privatize utils

function get(String $url) {
	$instance = curl_init($url);
	$options = array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING => "",
		CURLOPT_AUTOREFERER	=> true,
		CURLOPT_CONNECTTIMEOUT => 120,
		CURLOPT_TIMEOUT => 120,
		CURLOPT_MAXREDIRS => 10
	);
	curl_setopt_array($instance, $options);
	$data = curl_exec($instance);
	curl_close($instance);

	return $data;
}

function mkdirIfNeeded(String $dir) {
	if (!is_dir($dir)) {
		mkdir($dir, false, true);
	}
}

function download (String $local, String $url) {
	echo "Downloading $url to $local";
	$fp = fopen($local, "w");
	fwrite($fp, get($url));
	fclose($fp);
	echo " ... done\n";
}

class GhostSitemap {
	const VERSION = "0.0.1";
	protected $urls = null;
	protected $sitemaps = [];

	protected function warn(String $message) { echo "GhostSitemap - $message.\n"; }
	protected function debug(String $message) { echo defined("DEBUG") ? "GhostSitemap - $message\n" : ''; }

	// Process all child sitemaps in $sitemapRef
	protected function process_sitemap_elements(SimpleXMLElement &$sitemapRef) {
		$children = [];
		foreach($sitemapRef->children() as $child) {
			if ($child->getName() === "sitemap") {
				$this->debug("Child sitemap found, recursively flattening");
				$children = array_merge($children, $this->process_sitemap($child->children()->loc));
			}
		}
		return $children;
	}

	// Add all urls in $sitemapRef to list of known URLs
	protected function process_url_elements(SimpleXMLElement &$sitemapRef) {
		$children = [];
		foreach($sitemapRef->children() as $child) {
			$childTagName = $child->getName();
			if ($childTagName === "url") {
				array_push($children, $child);
			} else {
				$this->warn("Don't know how to process urlset child tag `$childTagName`");
			}
		}
		return $children;
	}

	// Download and extract all urls listed in the sitemap located at $url
	protected function process_sitemap(String $url): array {
		$this->debug("Getting sitemap: $url");
		$sitemap = get($url);
		array_push($this->sitemaps, (String) $url));

		if (!$sitemap) {
			$this->warn("Failed to get sitemap $url");
			$sitemap ="<sitemapindex></sitemapindex>";
		}

		$sitemap = simplexml_load_string($sitemap);
		$rootName = $sitemap->getName();
		$children = [];

		if ($rootName == "sitemapindex") {
			$resp = $this->process_sitemap_elements($sitemap);
			$children = array_merge($children, $resp);
		} elseif ($rootName === "urlset") {
			$children = array_merge($children, $this->process_url_elements($sitemap));
		} else {
			throw new InvalidArgumentException("Unable to process sitemap - don't know how to process tag `$rootName`");
		}

		return $children;
	}

	// @todo: currently running into an issue with SiteMaps where the author page last updated
	// isn't changed when a post is published, which means all author + tag pages may need to
	// be regenerated on every change 🤔
	/*function filter_unmodified_urls(array $urls = NULL) {
		$urls = $urls ? $urls : $this->urls;
		$fileName = $this->currentDir."/.cachestate";
		if (!file_exists($fileName)) {
			return $urls;
		}

		$fp = fopen($fileName, "r");
		$contents = fread($fp, filesize($fileName));
		fclose($fp);

		$contents = (array) json_decode($contents);
		$i = 0;
		for ($i; $i < sizeof($urls); $i++) {
			$url = $urls[$i];

			$lastmod = (String) $url->lastmod;
			$loc = (String) $url->loc;

			$past = $contents[$loc] ? (array) $contents[$loc] : false;

			if ($past && $past['lastmod'] == $lastmod) {
				$urls[$i] = false;
				unset($contents[$loc]);
			}
		}

		foreach ($contents as $newUrl->$data) {
			array_push($urls, $data);
		}

		return array_filter($urls, function ($value) {
			return $value;
		});
	}

	function save_cache_state() {
		$urls = [];

		foreach ($this->urls as $url) {
			$loc = (String) $url->loc;
			$lastMod = (String) $url->lastmod;
			$child = array(
				"lastmod" => $lastMod,
				"loc" => $loc
			);
			$urls[$loc] = $child;
		}

		$fp = fopen($this->currentDir."/.cachestate", "w");
		if (!$fp) {
			return false;
		}

		fwrite($fp, json_encode($urls));
		fclose($fp);
		return true;
	}*/

	function __construct(String $url, String $downloadDir = '.', $currentDir = false, int $delayInMs = 575) {
		$this->root = preg_replace("/\/$/", "", $url); // No trailing slashes
		$this->currentDir = $currentDir ? preg_replace("/\/$/", "", $currentDir) : $currentDir; // No trailing slashes
		$this->delay = $delayInMs * 1000; // ms -> us
		$this->downloadDir = $downloadDir;

		$this->urls = $this->process_sitemap($this->root."/sitemap.xml");
	}

	function downloadUrls() {
		$base_dir = $this->downloadDir;
		$urls = $this->urls;
		/*if ($this->currentDir) {
			$urls = $this->filter_unmodified_urls($urls);
		}*/

		foreach ($urls as $url) {
			$url = $url->loc;
			$path = $base_dir.str_replace($this->root, "", $url);
			$file = $path."index.html";
			mkdirIfNeeded($path);
			download($file, $url);
			usleep($this->delay);
		}

		foreach ($this->sitemaps as $sitemap) {
			$filename = $base_dir.str_replace($this->root, "", $sitemap);
			download($filename, $sitemap);
		}

		if ($this->currentDir) {
			rename($this->currentDir, $this->currentDir."-stale");
			rename($base_dir, $this->currentDir);
		}
	}
}
/*$sitemap = new GhostSitemap($ROOT, './gh', './current');
$sitemap->downloadUrls();*/
?>