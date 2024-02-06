<?php

/**
 * @file
 * Contains rajeshreeputra\ComposerDynamicPatching\Resolvers\PatchesFile.
 */

namespace rajeshreeputra\ComposerDynamicPatching\Resolver;

use Composer\Package\Version\VersionParser;
use Composer\Composer\InstalledVersions;
use cweagans\Composer\Patch;
use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use cweagans\Composer\PatchCollection;
use cweagans\Composer\Resolver\ResolverBase;
use InvalidArgumentException;

class DynamicPatchingPatchesFile extends ResolverBase {
    /**
     * {@inheritDoc}
     */
    public function resolve(PatchCollection $collection): void {

        $patches_file = $this->plugin->getConfig('patches-file');
        print_r($patches_file);
        $valid_patches_file = file_exists(realpath($patches_file)) && is_readable(realpath($patches_file));

        // If we don't have a valid patches file, exit early.
        if (!$valid_patches_file) {
            return;
        }

        $this->io->write('  - <info>Resolving patches from patches file.</info>');
        $patches_file = $this->readPatchesFile($patches_file);

        foreach ($this->findPatchesInJson($patches_file) as $package => $patches) {
          //print_r($patches);
          $package_version = \Composer\InstalledVersions::getPrettyVersion($package);
          foreach ($patches as $patch) {
            if (!isset($patch->version) ||
                (version_compare($package_version, $patch->version) == 0)) {
              /** @var Patch $patch */
              $collection->addPatch($patch);
            }

          }
        }
    }

    /**
     * Read a patches file.
     *
     * @param $patches_file
     *   A URI to a file. Can be anything accepted by file_get_contents().
     * @return array
     *   A list of patches.
     * @throws InvalidArgumentException
     */
    protected function readPatchesFile($patches_file): array {
        if ($patches_file === '') {
            return [];
        }

        $patches = file_get_contents($patches_file);
        $patches = json_decode($patches, true);

        // First, check for JSON syntax issues.
        $json_error = json_last_error_msg();
        if ($json_error !== "No error") {
            throw new InvalidArgumentException($json_error);
        }

        // Next, make sure there is a patches key in the file.
        if (!array_key_exists('patches', $patches)) {
            throw new InvalidArgumentException('No patches found.');
        }

        // If nothing is wrong at this point, we can return the list of patches.
        return $patches['patches'];
    }
}