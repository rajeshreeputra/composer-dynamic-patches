<?php

/**
 * @file
 * Contains rajeshreeputra\ComposerDynamicPatches\Resolvers\PatchesFile.
 */

namespace rajeshreeputra\ComposerDynamicPatches\Resolver;

use Composer\Package\Version\VersionParser;
use Composer\Composer\InstalledVersions;
use cweagans\Composer\Patch;
use Composer\IO\IOInterface;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\HttpDownloader;
use cweagans\Composer\PatchCollection;
use rajeshreeputra\ComposerDynamicPatches\Resolver\DynamicPatchesResolverBase;
use InvalidArgumentException;

class DynamicPatchesFile extends DynamicPatchesResolverBase
{
    /**
     * {@inheritDoc}
     */
    public function resolve(PatchCollection $collection): void
    {
        $patches_file = $this->grabAllPatches();
        $versionParser = new VersionParser();
        foreach ($this->findPatchesInJson($patches_file) as $package => $patches) {
            $package_version = \Composer\InstalledVersions::getPrettyVersion($package);
            $requiredConstraint = new Constraint('==', $package_version);
            foreach ($patches as $patch) {
                if (isset($patch->extra['version'])) {
                    $constraint = $versionParser->parseConstraints($patch->extra['version']);
                    if (
                        $constraint->matches($requiredConstraint) ||
                        (version_compare($package_version, $patch->extra['version']) == 0)
                    ) {
                        /** @var Patch $patch */
                        $collection->addPatch($patch);
                    }
                } else {
                    /** @var Patch $patch */
                    $collection->addPatch($patch);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function grabPatches($package)
    {
        // First, try to get the patches from the root composer.json.
        $extra = $package->getExtra();
        if (isset($extra['patches'])) {
            $this->io->write('<info>Gathering patches for root package.</info>');
            $patches = $extra['patches'];
            return $patches;
        } elseif (isset($extra['patches-file']) && is_string($extra['patches-file'])) {
            // If it's not specified there, look for a patches-file definition.
            $this->io->write('<info>Gathering patches from patch file.</info>');
            $patches = file_get_contents($extra['patches-file']);
            $patches = json_decode($patches, true);
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
            } elseif (!$patches) {
                throw new \Exception('There was an error in the supplied patch file');
            }
        } else {
            return array();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function grabAllPatches()
    {
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
        } catch (\LogicException $e) {
            // If the Locker isn't available, then we don't need to do this.
            // It's the first time packages have been installed.
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
     * $link_options_1 = [
     *  'fragment' => 'x',
     *  'attributes' => [
     *    'title' => t('X'),
     *    'class' => ['a', 'b']
     *  ]
     * ];
     * $link_options_1 = [
     *  'fragment' => 'x',
     *  'attributes' => [
     *    'title' => t('X'),
     *    'class' => ['c', 'd']
     *  ]
     * ];
     *
     * // This results in [
     *  'fragment' => ['x', 'y'],
     *  'attributes' => [
     *    'title' => [t('X'), t('Y')],
     *    'class' => ['a', 'b', 'c', 'd']
     *  ]
     * ].
     * $incorrect = array_merge_recursive($link_options_1, $link_options_2);
     *
     * // This results in [
     *  'fragment' => 'y',
     *  'attributes' => [
     *    'title' => t('Y'),
     *    'class' => ['a', 'b', 'c', 'd']
     *  ]
     * ].
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
    public static function mergeDeep()
    {
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
    public static function mergeDeepArray(array $arrays, $preserve_integer_keys = false)
    {
        $result = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                // Renumber integer keys as array_merge_recursive() does unless
                // $preserve_integer_keys is set to TRUE. Note that PHP automatically
                // converts array keys that are integer strings (e.g., '1') to integers.
                if (is_int($key) && !$preserve_integer_keys) {
                    $result[] = $value;
                } elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                    // Recurse when both values are arrays.
                    $result[$key] = self::mergeDeepArray([$result[$key], $value], $preserve_integer_keys);
                } else {
                    // Otherwise, use the latter value, overriding any previous value.
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}
