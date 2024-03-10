<?php

namespace rajeshreeputra\ComposerDynamicPatches\Plugin;

use Composer\Composer;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\IO\IOInterface;
use cweagans\Composer\Capability\Resolver\ResolverProvider;
use rajeshreeputra\ComposerDynamicPatches\Capability\Resolver\DynamicPatchesResolverProvider;

class Plugin implements PluginInterface, Capable
{
  /**
   * Apply plugin modifications to composer
   *
   * @param Composer $composer
   * @param IOInterface $io
   */
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

  /**
   * @inheritDoc
   */
    public function getCapabilities(): array
    {
        return [
        ResolverProvider::class => DynamicPatchesResolverProvider::class
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
