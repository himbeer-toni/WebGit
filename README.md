# WebGit

WebGit is a lightweight PHP script for browsing local git repositories via a web browser. It provides commit history, commit diffs (with color highlighting), and basic navigation, designed for self-hosting on a local or private server.

## Features

- **View commit history** of any repository in a directory.
- **See detailed diffs** for each commit, with colored highlights (additions, removals, metadata).
- **Theme-switching** (dark/light) via the UI.
- **Navigation**: Click to move between the list of repositories, commit history, and single commit view.
- **Minimal dependencies**: Only requires PHP and read access to your git repositories.

## Git Binary Selection

WebGit can use a special setuid copy of the `git` binary to avoid permission issues that often occur when the webserver runs as a different user than the owner of the repositories. 

- **If a setuid git binary (default `/home/pi/gitrepos/webgit`) exists and is executable, WebGit will use it.**
- **If it does not exist, WebGit will fall back to the system git (`/usr/bin/git` or `git` in `$PATH`).**

> **Note:** In most real-world setups, a setuid git binary is required to allow the web server (often running as `www-data`) to access repositories owned by another user (e.g., `pi`). Without it, you may encounter permission errors or git refusing to operate due to "dubious ownership." For development or single-user installations where the web server and repositories share ownership, the system git may suffice.

The Makefile includes a target to build and install the setuid git binary with suitable ownership and permissions.

## Installation

1. **Clone or download this repository.**
2. **Install files** to your web server root using the provided Makefile:
   ```sh
   make install
   ```
   This copies `webgit.php` and associated CSS to `/data/www` (edit `PBINDIR`/`PSTYDIR` as needed).

3. **Set up the setuid git binary** (recommended for multi-user or production setups):
   - The Makefile will create `/home/pi/gitrepos/webgit` as a copy of `/usr/bin/git`, owned by `pi:www-data` with mode `4610`.
   - Adjust the `REPODIR`, `XTRGOWN`, `XTRGGRP`, `XTRGSRC`, and `XTARGET` variables in the Makefile if your paths or users differ.

4. **Point your web browser to** `http://yourserver/webgit.php`.

## Usage

- **Repository List:** The landing page lists all git repositories under `$(REPODIR)` (default `/home/pi/gitrepos`).
- **Commit History:** Click a repository to see its commit history.
- **Commit Diff:** Click a commit hash to see the full diff, color-coded.
- **Theme Switch:** Use the theme switcher (top right) to toggle between dark and light themes.
- **Navigation:** Use the ← button (top left) to go up a level.

## Security Notes

- This tool is intended for use on local or trusted networks only.
- The setuid git binary is a potential security risk if misconfigured. Make sure only trusted users have access to the web server and repository directories.

---

## Appendix: Understanding Colored Git Diff Output

WebGit displays git diffs with color highlights for clarity, closely matching standard `git diff` output. Here’s what you may see:

### Diff Output Components

- **Commit Information**  
  - `commit <hash>`, `Author:`, `Date:`: Shown at the top of a commit.
  - Displayed in yellow.

- **Diff Headers**  
  - `diff --git a/file b/file`: Indicates which files are being compared.
  - `index <hash>..<hash> <mode>`: Shows file hashes and mode.
  - `--- a/file`, `+++ b/file`: Old and new filenames.
  - `@@ ... @@`: "Hunk" header, shows line numbers for the change.
  - All of these are shown in yellow.

- **File Changes**
  - Lines starting with `+` (plus): **Added lines**, highlighted in green.
  - Lines starting with `-` (minus): **Removed lines**, highlighted in red.
  - Lines starting with a space: Unchanged context, default color.
  - Lines starting with `\`: Meta lines (e.g., `\ No newline at end of file`), usually yellow.

- **Other Special Lines**
  - Lines with file permission changes, new file or deleted file messages, or mode changes, are typically shown in yellow.
  - Bold may be used for emphasis (e.g., commit ID).

### Example

```diff
commit 1a2b3c4d5e6f7g8h9i0j
Author: Jane Doe <jane@example.com>
Date:   2024-07-10

    Add new feature

diff --git a/file.txt b/file.txt
index 1234567..89abcd0 100644
--- a/file.txt
+++ b/file.txt
@@ -1,6 +1,7 @@
 Line unchanged
-Line removed
+Line added
 Context line
\ No newline at end of file
```

#### Color Key

- **Yellow:** Commit info, hunk headers, file headers, meta lines.
- **Green:** Lines starting with `+` (additions).
- **Red:** Lines starting with `-` (removals).
- **Default:** All other lines (context).

### Notes

- The coloring is handled by WebGit’s CSS and PHP, using `<span>` tags for semantic highlighting.
- This makes it easier to read and understand changes at a glance, especially on larger diffs.
- Only the main content of the `git diff` is shown; binary files and non-text changes may appear as messages (yellow).

---

## License

This project is licensed under the GNU General Public License v3.0 (GPLv3).

**What does this mean?**  
- You are free to use, study, modify, and share this software.
- If you distribute modified versions, you must also provide the source code and keep them under the same GPLv3 license.
- This ensures that all users have the same freedoms with the software.

For full details, please see the [official GPL v3 license text](https://www.gnu.org/licenses/gpl-3.0.html).
