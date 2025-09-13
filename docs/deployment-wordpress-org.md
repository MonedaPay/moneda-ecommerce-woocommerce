# Deploying a WordPress Plugin from GitHub to the WordPress.org Plugin Directory

This guide walks you through **two reliable ways** to publish a plugin that lives on GitHub to the official WordPress Plugin Directory:

1) **Manual deployment with SVN** (works everywhere)  
2) **Automated deployment from GitHub** using the _10up/WordPress Plugin Deploy_ GitHub Action

Where applicable, links to official docs are provided so you can verify details and keep current.

---

## 0) Prerequisites & checklist

- **WordPress.org account** (used for the Plugin Directory and SVN access).  
- Your plugin complies with the **Plugin Directory Guidelines** and common security practices.  
- Your plugin has a proper **main file header** (including `Plugin Name`, `Version`, `Requires at least`, `Requires PHP`, `Text Domain`, `License`, etc.).  
- A valid `readme.txt` that matches the **WordPress readme standard**.  
- Optional but recommended: icons/banners/screenshots in the **assets** folder.

> Notes
> - Since WordPress 5.8, `Requires at least` and `Requires PHP` are parsed from the **main PHP file header**, not from `readme.txt`. Keep both in sync, but the parser uses the plugin header as source of truth.
> - The `Stable tag` in `readme.txt` determines which tag is treated as the current release. If you set `Stable tag: 1.2.3`, the directory will use `/tags/1.2.3/` for display. If you ship from trunk, explicitly set `Stable tag: trunk`.

**References:** Developer guidelines; readme standard & parser behavior; assets rules; initial submission page.


---

## 1) One‑time: get your plugin a WordPress.org repository

1. Go to the official **“Add your plugin”** page and submit your plugin.  
2. Your submission is manually reviewed for guideline compliance.  
3. Once approved, you’ll receive your plugin’s **SVN repository**:  
   `https://plugins.svn.wordpress.org/<your-plugin-slug>/`

**References:** Submission page; planning & submitting; what the review & repo creation looks like.


---

## 2) Understand the WordPress.org repository layout

Your SVN repo will contain three top‑level directories:

```
assets/        # banners, icons, screenshots for the plugin page (NOT inside trunk)
trunk/         # the current development version or stable if you ship from trunk
tags/          # one folder per released version, e.g. 1.0.0, 1.1.0, etc.
```

**Assets** (banners, icons, screenshots) live in the **top-level `assets/`** directory in SVN, not inside `trunk`. Common image sizes:
- `banner-772x250.(png|jpg)` (header image)
- `icon-256x256.(png|jpg|svg)` (plugin icon; 128×128 and 256×256 variants supported)

**References:** Assets guide (sizes & placement).


---

## 3) Manual deployment with SVN (from any GitHub-hosted project)

> You use Git for day‑to‑day work and only use SVN to publish releases to WordPress.org.

### 3.1 Install SVN
- **macOS**: `brew install subversion`
- **Ubuntu/Debian**: `sudo apt-get install subversion`
- **Windows**: Install [TortoiseSVN] or use Git for Windows + SVN CLI.

### 3.2 Check out your plugin’s empty SVN repo
```bash
# Choose a working folder on your machine, then:
svn checkout https://plugins.svn.wordpress.org/<your-plugin-slug>/
cd <your-plugin-slug>
# You should now see assets/, trunk/, tags/ (possibly empty initially)
```

### 3.3 Prepare a clean release build from your GitHub repo
From your Git project (outside the SVN checkout):
```bash
# Ensure the version is bumped in the main plugin file and optionally in readme.txt
# Make a clean build (exclude dev files, node_modules, tests, etc.)
# Export your release-ready files to a temporary folder, e.g. ../release-build
git clone --depth=1 <your-github-repo-url> repo-tmp
cd repo-tmp
# (optional) run your build step here (npm run build / composer install --no-dev, etc.)
# remove dev-only files you don't want to ship
cd ..
```

### 3.4 Copy the build into `trunk/` and commit
```bash
# From the SVN working copy directory:
rsync -av --delete ../release-build/ trunk/

# Review status, then add any new files and remove deleted ones:
svn status
svn add --force trunk/*
svn rm --force $(svn status | awk '/^\!/ {print $2}')

# Commit trunk (this becomes the basis for your tag)
svn commit -m "Release 1.2.3 to trunk"
```

### 3.5 Tag the release in SVN
```bash
svn copy trunk tags/1.2.3
svn commit -m "Tag 1.2.3"
```
Ensure your `readme.txt` has `Stable tag: 1.2.3` (or `trunk` if you ship from trunk).

### 3.6 Add or update assets (optional)
```bash
# Place banners/icons/screenshots into the top-level assets/ folder
svn add --force assets/*
svn commit -m "Update plugin assets"
```

The Plugin Directory will index your commit shortly; your plugin page and update metadata will reflect the new version.

**References:** Using Subversion for plugins; stable tag behavior; assets.


---

## 4) Automated deployment from GitHub (recommended)

Use the maintained **10up “WordPress Plugin Deploy” GitHub Action**. It takes your Git **tag** and publishes it to WordPress.org SVN, moving `.wordpress-org/` assets to SVN’s top-level `assets/`, and respecting `.distignore` or `.gitattributes` to exclude dev files.

### 4.1 Add WordPress.org credentials to GitHub Secrets
Create repository secrets:
- `SVN_USERNAME` — your WordPress.org username
- `SVN_PASSWORD` — your WordPress.org password

> The Action logs in only during deploy. Do **not** commit credentials to your repo.

### 4.2 (Optional) Add `.distignore` to control what ships
Example minimal `.distignore`:
```
/.wordpress-org
/.github
/.git
/node_modules
/tests
/.distignore
/.gitignore
```

### 4.3 Place directory assets in `.wordpress-org/`
Keep your plugin page images alongside your Git repo in a folder that won’t ship with the plugin zip. The Action will move them to SVN’s `assets/` on deploy.

Common files:
```
.wordpress-org/banner-772x250.png
.wordpress-org/icon-256x256.png
.wordpress-org/screenshot-1.png
```

### 4.4 Add the deploy workflow
Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to WordPress.org

on:
  push:
    tags:
      - '*'
      # Alternatively: only semantic tags like 'v*' and strip the 'v' in a step

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Check out the repository
        uses: actions/checkout@v4

      # (Optional) Build your plugin here (npm/composer), then place build in a folder.
      # - run: npm ci && npm run build

      # Example: if you build to `build/`, set BUILD_DIR so only built files are deployed
      - name: WordPress Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@v2
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          # SLUG: your-wporg-slug  # only if different from repo name
          # BUILD_DIR: build       # uncomment if you deploy from a build folder
        with:
          # generate-zip: true     # optional: outputs a zip for release assets
          # dry-run: true          # optional: test without committing to SVN
```

**How it works**
- Pushing a **Git tag** (e.g., `1.2.3` or `v1.2.3`) triggers the workflow.  
- The Action commits your tagged code to SVN `trunk/` and **creates/updates** `tags/1.2.3/`.  
- It moves files from `.wordpress-org/` to the top-level SVN `assets/`.  
- It excludes files using `.distignore` (or `.gitattributes export-ignore`).

**References:** GitHub Action marketplace page; example configuration and file exclusions.


---

## 5) Versioning & metadata: keep these in sync

- **Main plugin file header**
  - `Version: 1.2.3`
  - `Requires at least: 6.0` (parsed from header since WP 5.8)
  - `Requires PHP: 7.4` (parsed from header since WP 5.8)
  - `Text Domain: your-plugin-slug`

- **readme.txt**
  - `Stable tag: 1.2.3` (or `trunk` if shipping from trunk)
  - `Tested up to: 6.x`
  - Proper sections: `Description`, `Installation`, `FAQ`, `Screenshots`, `Changelog`, etc.
  - Validate with the **Readme Validator** before releasing.

**References:** Readme standard & validator; parser behavior for requirements.


---

## 6) Releasing updates (summary checklist)

1. Bump **Version** in the main plugin file.  
2. Update `Stable tag` and `Changelog` in `readme.txt`.  
3. Ensure banners/icons/screenshots are current.  
4. **Manual SVN**: commit to `trunk/`, then `svn copy` to `tags/<version>`.  
   **OR**  
   **GitHub Action**: push a **Git tag** and let the workflow publish.  
5. Verify the plugin page and install/update flow in a test site.

---

## 7) Useful links

- **Submit a new plugin**: <https://wordpress.org/plugins/developers/add/>  
- **Detailed Plugin Guidelines**: <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>  
- **Readme standard** (+ stable tag behavior): <https://wordpress.org/plugins/readme.txt>  
- **How your readme works**: <https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/>  
- **Assets guide** (icons/banners/screenshots): <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>  
- **Using Subversion**: <https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/>  
- **Readme Validator**: <https://wordpress.org/plugins/developers/readme-validator/>  
- **GitHub Action – WordPress Plugin Deploy**: <https://github.com/marketplace/actions/wordpress-plugin-deploy>

---

## 8) Troubleshooting tips

- **Page not showing new version**: confirm `Stable tag` matches your SVN tag; ensure you committed both `trunk/` and `tags/<version>/`.  
- **Missing images on plugin page**: assets must be in the **SVN top‑level `assets/`**, not under `trunk/`. If using GitHub Action, keep them in `.wordpress-org/` so the action moves them for you.  
- **Unwanted files in release**: use `.distignore` (or `.gitattributes export-ignore`) to exclude build/dev files.  
- **Auth failures in CI**: re‑check `SVN_USERNAME`/`SVN_PASSWORD` secrets; the username is your **wordpress.org** login.  
- **Readme formatting oddities**: run the **Readme Validator** and fix headings/sections before tagging.
