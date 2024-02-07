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
use rajeshreeputra\ComposerDynamicPatching\Resolver\DynamicPatchingResolverBase;
use InvalidArgumentException;

class DynamicPatchingPatchesFile extends DynamicPatchingResolverBase {
    /**
     * {@inheritDoc}
     */
    public function resolve(PatchCollection $collection): void {
        $patches_file = $this->grabAllPatches();

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
     * {@inheritDoc}
     */
    public function grabPatches($package) {
      // First, try to get the patches from the root composer.json.
      $extra = $package->getExtra();
      if (isset($extra['patches'])) {
        $this->io->write('<info>Gathering patches for root package.</info>');
        $patches = $extra['patches'];
        return $patches;
      }
      // If it's not specified there, look for a patches-file definition.
      elseif (isset($extra['patches-file']) && is_string($extra['patches-file'])) {
        $this->io->write('<info>Gathering patches from patch file.</info>');
        $patches = file_get_contents($extra['patches-file']);
        $patches = json_decode($patches, TRUE);
        $error = json_last_error();
        if ($error != 0) {
          switch ($error) {
            case JSON_ERROR_DEPTH:
              $msg = ' - Maximum stack depth exceeded';
              break;
            case JSON_ERROR_STATE_MISMATCH:
              $msg =  ' - Underflow or the modes mismatch';
              break;
            case JSON_ERROR_CTRL_CHAR:
              $msg = ' - Unexpected control character found';
              break;
            case JSON_ERROR_SYNTAX:
              $msg =  ' - Syntax error, malformed JSON';
              break;
            case JSON_ERROR_UTF8:
              $msg =  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
              break;
            default:
              $msg =  ' - Unknown error';
              break;
            }
            throw new \Exception('There was an error in the supplied patches file:' . $msg);
          }
        if (isset($patches['patches'])) {
          $patches = $patches['patches'];
          return $patches;
        }
        elseif(!$patches) {
          throw new \Exception('There was an error in the supplied patch file');
        }
      }
      else {
        return array();
      }
    }

    /**
     * {@inheritDoc}
     */
    public function grabAllPatches() {
      try {
        $repositoryManager = $this->composer->getRepositoryManager();
        $localRepository = $repositoryManager->getLocalRepository();
        $installationManager = $this->composer->getInstallationManager();
        $packages = $localRepository->getPackages();
        $allPatches = [];
        foreach ($packages as $package) {
          $patches = $this->grabPatches($package);
          if ($patches) {
            $allPatches = $this->mergeDeep($allPatches, $patches);
          }
        }
        return $allPatches;
      }
      // If the Locker isn't available, then we don't need to do this.
      // It's the first time packages have been installed.
      catch (\LogicException $e) {
        return;
      }
    }

    /**
     * Merges multiple arrays, recursively, and returns the merged array.
     *
     * This function is similar to PHP's array_merge_recursive() function, but it
     * handles non-array values differently. When merging values that are not both
     * arrays, the latter value replaces the former rather than merging with it.
     *
     * Example:
     * @code
     * $link_options_1 = array('fragment' => 'x', 'attributes' => array('title' => t('X'), 'class' => array('a', 'b')));
     * $link_options_2 = array('fragment' => 'y', 'attributes' => array('title' => t('Y'), 'class' => array('c', 'd')));
     *
     * // This results in array('fragment' => array('x', 'y'), 'attributes' => array('title' => array(t('X'), t('Y')), 'class' => array('a', 'b', 'c', 'd'))).
     * $incorrect = array_merge_recursive($link_options_1, $link_options_2);
     *
     * // This results in array('fragment' => 'y', 'attributes' => array('title' => t('Y'), 'class' => array('a', 'b', 'c', 'd'))).
     * $correct = NestedArray::mergeDeep($link_options_1, $link_options_2);
     * @endcode
     *
     * @param array ...
     *   Arrays to merge.
     *
     * @return array
     *   The merged array.
     *
     * @see NestedArray::mergeDeepArray()
     */
    public static function mergeDeep() {
      return self::mergeDeepArray(func_get_args());
    }

    /**
     * Merges multiple arrays, recursively, and returns the merged array.
     *
     * This function is equivalent to NestedArray::mergeDeep(), except the
     * input arrays are passed as a single array parameter rather than a variable
     * parameter list.
     *
     * The following are equivalent:
     * - NestedArray::mergeDeep($a, $b);
     * - NestedArray::mergeDeepArray(array($a, $b));
     *
     * The following are also equivalent:
     * - call_user_func_array('NestedArray::mergeDeep', $arrays_to_merge);
     * - NestedArray::mergeDeepArray($arrays_to_merge);
     *
     * @param array $arrays
     *   An arrays of arrays to merge.
     * @param bool $preserve_integer_keys
     *   (optional) If given, integer keys will be preserved and merged instead of
     *   appended. Defaults to FALSE.
     *
     * @return array
     *   The merged array.
     *
     * @see NestedArray::mergeDeep()
     */
    public static function mergeDeepArray(array $arrays, $preserve_integer_keys = FALSE) {
      $result = [];
      foreach ($arrays as $array) {
        foreach ($array as $key => $value) {
          // Renumber integer keys as array_merge_recursive() does unless
          // $preserve_integer_keys is set to TRUE. Note that PHP automatically
          // converts array keys that are integer strings (e.g., '1') to integers.
          if (is_int($key) && !$preserve_integer_keys) {
            $result[] = $value;
          }
          // Recurse when both values are arrays.
          elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
            $result[$key] = self::mergeDeepArray([$result[$key], $value], $preserve_integer_keys);
          }
          // Otherwise, use the latter value, overriding any previous value.
          else {
            $result[$key] = $value;
          }
        }
      }
      return $result;
    }
}
