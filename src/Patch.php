<?php

namespace rajeshreeputra\ComposerDynamicPatches;

use Composer\Package\PackageInterface;
use cweagans\Composer\Patch as composerPatch;
use JsonSerializable;

class Patch extends composerPatch {
    /**
     * The package version.
     *
     * @var string $version
     */
    public string $version;

}
