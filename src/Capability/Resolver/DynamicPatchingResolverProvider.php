<?php

namespace rajeshreeputra\ComposerDynamicPatching\Capability\Resolver;

use cweagans\Composer\Resolver\Dependencies;
use cweagans\Composer\Resolver\PatchesFile;
use cweagans\Composer\Resolver\RootComposer;
use cweagans\Composer\Capability\Resolver\BaseResolverProvider;
use rajeshreeputra\ComposerDynamicPatching\Resolver\DynamicPatchingPatchesFile;

class DynamicPatchingResolverProvider extends BaseResolverProvider
{
    /**
     * @inheritDoc
     */
    public function getResolvers(): array
    {
        return [
            new DynamicPatchingPatchesFile($this->composer, $this->io, $this->plugin),
        ];
    }
}
