<?php

/**
 * @file
 * Contains rajeshreeputra\ComposerDynamicPatches\Resolvers\DynamicPatchesFile.
 */

namespace rajeshreeputra\ComposerDynamicPatches\Resolver;

use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Semver\Semver;
use cweagans\Composer\Patch;
use Composer\IO\IOInterface;
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
        $this->io->write('  - <info>Resolving dynamic patches from all installed packages.</info>');

        $patches_file = $this->grabAllPatches();

        if (empty($patches_file)) {
            $this->io->write(
                '    <info>No dynamic patches found in installed packages.</info>',
                true,
                IOInterface::VERBOSE
            );
            return;
        }

        $versionParser = new VersionParser();

        foreach ($this->findPatchesInJson($patches_file) as $package_name => $patches) {
            // Get the installed version of the package
            $package_version = $this->getInstalledPackageVersion($package_name);

            if ($package_version === null) {
                $this->io->write(
                    "    <comment>Package {$package_name} not found in installed packages. Skipping patches.</comment>",
                    true,
                    IOInterface::VERBOSE
                );
                continue;
            }

            $this->io->write(
                "    Processing patches for <info>{$package_name}</info> (version: {$package_version})",
                true,
                IOInterface::VERBOSE
            );

            foreach ($patches as $patch) {
                /** @var Patch $patch */

                // Check if patch has version constraint
                if (isset($patch->extra['version'])) {
                    $version_constraint = $patch->extra['version'];

                    try {
                        // Use Semver to check if version matches constraint
                        if (Semver::satisfies($package_version, $version_constraint)) {
                            $this->io->write(
                                "      ✓ Version constraint '{$version_constraint}' matches {$package_version}",
                                true,
                                IOInterface::VERY_VERBOSE
                            );
                            $patch->extra['provenance'] = "dynamic-patches:version-constraint:{$version_constraint}";
                            $collection->addPatch($patch);
                        } else {
                            $this->io->write(
                                "      ✗ Version constraint '{$version_constraint}' does not match {$package_version}",
                                true,
                                IOInterface::VERY_VERBOSE
                            );
                        }
                    } catch (\Exception $e) {
                        $this->io->writeError(
                            "      <error>Invalid version constraint '{$version_constraint}': {$e->getMessage()}</error>"
                        );
                    }
                } else {
                    // No version constraint, apply to all versions
                    $this->io->write(
                        "      ✓ No version constraint, applying patch",
                        true,
                        IOInterface::VERY_VERBOSE
                    );
                    $patch->extra['provenance'] = "dynamic-patches:all-versions";
                    $collection->addPatch($patch);
                }
            }
        }
    }

    /**
     * Get the installed version of a package.
     *
     * @param string $package_name
     *   The package name.
     *
     * @return string|null
     *   The installed version or null if not found.
     */
    protected function getInstalledPackageVersion(string $package_name): ?string
    {
        try {
            $repositoryManager = $this->composer->getRepositoryManager();
            $localRepository = $repositoryManager->getLocalRepository();

            $package = $localRepository->findPackage($package_name, '*');

            if ($package instanceof PackageInterface) {
                return $package->getPrettyVersion();
            }

            return null;
        } catch (\Exception $e) {
            $this->io->write(
                "    <comment>Error getting version for {$package_name}: {$e->getMessage()}</comment>",
                true,
                IOInterface::VERBOSE
            );
            return null;
        }
    }

    /**
     * Grab patches from a single package.
     *
     * @param PackageInterface $package
     *   The package to grab patches from.
     *
     * @return array
     *   An array of patches.
     */
    protected function grabPatches(PackageInterface $package): array
    {
        $extra = $package->getExtra();

        // First, try to get patches directly from extra.patches
        if (isset($extra['patches']) && is_array($extra['patches'])) {
            $this->io->write(
                "    <info>Found patches in {$package->getName()}</info>",
                true,
                IOInterface::VERBOSE
            );
            return $extra['patches'];
        }

        // Next, try to load from patches-file
        if (isset($extra['patches-file']) && is_string($extra['patches-file'])) {
            return $this->loadPatchesFromFile($package, $extra['patches-file']);
        }

        return [];
    }

    /**
     * Load patches from a patches file.
     *
     * @param PackageInterface $package
     *   The package that references the patches file.
     * @param string $patches_file_path
     *   The path to the patches file.
     *
     * @return array
     *   An array of patches.
     */
    protected function loadPatchesFromFile(PackageInterface $package, string $patches_file_path): array
    {
        try {
            $installationManager = $this->composer->getInstallationManager();
            $packagePath = $installationManager->getInstallPath($package);

            if (!$packagePath) {
                throw new InvalidArgumentException("Could not determine install path for {$package->getName()}");
            }

            $fullPath = $packagePath . '/' . $patches_file_path;

            if (!file_exists($fullPath) || !is_readable($fullPath)) {
                $this->io->writeError(
                    "    <error>Patches file not found or not readable: {$fullPath}</error>"
                );
                return [];
            }

            $this->io->write(
                "    <info>Loading patches from file: {$patches_file_path} in {$package->getName()}</info>",
                true,
                IOInterface::VERBOSE
            );

            $content = file_get_contents($fullPath);
            $patches = json_decode($content, true);

            // Check for JSON errors
            $json_error = json_last_error();
            if ($json_error !== JSON_ERROR_NONE) {
                $msg = json_last_error_msg();
                throw new InvalidArgumentException(
                    "JSON decode error in {$fullPath}: {$msg}"
                );
            }

            // Support both formats: {"patches": {...}} and direct patch array
            if (isset($patches['patches']) && is_array($patches['patches'])) {
                return $patches['patches'];
            } elseif (is_array($patches)) {
                return $patches;
            }

            throw new InvalidArgumentException("Invalid patches file format in {$fullPath}");

        } catch (\Exception $e) {
            $this->io->writeError(
                "    <error>Error loading patches file from {$package->getName()}: {$e->getMessage()}</error>"
            );
            return [];
        }
    }

    /**
     * Grab all patches from all installed packages.
     *
     * @return array
     *   An array of all patches keyed by package name.
     */
    protected function grabAllPatches(): array
    {
        try {
            $repositoryManager = $this->composer->getRepositoryManager();
            $localRepository = $repositoryManager->getLocalRepository();
            $packages = $localRepository->getPackages();

            $allPatches = [];

            foreach ($packages as $package) {
                $patches = $this->grabPatches($package);

                if (!empty($patches)) {
                    $allPatches = $this->mergeDeepArray([$allPatches, $patches], false);
                }
            }

            // Also check root package
            $rootPackage = $this->composer->getPackage();
            $rootPatches = $this->grabPatches($rootPackage);

            if (!empty($rootPatches)) {
                $this->io->write(
                    "    <info>Found patches in root package</info>",
                    true,
                    IOInterface::VERBOSE
                );
                $allPatches = $this->mergeDeepArray([$allPatches, $rootPatches], false);
            }

            return $allPatches;

        } catch (\Exception $e) {
            $this->io->writeError(
                "  <error>Error gathering patches: {$e->getMessage()}</error>"
            );
            return [];
        }
    }

    /**
     * Merges multiple arrays, recursively, and returns the merged array.
     *
     * This function is similar to PHP's array_merge_recursive() function, but it
     * handles non-array values differently. When merging values that are not both
     * arrays, the latter value replaces the former rather than merging with it.
     *
     * @param array $arrays
     *   An arrays of arrays to merge.
     * @param bool $preserve_integer_keys
     *   (optional) If given, integer keys will be preserved and merged instead of
     *   appended. Defaults to FALSE.
     *
     * @return array
     *   The merged array.
     */
    protected static function mergeDeepArray(array $arrays, bool $preserve_integer_keys = false): array
    {
        $result = [];

        foreach ($arrays as $array) {
            if (!is_array($array)) {
                continue;
            }

            foreach ($array as $key => $value) {
                // Renumber integer keys as array_merge_recursive() does unless
                // $preserve_integer_keys is set to TRUE.
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
