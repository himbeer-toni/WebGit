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
# 
##
// ----------- DEBUGGING --------- disabled by comment
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// echo "<pre>" . __FILE__ . "</pre>";
// ----------- CONFIGURATION -----------
$repoRoot = '/home/pi/gitrepos'; // All git repos in this directory
$selfName = basename(__FILE__,".php");
$styleDir = __DIR__ . "/$selfName-style";
$styleWebPath = "/$selfName-style"; // Web-accessible path (relative to script location)
// ----------- GIT BINARY SELECTION -----------
$setuidGit = "$repoRoot/git4$selfName"; // path as before
$systemGit = '/usr/bin/git'; // fallback
if (is_executable($setuidGit)) {
    $gitBin = $setuidGit;
} elseif (is_executable($systemGit)) {
    $gitBin = $systemGit;
} else {
    $gitBin = 'git'; // rely on PATH
}
$selfUrl = basename(__FILE__);
// ----------- UTILS -----------
function themesAvailable($styleDir) {
    $themes = [];
    foreach (glob($styleDir . '/*-theme.css') as $css) {
        $name = basename($css);
        if (preg_match('/^(.*)-theme\.css$/', $name, $m)) {
            $themes[$m[1]] = $name;
        }
    }
    return $themes;
}
function getTheme() {
    if (!empty($_COOKIE['theme']) && preg_match('/^[a-zA-Z0-9_-]+$/', $_COOKIE['theme'])) {
        return $_COOKIE['theme'];
    }
    return 'dark'; // Default
}
function setThemeHeader($themes, $theme, $styleWebPath) {
    if (isset($themes[$theme])) {
        echo '<link rel="stylesheet" href="' . $styleWebPath . '/' . htmlspecialchars($themes[$theme]) . '" id="themecss">';
    } else {
        $first = reset($themes);
        if ($first) {
            echo '<link rel="stylesheet" href="' . $styleWebPath . '/' . htmlspecialchars($first) . '" id="themecss">';
        }
    }
}
function sanitizeRepo($repo) {
		if ( basename($repo) != $repo) {
			return "Do not even think of trying to trick me!";
		} else {
			return preg_replace('/[^\w.-]/', '', $repo);
		}
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
    $ansi = preg_replace_callback('/(\033\[[0-9;]*m)/', function($m) use ($map) {
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

if (!$repo) {
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
    // List all repos, skipping symlinks to dirs within $repoRoot
    $repos = [];
    $all = scandir($repoRoot);
    $repoRootReal = realpath($repoRoot);
		foreach ($all as $r) {
				if ($r[0] == '.') continue;
				$path = "$repoRoot/$r";
				if (is_dir($path) && !is_link($path)) {
						// Normal directory: show only if it has a .git
						if (!is_dir($path . '/.git')) continue;
						$repos[] = $r;
						continue;
				}
				if (is_link($path)) {
						$target = readlink($path);
						// Skip symlinks of the form ../Something (no further slash)
						if (preg_match('#^\.\./[^/]+$#', $target)) {
								continue;
						}
						// Only show if the resolved target is a git repo
						$real = realpath($path);
						if ($real === false || !is_dir($real . '/.git')) continue;
						$repos[] = $r;
						continue;
				}
				// skip everything else (files, broken links, etc)
		}
    sort($repos, SORT_NATURAL | SORT_FLAG_CASE);
} elseif ($level == 2 && repoExists($repoRoot, $repo)) {
    // Get commit list
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
    // Get commit diff and message
    $cmd = sprintf('%s -C %s show --color=always %s 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"), escapeshellarg($commit));
    $diff = shell_exec($cmd);
    // Get commit message only
    $msg = '';
    $cmd2 = sprintf('%s -C %s log -1 --pretty=format:"%%s" %s 2>&1',
        escapeshellarg($gitBin), escapeshellarg("$repoRoot/$repo"), escapeshellarg($commit));
    $msg = trim(shell_exec($cmd2));
    // For left nav
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

// ----------- ERROR HANDLING -----------
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
    <title>GitWeb<?php
        if ($level==2 && $repo) echo ': '.htmlspecialchars($repo);
        if ($level==3 && $repo && $commit) echo ': '.htmlspecialchars($repo).' '.htmlspecialchars($commit);
    ?></title>
    <?php setThemeHeader($themes, $theme, $styleWebPath); ?>
    <!-- Main layout CSS, after theme -->
    <link rel="stylesheet" href="<?=$styleWebPath?>/layout.css" id="layoutcss">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="level-<?=$level?>">
<div id="headline-row">
	<div class="hl-left">
  <?php if ($level==2): ?>
    <a href="<?=$selfUrl?>" class="levelup-btn" title="Back to list">&larr;</a>
  <?php elseif ($level==3): ?>
    <a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>" class="levelup-btn" title="Back to commits">&larr;</a>
  <?php endif; ?>
</div>
  <div class="hl-center">
    <?php if ($level==1): ?>
      Repository List
    <?php elseif ($level==2): ?>
      <?=htmlspecialchars($repo)?>
    <?php elseif ($level==3): ?>
      <?=htmlspecialchars($repo)?>: <span style="font-family:monospace;"><?=htmlspecialchars($commit)?></span>
    <?php endif; ?>
  </div>
  <div class="hl-right">
    <button class="theme-switcher" id="themeBtn" title="Switch theme"><?=htmlspecialchars($theme)?> &#x25BC;</button>
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
    <div class="subheadline"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<?php
// ----------- MAIN CONTENT -----------

// Level 1: Repo list
if ($level == 1): ?>
    <div class="main-pane">
        <?php if (empty($repos)): ?>
            <div>No repositories found in <code><?=htmlspecialchars($repoRoot)?></code>.</div>
        <?php else: ?>
            <h2 style="margin-top:0;">Repositories</h2>
            <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach($repos as $r): ?>
                <li style="margin-bottom:1.1em;">
								<a href="<?=$selfUrl?>?repo=<?=urlencode($r)?>" class="levelup-btn" style="font-size:1.08em;">
                        <?=htmlspecialchars($r)?>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php
// Level 2: Commit list for repo (NO nav-pane, only main-pane)
elseif ($level == 2 && !$notfound): ?>
    <div class="main-pane">
        <h2 style="margin-top:0;">Commit History</h2>
        <?php if (empty($commits)): ?>
            <div>No commits found in this repo.</div>
        <?php else: ?>
            <div>
            <?php foreach ($commits as $c): ?>
                <div class="commit-row">
                    <div class="commit-meta">
										<a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=htmlspecialchars($c['hash'])?>" class="levelup-btn commit-hash" style="font-family:monospace; font-size:1em;">
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
// Level 3: Commit diff
elseif ($level == 3 && !$notfound): ?>
    <div class="nav-content-layout">
        <div class="nav-pane">
            <div style="font-size:1.2em; font-weight:600; color:var(--subheadline-color); margin-bottom:1em;">
                <?=htmlspecialchars($repo)?>
            </div>
            <ul style="list-style:none; padding:0;">
            <?php foreach ($commits as $c): ?>
                <li style="margin-bottom:0.39em;">
										<a href="<?=$selfUrl?>?repo=<?=urlencode($repo)?>&commit=<?=$c['hash']?>" class="levelup-btn" style="font-family:monospace; font-size:1em;<?=($c['hash']==$commit?' background:var(--btn-bg-hover);':'')?>">
                        <?=htmlspecialchars($c['hash'])?>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <div class="main-pane">
            <div style="margin-bottom:2em;">
                <span style="font-size:1.16em; color:var(--subheadline-color); font-weight:600;"><?=htmlspecialchars($repo)?> / <span style="font-family:monospace;"><?=htmlspecialchars($commit)?></span></span>
            </div>
            <div class="git-diff"><?=ansi2html($diff)?></div>
        </div>
    </div>
<?php
// Not found
else: ?>
    <div class="main-pane">
        <h2>Not found</h2>
        <div>The page you wanted does not exist or is not available.</div>
        <div style="margin-top:2em;">
				<a href="<?=$selfUrl?>" class="levelup-btn">Go to Repository List</a>
        </div>
    </div>
<?php endif; ?>

<script>
const themeBtn = document.getElementById('themeBtn');
const themePopup = document.getElementById('themePopup');
if (themeBtn && themePopup) {
    themeBtn.addEventListener('click',function(e){
        e.stopPropagation();
        themePopup.classList.toggle('show');
    });
    themeBtn.addEventListener('mouseenter',function(){
        themeBtn.title = "Click to switch theme";
    });
    document.addEventListener('click',function(e){
        if(!themePopup.contains(e.target) && e.target!==themeBtn) {
            themePopup.classList.remove('show');
        }
    });
    themePopup.querySelectorAll('.theme-item').forEach(function(btn){
        btn.addEventListener('click',function(){
            var theme = btn.getAttribute('data-theme');
            document.cookie = "theme=" + encodeURIComponent(theme) + ";path=/;max-age=31536000";
            location.reload();
        });
        btn.addEventListener('mouseenter',function(){
            let altTheme = btn.textContent.trim();
            themeBtn.title = "Click to switch to " + altTheme + " theme";
        });
        btn.addEventListener('mouseleave',function(){
            themeBtn.title = "Click to switch theme";
        });
    });
}
</script>
</body>
</html>
