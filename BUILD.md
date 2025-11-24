# Build Process for BuddyPress Favorite Notification

## Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)

## Installation

Install all required npm packages:

```bash
npm install
```

This will install:
- grunt
- grunt-checktextdomain
- grunt-wp-i18n
- grunt-contrib-clean
- grunt-contrib-copy
- grunt-contrib-compress

## Available Commands

### Check Text Domain

Verifies that all translation functions use the correct text domain (`bp-fav-notification`):

```bash
npm run checktextdomain
# or
grunt checktextdomain
```

**What it checks:**
- All `__()`, `_e()`, `_x()`, etc. functions
- Ensures text domain is 'bp-fav-notification'
- Scans all PHP files (excludes node_modules, vendor, dist, tests)

### Generate POT File

Creates the translation template file for translators:

```bash
npm run makepot
# or
grunt makepot
```

**Output:** `languages/bp-fav-notification.pot`

**What it includes:**
- All translatable strings from PHP files
- Plugin header information
- Proper POT headers for Poedit compatibility

### Build WordPress.org Package

Creates a clean, production-ready ZIP file for WordPress.org submission:

```bash
npm run build
# or
grunt build
```

**Process:**
1. Checks text domain
2. Generates POT file
3. Cleans dist directory
4. Copies production files
5. Creates ZIP package
6. Cleans temporary files

**Output:** `dist/buddypress-favorite-notification-2.0.0.zip`

**Excluded from ZIP:**
- node_modules/
- vendor/
- dist/
- tests/
- bin/
- .git/
- .github/
- Development files (Gruntfile.js, package.json, composer.json, phpcs.xml, etc.)
- Documentation files (*.md except readme.txt)
- Log files

### Create ZIP Only

Creates ZIP without running checks (faster):

```bash
npm run zip
# or
grunt compress
```

### Clean Build Directory

Removes the dist directory:

```bash
npm run clean
# or
grunt clean
```

### Watch for Changes

Automatically runs checks when PHP files change:

```bash
npm run watch
# or
grunt watch
```

## Build Workflow

### For Development

```bash
# Check text domain after adding new strings
npm run checktextdomain

# Generate POT file for translators
npm run makepot
```

### For Release

```bash
# Complete build process
npm run build

# Output: dist/buddypress-favorite-notification-2.0.0.zip
# This file is ready for WordPress.org submission
```

### For Testing Build

```bash
# Build the package
npm run build

# Install on test site
wp plugin install dist/buddypress-favorite-notification-2.0.0.zip --activate

# Or manually upload via WordPress admin
```

## File Structure After Build

```
dist/
└── buddypress-favorite-notification-2.0.0.zip
    └── buddypress-favorite-notification/
        ├── assets/
        ├── includes/
        ├── languages/
        │   └── bp-fav-notification.pot
        ├── templates/
        ├── bp-favorite-notification.php
        └── readme.txt
```

## Troubleshooting

### Text Domain Errors

If you see text domain errors:

```bash
npm run checktextdomain
```

Common issues:
- Missing text domain: `__( 'Text' )` should be `__( 'Text', 'bp-fav-notification' )`
- Wrong text domain: `__( 'Text', 'other-domain' )` should be `__( 'Text', 'bp-fav-notification' )`
- Variable text domain: Use string literal, not variable

### POT File Issues

If POT file is not generating:

1. Check that `languages/` directory exists
2. Ensure main plugin file has proper headers
3. Run with verbose output:
   ```bash
   grunt makepot --verbose
   ```

### ZIP File Issues

If ZIP file is incomplete:

1. Check the `copy` task in Gruntfile.js
2. Ensure files aren't excluded in the `src` array
3. Run clean before build:
   ```bash
   npm run clean
   npm run build
   ```

## NPM Scripts Reference

| Command | Description |
|---------|-------------|
| `npm run build` | Full build process (check → pot → zip) |
| `npm run checktextdomain` | Verify text domain usage |
| `npm run makepot` | Generate POT translation file |
| `npm run zip` | Create ZIP package only |
| `npm run clean` | Remove dist directory |
| `npm run watch` | Watch PHP files for changes |

## Grunt Tasks Reference

| Task | Description |
|------|-------------|
| `grunt` | Default: checktextdomain + makepot |
| `grunt check` | Check text domain only |
| `grunt i18n` | Check text domain + generate POT |
| `grunt build` | Complete build process |
| `grunt clean` | Remove dist directory |
| `grunt watch` | Watch for file changes |

## WordPress.org Submission Checklist

Before uploading to WordPress.org:

- [ ] Run `npm run build`
- [ ] Test the generated ZIP on clean WordPress install
- [ ] Verify all features work
- [ ] Check POT file exists in ZIP
- [ ] Ensure no development files in ZIP
- [ ] Verify version number matches plugin header
- [ ] Update readme.txt changelog
- [ ] Tag release in Git

## Continuous Integration

For automated builds in CI/CD:

```bash
# Install dependencies
npm ci

# Run checks
npm run checktextdomain

# Build package
npm run build

# Artifact: dist/buddypress-favorite-notification-2.0.0.zip
```

## Version Update Process

When releasing a new version:

1. Update version in `package.json`
2. Update version in `bp-favorite-notification.php` header
3. Update `BPFN_VERSION` constant in main plugin file
4. Update `Stable tag` in `readme.txt`
5. Update changelog in `readme.txt`
6. Run `npm run build`
7. Test the generated ZIP
8. Commit and tag: `git tag 2.0.0`
9. Upload to WordPress.org
