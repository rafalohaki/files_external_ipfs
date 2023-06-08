<?php

namespace OCA\Files_External_IPFS\Storage;

use League\Flysystem\FileNotFoundException;
use Pdsinterop\Flysystem\Nextcloud\NextcloudAdapter;
use OC\Files\Storage\PolyFill\CopyDirectory;

/**
 * Class IPFS
 * @package OCA\Files_External_IPFS\Storage
 * @author V31L <veil@mail.ch>
 */
class IPFS extends NextcloudAdapter {
    use CopyDirectory;

    private $adapter, $api;
    protected $root;

    public function __construct($params) {
        if (isset($params['host'])) {
            $this->root = isset($params['root']) ? "/{$params['root']}" : '/';
            $this->api = $params['host'];

            // Example: Create an instance of NextcloudAdapter with a given IStorage instance
            $storageInstance = new NextcloudAdapter($storageInstance);
            $this->adapter = $storageInstance;

            $this->buildFlySystem($this->adapter);
        } else {
            throw new \Exception('Creating \OCA\Files_External_IPFS\IPFS storage failed');
        }
    }

    public function __destruct() {
    }

    /**
     * Check for dependencies (as of now none)
     *
     * @return array|bool array of missing dependencies or true
     */
    public static function checkDependencies() {
        $deps = [];
        return count($deps) == 0 ? true : $deps;
    }

    public function getId() {
        return "IPFS::{$this->api}#{$this->root}";
    }
}
