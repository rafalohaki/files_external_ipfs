<?php

namespace OCA\Files_External_IPFS\Storage;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

/**
 * Class Adapter
 * @package OCA\Files_External_IPFS\Storage
 * @author V31L <veil@mail.ch>
 */
class Adapter extends AbstractAdapter {
	private $host;

	public function __construct(string $host) {
		$this->host = $host;
	}

	/**
	 * @param string $method GET, POST, PUT
	 * @param string $url url of api to call
	 * @param array $params
	 * @param array $data
	 * @return bool|string
	 */
	private function callAPI(string $method, string $url, array $params = [], array $data = []) {
		$curl = curl_init();

		switch ($method) {
			case "POST":
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				break;
			case "PUT":
				curl_setopt($curl, CURLOPT_PUT, 1);
				break;
		}
		if (!empty($params)) $url = sprintf("%s?%s", $url, http_build_query($params));

		curl_setopt($curl, CURLOPT_URL, "{$this->host}{$url}");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($curl);

		curl_close($curl);

		return $result;
	}

	/**
	 * @param array $entry entry from IPFS api
	 * @param bool|string $root (optional) root if its for a directory
	 * @return array normalized file data
	 */
	private function normalizeFile(array $entry, $root = false) {
		return [
			'type' => $root ? ($entry['Type'] == 0 ? 'dir' : 'file') : ($entry['Type'] == 'file' ? 'file' : 'dir'),
			'path' => $root ? ltrim("{$root}/{$entry['Name']}", '/') : $entry['Name'],
			'timestamp' => time(),
			'size' => $entry['Size'],
		];
	}

	/**
	 * Uploads a file to IPFS
	 * @param string $path the destination path of the file
	 * @param string $contents the file contents to be uploaded
	 * @param bool $append if true it appends the content instead of replacing it
	 * @return array|bool metadata of false if the operation failed
	 */
	private function upload(string $path, string $contents, $append = false) {
		$args = ['arg' => "/{$path}", 'create' => true];
		if ($append) {
			$meta = $this->getMetadata($path);
			$args['offset'] = $meta['size'];
		} else {
			$args['truncate'] = true;
		}
		$response = $this->callAPI('POST', '/files/write', $args, ['data' => $contents]);
		if ($response != '') return false;
		return $this->getMetadata($path);
	}

	/**
	 * downloads a file via the IPFS API
	 * @param string $path path of the file
	 * @return bool|string returns contents of files or false if it failed
	 */
	private function download(string $path) {
		return $this->callAPI('GET', '/files/read', ['arg' => "/$path"]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path) {
		return $this->getMetadata($path) === false ? false : true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path) {
		$response = $this->download($path);
		return ['type' => 'file', 'path' => $path, 'contents' => $response]; //TODO: handle "file not found"
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path) {
		$response = $this->download($path);
		return ['type' => 'file', 'path' => $path, 'stream' => $response]; //TODO: handle "file not found"
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false) {
		$result = [];
		$response = json_decode($this->callAPI('GET', '/files/ls', ['arg' => "/$directory"]), true);
		foreach ($response['Entries'] as $e) $result[] = $this->normalizeFile($e, $directory);
		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path) {
		$response = json_decode($this->callAPI('GET', '/files/stat', ['arg' => "/$path"]), true);
		if (isset($response['Message'])) return false;
		$response['Name'] = $path;
		return $this->normalizeFile($response);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path) {
		return $this->getMetadata($path);
	}

	public function getMimetype($path) {
		//TODO
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path) {
		return $this->getMetadata($path);
	}

	public function getVisibility($path) {
		//TODO
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, Config $config) {
		return $this->upload($path, $contents);
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, Config $config) {
		return $this->upload($path, stream_get_contents($resource));
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, Config $config) {
		return $this->upload($path, $contents, true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateStream($path, $resource, Config $config) {
		return $this->upload($path, stream_get_contents($resource), true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath) {
		$args = '?arg=' . urlencode("/{$path}") . '&arg=' . urlencode("/{$newpath}");
		$response = $this->callAPI('GET', '/files/mv' . $args);
		return $response == '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newpath) {
		$args = '?arg=' . urlencode("/{$path}") . '&arg=' . urlencode("/{$newpath}");
		$response = $this->callAPI('GET', '/files/cp' . $args);
		return $response == '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path) {
		$response = $this->callAPI('GET', '/files/rm', ['arg' => "/$path"]);
		return $response == '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($dirname) {
		return $this->delete($dirname);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($dirname, Config $config) {
		$response = $this->callAPI('GET', '/files/mkdir', ['arg' => "/$dirname", 'parent' => true]);
		return $response == '';
	}

	public function setVisibility($path, $visibility) {
		//TODO
	}
}