<?php

namespace rajeshreeputra\ComposerDynamicPatching\Plugin;

use Composer\Composer;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Util\ProcessExecutor;
use cweagans\Composer\Capability\Downloader\DownloaderProvider;
use cweagans\Composer\Capability\Patcher\PatcherProvider;
use cweagans\Composer\Capability\Resolver\ResolverProvider;
use cweagans\Composer\ConfigurablePlugin;
use cweagans\Composer\Locker;
use rajeshreeputra\ComposerDynamicPatching\Capability\Resolver\DynamicPatchingResolverProvider;


class Plugin implements PluginInterface, Capable {

  use ConfigurablePlugin;

  /**
   * @var Composer $composer
   */
  protected Composer $composer;

  /**
   * @var IOInterface $io
   */
  protected IOInterface $io;

  /**
   * @var EventDispatcher $eventDispatcher
   */
  protected EventDispatcher $eventDispatcher;

  /**
   * @var ProcessExecutor $executor
   */
  protected ProcessExecutor $executor;

  /**
   * @var array $patches
   */
  protected array $patches;

  /**
   * @var array $installedPatches
   */
  protected array $installedPatches;

  /**
   * @var ?PatchCollection $patchCollection
   */
  protected ?PatchCollection $patchCollection;

  protected Locker $locker;

  protected JsonFile $lockFile;

  /**
   * Get the path to the current patches lock file.
   */
  public static function getPatchesLockFilePath(): string
  {
      $composer_file = \Composer\Factory::getComposerFile();

      $dir = dirname(realpath($composer_file));
      $base = pathinfo($composer_file, \PATHINFO_FILENAME);

      if ($base === 'composer') {
          return "$dir/patches.lock.json";
      }

      return "$dir/$base-patches.lock.json";
  }

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io): void {
      $this->composer = $composer;
      $this->io = $io;
      $this->executor = new ProcessExecutor($this->io);
      $this->patches = array();
      $this->installedPatches = array();
      $this->lockFile = new JsonFile(
          static::getPatchesLockFilePath(),
          null,
          $this->io
      );
      $this->locker = new Locker($this->lockFile);
      $this->configuration = [
          'disable-resolvers' => [
              'type' => 'list',
              'default' => [],
          ],
          'disable-downloaders' => [
              'type' => 'list',
              'default' => [],
          ],
          'disable-patchers' => [
              'type' => 'list',
              'default' => [],
          ],
          'default-patch-depth' => [
              'type' => 'int',
              'default' => 1,
          ],
          'package-depths' => [
              'type' => 'list',
              'default' => [],
          ],
          'patches-file' => [
              'type' => 'string',
              'default' => 'patches.json',
          ]
      ];
      $this->configure($this->composer->getPackage()->getExtra(), 'composer-dynamic-patching');
    }

    /**
     * @inheritDoc
     */
    public function getCapabilities(): array {
        return [
            ResolverProvider::class => DynamicPatchingResolverProvider::class
        ];
    }

    public function deactivate(Composer $composer, IOInterface $io){}

    public function uninstall(Composer $composer, IOInterface $io){}


}
