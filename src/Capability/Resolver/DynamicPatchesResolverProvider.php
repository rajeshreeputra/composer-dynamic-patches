<?php

namespace rajeshreeputra\ComposerDynamicPatches\Capability\Resolver;

use cweagans\Composer\Resolver\Dependencies;
use cweagans\Composer\Resolver\PatchesFile;
use cweagans\Composer\Resolver\RootComposer;
use cweagans\Composer\Capability\Resolver\BaseResolverProvider;
use rajeshreeputra\ComposerDynamicPatches\Resolver\DynamicPatchesFile;

class DynamicPatchesResolverProvider extends BaseResolverProvider
{
    /**
     * @inheritDoc
     */
    public function getResolvers(): array
    {
        return [
            new DynamicPatchesFile($this->composer, $this->io, $this->plugin),
        ];
    }
}
