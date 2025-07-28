<?php
##
# Author: Himbeertoni
# Email: Toni.Himbeer@fn.de
# Github: https://www.github.com/himbeer-toni
# 
# This script is available for
# public use under GPL V3 (see
# https://www.gnu.org/licenses/gpl-3.0.en.html )
# 
# ©2025 Himbeertoni
##

$repoRoot = '/home/pi/gitrepos';
$selfName = basename(__FILE__, ".php");
$styleDir = __DIR__ . "/$selfName-style";
$styleWebPath = "/$selfName-style";
$setuidGit = "$repoRoot/git4$selfName";
$systemGit = '/usr/bin/git';
if (is_executable($setuidGit)) {
    $gitBin = $setuidGit;
} elseif (is_executable($systemGit)) {
    $gitBin = $systemGit;
} else {
    $gitBin = 'git';
}
$selfUrl = basename(__FILE__);

// Load and sort the font list
$fontFile = __DIR__ . "/$selfName-style/fontdata.txt";
$fonts = [];
if (file_exists($fontFile)) {
    $fonts = array_filter(array_map('trim', file($fontFile)));
    sort($fonts, SORT_NATURAL | SORT_FLAG_CASE);
}

// ----------- UTILS -----------
function sanitizeRepo($repo) {
    // Only allow [A-Za-z0-9_.-]
    return preg_replace('/[^A-Za-z0-9_.-]/', '', $repo);
}
function themesAvailable($styleDir) {
    $themes = [];
    if (is_dir($styleDir)) {
        foreach (scandir($styleDir) as $f) {
            if (preg_match('/^theme-(.+)\.css$/', $f, $m)) {
                $themes[$m[1]] = "$styleDir/$f";
            }
        }
    }
    return $themes;
}
function getTheme() {
    if (isset($_COOKIE['GitPeekTheme'])) return $_COOKIE['GitPeekTheme'];
    return 'dark';
}
function setThemeHeader($themes, $theme, $styleWebPath) {
    if (!array_key_exists($theme, $themes)) $theme = 'dark';
    echo '<link rel="stylesheet" href="'.$styleWebPath.'/theme-'.$theme.'.css" id="themecss">';
}
function repoExists($repoRoot, $repo) {
    return is_dir("$repoRoot/$repo/.git");
}
function ansi2html($ansi) {
    $ansi = htmlspecialchars($ansi);
    $map = [
        "\033[1;31m" => '<span class="git-red">',
        "\033[31m"   => '<span class="git-red">',
        "\033[1;32m" => '<span class="git-green">',
        "\033[32m"   => '<span class="git-green">',
        "\033[1;33m" => '<span class="git-yellow">',
        "\033[33m"   => '<span class="git-yellow">',
        "\033[1;36m" => '<span class="git-cyan">',
        "\033[36m"   => '<span class="git-cyan">',
        "\033[1m"    => '<span class="git-bold">',
        "\033[0m"    => '</span>',
        "\033[m"     => '</span>',
    ];
    $ansi = preg_replace_callback('/(\033\[[0-9;]*m)/', function ($m) use ($map) {
        return $map[$m[1]] ?? '';
    }, $ansi);
    $open = substr_count($ansi, '<span');
    $close = substr_count($ansi, '</span>');
    if ($open > $close) {
        $ansi .= str_repeat('</span>', $open - $close);
    }
    return nl2br($ansi);
}

// ----------- ROUTING LOGIC -----------
$repo = isset($_GET['repo']) ? sanitizeRepo($_GET['repo']) : null;
$commit = isset($_GET['commit']) ? preg_replace('/[^0-9a-f]/i', '', $_GET['commit']) : null;
$commit = isset($_GET['commit']) ? $_GET['commit'] : null;
if (($commit != '') && (!preg_match('/^[0-9a-f]+$/', $commit))) {
    $commit = "!$commit is invalid!";
}
$themes = themesAvailable($styleDir);
$theme = getTheme();

if (isset($_GET['fonts'])) {
    $fontidx = (int)$_GET['fonts'];
    $level = 4;
} else if (!$repo) {
    $level = 1;
} else if ($repo && !$commit) {
    $level = 2;
} else if ($repo && $commit) {
    $level = 3;
} else {
    $level = 1;
}

// ----------- DATA FETCHING -----------
if ($level == 1) {
    $repos = [];
    $all = scandir($repoRoot);
    $repoRootReal = realpath($repoRoot);
    foreach ($all as $r) {
        if ($r[0] == '.') continue;
        $path = "$repoRoot/$r";
        if (is_dir($path) && !is_link($path)) {
            if (!is_dir($path . '/.git')) continue;
            $repos[] = $r;
            continue;
        }
        if (is_link($path)) {
            $target = readlink($path);
            if (preg_match('#^\.\./[^/]+$#', $target)) {
                continue;
            }
            $real = realpath($path);
            if ($real === false || !is_dir($real . '/.git')) continue;
            if (str_contains(substr($real, strlen($repoRoot) + 1), '/') === false) continue;
            $repos[] = $r;
        }
    }
    sort($repos, SORT_NATURAL | SORT_FLAG_CASE);
} elseif ($level == 2 && repoExists($repoRoot, $repo)) {
    $cmd = sprintf('%s -C %s log --pretty=format:"%%h|%%ad|%%an|%%s" --date=short --no-color 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"));
    $gitlog = shell_exec($cmd);
    $commits = [];
    if ($gitlog) {
        foreach (explode("\n", trim($gitlog)) as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4) {
                $commits[] = ['hash' => $parts[0], 'date' => $parts[1], 'author' => $parts[2], 'subject' => $parts[3]];
            }
        }
    }
} elseif ($level == 3 && repoExists($repoRoot, $repo)) {
    $cmd = sprintf('%s -C %s show --color=always %s 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"), escapeshellarg($commit));
    $diff = shell_exec($cmd);
    $msg = '';
    $cmd2 = sprintf('%s -C %s log -1 --pretty=format:"%%s" %s 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"), escapeshellarg($commit));
    $msg = trim(shell_exec($cmd2));
    $cmd3 = sprintf('%s -C %s log --pretty=format:"%%h|%%ad|%%an|%%s" --date=short --no-color 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"));
    $gitlog = shell_exec($cmd3);
    $commits = [];
    if ($gitlog) {
        foreach (explode("\n", trim($gitlog)) as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4) {
                $commits[] = ['hash' => $parts[0], 'date' => $parts[1], 'author' => $parts[2], 'subject' => $parts[3]];
            }
        }
    }
}

$notfound = false;
if (($level == 2 || $level == 3) && !repoExists($repoRoot, $repo)) {
    $notfound = true;
} elseif ($level == 3 && empty($diff)) {
    $notfound = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?=$selfName?><?php
        if ($level==2 && $repo) echo ': '.htmlspecialchars($repo);
        if ($level==3 && $repo && $commit) echo ': '.htmlspecialchars($repo).' '.htmlspecialchars($commit);
    ?></title>
    <?php setThemeHeader($themes, $theme, $styleWebPath); ?>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family='system-ui:400|Open+Sans:400|Roboto:400|ABeeZee:400|Abyssinica+SIL:400|Acme:400|Actor:400|Aldrich:400|Annie+Use+Your+Telescope:400|Damion:400|M+PLUS+1+Code:400'" />
    <link rel="stylesheet" href="<?=$styleWebPath?>/layout.css" id="layoutcss">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
      .commit-nav-arrow.disabled {
        pointer-events: none;
        opacity: 0.4;
        cursor: default;
      }
      .commit-nav-arrow {
        text-decoration: none;
        font-size: 1.5em;
        line-height: 1;
        padding: 0 0.2em;
        color: inherit;
        transition: color 0.2s;
      }
      .commit-nav-arrow:not(.disabled):hover {
        color: var(--subheadline-color, #006be6);
      }
      /* Custom for commit nav row & msg area */
      .commit-nav-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5em;
      }
      .commit-msg-area {
        min-height: calc(1.5em * 3); /* 3 lines of 1.5em each */
        line-height: 1.5em;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 1.16em;
        color: var(--subheadline-color);
        font-weight: 600;
        margin-bottom: 0.5em;
        white-space: pre-line;
        word-break: break-word;
      }
    </style>
</head>
<body class="level-<?=$level?>" style="font-family:<?= htmlspecialchars($appFont) ?>,sans-serif;">
<div id="headline-row">
    <div class="hl-left">
      <?php if ($level==2): ?>
        <a href="<?=$selfUrl?>#repo-<?=urlencode($repo)?>" class="levelup-btn" title="Back to list">&larr;</a>
      <?php elseif ($level==3): ?>
        <a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>#commit-<?=htmlspecialchars($commit)?>" class="levelup-btn" title="Back to commits">&larr;</a>
      <?php elseif ($level==4): ?>
        <a href="<?=$selfUrl?>" class="levelup-btn" title="Back to list">&larr;</a>
      <?php endif; ?>
    </div>
    <div class="hl-center">
      <?php if ($level==1): ?>
        Repository List
      <?php elseif ($level==2): ?>
        <?=htmlspecialchars($repo)?>
      <?php elseif ($level==3): ?>
        <?=htmlspecialchars($repo)?>
      <?php elseif ($level==4): ?>
        Select font
      <?php endif; ?>
    </div>
    <div class="hl-right">
      <button class="theme-switcher" id="themeBtn" title="Switch theme"><?=htmlspecialchars($theme)?> &#x25BC;</button>
			<br/>
      <button class="theme-switcher" id="fontBtn" title="Switch theme">Fonts</button>
      <script>
      document.getElementById('fontBtn').onclick = function() {
        window.location = window.location.pathname + '?fonts=0';
      };
      </script>
      <div class="theme-popup" id="themePopup" role="menu">
        <?php foreach ($themes as $t => $css): ?>
          <button class="theme-item<?php if($t==$theme)echo' selected';?>" data-theme="<?=htmlspecialchars($t)?>">
            <?=ucfirst(htmlspecialchars($t))?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
</div>
<?php if ($level==3 && isset($msg) && $msg): ?>
    <!-- The commit message is now rendered below (fixed height) -->
<?php endif; ?>

<?php
// ----------- MAIN CONTENT -----------

if ($level == 1): ?>
    <div class="main-pane">
        <?php if (empty($repos)): ?>
            <div>No repositories found in <code><?=htmlspecialchars($repoRoot)?></code>.</div>
        <?php else: ?>
            <h2 style="margin-top:0;">Repositories</h2>
            <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach($repos as $r): ?>
                <li id="repo-<?=urlencode($r)?>" style="margin-bottom:1.1em;">
                  <a href="<?=$selfUrl?>?repo=<?=urlencode($r)?>#repo-<?=urlencode($r)?>" class="levelup-btn" style="font-size:1.08em;">
                        <?=htmlspecialchars($r)?>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php
elseif ($level == 2 && !$notfound): ?>
    <div class="main-pane">
        <h2 style="margin-top:0;">Commit History</h2>
        <?php if (empty($commits)): ?>
            <div>No commits found in this repo.</div>
        <?php else: ?>
            <div>
            <?php foreach ($commits as $c): ?>
                <div class="commit-row" id="commit-<?=htmlspecialchars($c['hash'])?>">
                    <div class="commit-meta">
                        <a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=htmlspecialchars($c['hash'])?>#commit-<?=htmlspecialchars($c['hash'])?>" class="levelup-btn commit-hash" style="font-family:monospace; font-size:1em;">
                            <?=htmlspecialchars($c['hash'])?>
                        </a>
                        <span class="commit-date"><?=htmlspecialchars($c['date'])?></span>
                        <span class="commit-author"><?=htmlspecialchars($c['author'])?></span>
                    </div>
                    <div class="commit-subject"><?=htmlspecialchars($c['subject'])?></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php
elseif ($level == 3 && !$notfound):
    // --- LEVEL 3: Commit detail view with navigation arrows ---

    // Find current commit index in list
    $curIdx = null;
    foreach ($commits as $i => $c) {
        if ($c['hash'] === $commit) { $curIdx = $i; break; }
    }
    $prevCommit = ($curIdx !== null && $curIdx < count($commits) - 1) ? $commits[$curIdx + 1]['hash'] : null;
    $nextCommit = ($curIdx !== null && $curIdx > 0) ? $commits[$curIdx - 1]['hash'] : null;
?>
    <div class="nav-content-layout">
        <div class="nav-pane">
            <div style="font-size:1.2em; font-weight:600; color:var(--subheadline-color); margin-bottom:1em;">
                <?=htmlspecialchars($repo)?>
            </div>
            <ul style="list-style:none; padding:0;">
            <?php foreach ($commits as $c): ?>
                <li id="commit-<?=htmlspecialchars($c['hash'])?>" style="margin-bottom:0.39em;">
                  <a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=$c['hash']?>#commit-<?=htmlspecialchars($c['hash'])?>" class="levelup-btn" style="font-family:monospace; font-size:1em;<?=($c['hash']==$commit?' background:var(--btn-bg-hover);':'')?>">
                        <?=htmlspecialchars($c['hash'])?>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <div class="main-pane">
            <div class="commit-nav-row">
            <?php if ($prevCommit): ?>
                <a class="commit-nav-arrow" href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=$prevCommit?>#commit-<?=$prevCommit?>" title="Previous commit">&#x25C0;</a>
            <?php else: ?>
                <span class="commit-nav-arrow disabled">&#x25C0;</span>
            <?php endif; ?>
            <span style="font-family:monospace; font-size:1em;"><?=htmlspecialchars($commit)?></span>
            <?php if ($nextCommit): ?>
                <a class="commit-nav-arrow" href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=$nextCommit?>#commit-<?=$nextCommit?>" title="Next commit">&#x25B6;</a>
            <?php else: ?>
                <span class="commit-nav-arrow disabled">&#x25B6;</span>
            <?php endif; ?>
            </div>
            <div class="commit-msg-area"><?=htmlspecialchars($msg)?></div>
            <div class="git-diff"><?=ansi2html($diff)?></div>
        </div>
    </div>
<?php
elseif ($level == 4): ?>
    <div class="main-pane">
      <h2 style="margin-top:0;">Font Selection</h2>
      <div class="font-list">
        <?php foreach ($fonts as $i => $f): ?>
          <div class="font-item<?php if($f==$appFont)echo' selected';?>" style="font-family:<?=htmlspecialchars($f)?>,sans-serif;">
            <a href="<?=$selfUrl?>?fonts=<?=$i?>" style="text-decoration:none;color:inherit;">
              <?=htmlspecialchars($f)?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
<?php endif; ?>
<script>
// -------- Font Selector --------
function setFontCookie(fontName) {
    document.cookie = 'appFont=' + encodeURIComponent(fontName) + ';path=/;max-age=31536000';
    location.reload();
}
const fontBtn = document.getElementById('fontBtn');
if (fontBtn) {
    fontBtn.onclick = function() {
        window.location = window.location.pathname + '?fonts=0';
    };
}

// -------- Theme Selector --------
const themeBtn = document.getElementById('themeBtn');
const themePopup = document.getElementById('themePopup');
if (themeBtn && themePopup) {
    themeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        themePopup.classList.toggle('show');
    });
    themeBtn.addEventListener('mouseenter', function() {
        themeBtn.title = "Click to switch theme";
    });
    document.addEventListener('click', function(e) {
        if(!themePopup.contains(e.target) && e.target!==themeBtn) {
            themePopup.classList.remove('show');
        }
    });
    themePopup.querySelectorAll('.theme-item').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var theme = btn.getAttribute('data-theme');
            document.cookie = "theme=" + encodeURIComponent(theme) + ";path=/;max-age=31536000";
            location.reload();
        });
        btn.addEventListener('mouseenter', function() {
            let altTheme = btn.textContent.trim();
            themeBtn.title = "Click to switch to " + altTheme + " theme";
        });
        btn.addEventListener('mouseleave', function() {
            themeBtn.title = "Click to switch theme";
        });
    });
}
</script>
</body>
</html>
