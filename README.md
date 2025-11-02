# composer-dynamic-patches

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

This plugin extends [cweagans/composer-patches](https://github.com/cweagans/composer-patches) to provide **dynamic, version-specific patching** for Composer packages. It allows any installed package to define patches for other packages, with support for version constraints.

## Features

✅ **Version-specific patches** - Apply patches based on package versions using semantic versioning
✅ **Dynamic patch resolution** - Collect patches from all installed dependencies, not just root
✅ **Multiple patch sources** - Support for inline patches, external files, and URLs
✅ **Semver constraint matching** - Use version constraints like `1.0.0`, `^2.0`, `1.1.*`, etc.
✅ **Proper provenance tracking** - Track where patches come from for debugging
✅ **Compatible with composer-patches v2** - Built on top of the modern composer-patches architecture

## Requirements

- PHP 8.0 or higher
- Composer Plugin API v2
- cweagans/composer-patches ^2.0

## Installation

```bash
composer require rajeshreeputra/composer-dynamic-patches
```

Add the plugin to your `composer.json` allow-plugins configuration:

```json
{
    "config": {
        "allow-plugins": {
            "cweagans/composer-patches": true,
            "rajeshreeputra/composer-dynamic-patches": true
        }
    }
}
```

## Usage

### 1. Basic Patch Definition (No Version Constraint)

Apply patches to any package from any package:

```json
{
    "name": "vendor/my-package",
    "type": "library",
    "extra": {
        "patches": {
            "vendor/target-package": {
                "Fix critical bug": "https://example.com/patches/fix-bug.patch",
                "Add new feature": "./patches/feature.patch"
            }
        }
    }
}
```

### 2. Version-Specific Patches

Apply different patches based on the installed version:

```json
{
    "name": "vendor/my-package",
    "type": "library",
    "extra": {
        "patches": {
            "vendor/target-package": {
                "1.0.0": {
                    "Fix for 1.0.0": "patches/fix-1.0.0.patch"
                },
                "1.0.5": {
                    "Fix for 1.0.5": "patches/fix-1.0.5.patch"
                },
                "^1.1": {
                    "Fix for 1.1.x": "patches/fix-1.1.x.patch"
                },
                ">=2.0": {
                    "Fix for 2.0+": "patches/fix-2.0-plus.patch"
                }
            }
        }
    }
}
```

**Version Constraint Examples:**
- `"1.0.0"` - Exact version match
- `"^1.1"` - Compatible with 1.1.0 and higher (but < 2.0.0)
- `"~1.1"` - Compatible with 1.1.x
- `">=1.5"` - Version 1.5.0 or higher
- `"1.1.*"` - Any 1.1.x version

### 3. External Patches File

Load patches from an external JSON file:

**composer.json:**
```json
{
    "name": "vendor/my-package",
    "type": "library",
    "extra": {
        "patches-file": "patches.json"
    }
}
```

**patches.json:**
```json
{
    "vendor/target-package": {
        "1.0.0": {
            "Critical fix for 1.0.0": "https://example.com/fix-1.0.0.patch"
        },
        "^2.0": {
            "Enhancement for 2.x": "./local-patches/enhancement-2.x.patch"
        }
    },
    "another/package": {
        "Fix another issue": "patches/another-fix.patch"
    }
}
```

### 4. Expanded Format (Advanced)

For more control, use the expanded format with SHA256 verification and custom depth:

```json
{
    "extra": {
        "patches": {
            "vendor/package": [
                {
                    "description": "Security fix",
                    "url": "https://example.com/security-fix.patch",
                    "sha256": "abc123...",
                    "depth": 1
                }
            ]
        }
    }
}
```

## How It Works

1. **Background Operation**: The plugin runs automatically during `composer install`, `composer update`, and `composer require` commands
2. **Patch Collection**: Scans all installed packages for patch definitions
3. **Version Matching**: Evaluates version constraints against installed package versions
4. **Patch Application**: Applies matching patches using the composer-patches infrastructure
5. **Lock File**: Patches are tracked in `patches.lock.json` for consistency

## Disabling Default Resolvers

By default, this plugin disables the standard `PatchesFile` and `RootComposer` resolvers from composer-patches to avoid conflicts. This is configured in `composer.json`:

```json
{
    "extra": {
        "composer-patches": {
            "disable-resolvers": [
                "\\cweagans\\Composer\\Resolver\\PatchesFile",
                "\\cweagans\\Composer\\Resolver\\RootComposer"
            ]
        }
    }
}
```

If you want to enable them, remove these entries from your configuration.

## Verbose Output

For detailed information about patch resolution, use Composer's verbose flags:

```bash
# Verbose output
composer install -v

# Very verbose output (shows version constraint matching)
composer install -vv

# Debug output
composer install -vvv
```

## Troubleshooting

### Patches not being applied?

1. Check that the plugin is enabled in your `allow-plugins` configuration
2. Verify version constraints match your installed package versions
3. Run with `-vv` flag to see patch resolution details
4. Check `patches.lock.json` to see what patches were resolved

### Version constraint not matching?

- Use semantic versioning format (e.g., `^1.0`, not `1.0.x`)
- Check the installed version with `composer show vendor/package`
- Use `-vv` flag to see constraint evaluation messages

### JSON parse errors?

- Validate your JSON with a linter
- Check that patch file paths are correct
- Ensure patches-file exists and is readable

## Differences from composer-patches

| Feature | composer-patches | composer-dynamic-patches |
|---------|-----------------|-------------------------|
| Version-specific patches | ❌ | ✅ |
| Patches from dependencies | Limited | ✅ Full support |
| Semantic version constraints | ❌ | ✅ |
| Dynamic patch resolution | ❌ | ✅ |
| Commands (doctor, repatch) | ✅ | Inherits from base |
| SHA256 verification | ✅ | ✅ (inherited) |

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

GPL-2.0-or-later

## Credits

Built on top of [cweagans/composer-patches](https://github.com/cweagans/composer-patches) by Cameron Eagans.

## Support

- 🐛 [Report issues](https://github.com/rajeshreeputra/composer-dynamic-patches/issues)
- 💬 [Discussions](https://github.com/rajeshreeputra/composer-dynamic-patches/discussions)
- 📖 [Documentation](https://github.com/rajeshreeputra/composer-dynamic-patches)
