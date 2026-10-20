<?php
declare(strict_types=1);

/**
 * Keeps the shared code blocks of the automations in sync with Automation/_shared/.
 *
 * Every run.php stays a complete file that users copy and paste into eramba. Code that several
 * automations need lives once in Automation/_shared/<name>.php and is copied into each run.php
 * between two marker comments:
 *
 *   // ─── shared:http v1 (edit Automation/_shared/http.php, then run Automation/tools/sync.php) ───
 *   ...copy of Automation/_shared/http.php...
 *   // ─── end shared:http v1 sha256:0123456789abcdef ───
 *
 * Usage (maintainers only, from the repository root):
 *   php Automation/tools/sync.php check                  Check every run.php; exit 1 on any error.
 *   php Automation/tools/sync.php update <run.php> ...   Refresh the blocks of the given scripts.
 *
 * check fails when a block was edited inside a run.php (its hash no longer matches), when a shared
 * file changed without a version bump, on duplicate functions or constants, on eramba macros inside
 * shared code, on missing TPL_ settings, on top-level code in a block and on PHP syntax errors.
 * Blocks older than _shared are reported as stale: update a script deliberately, bump its
 * AUTOMATION_VERSION and changelog, and validate it again on a real eramba.
 */

const ROOT = __DIR__ . '/..';
const BEGIN = '~^// ─── shared:(?<name>[a-z0-9_/-]+) v(?<version>\d+) \(edit Automation/_shared/(?<file>[a-z0-9_/-]+)\.php, then run Automation/tools/sync\.php\) ───$~';
const END = '~^// ─── end shared:(?<name>[a-z0-9_/-]+) v(?<version>\d+) sha256:(?<hash>[0-9a-f]{16}) ───$~';
const MACRO = '~%[A-Z][A-Z0-9]*(?:_[A-Za-z0-9_]+)?%~';

final class SyncError extends RuntimeException
{
}

/** @return array{version:int, body:string} */
function sharedBlock(string $name): array
{
    static $cache = [];
    if (isset($cache[$name])) {
        return $cache[$name];
    }
    $path = ROOT . '/_shared/' . $name . '.php';
    if (preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)?$~', $name) !== 1 || !is_file($path)) {
        throw new SyncError("Unknown shared block '$name' (no Automation/_shared/$name.php).");
    }
    $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($path)));
    if (($lines[0] ?? '') !== '<?php' || ($lines[1] ?? '') !== 'declare(strict_types=1);'
        || preg_match('~^// version: (\d+) — .+$~u', $lines[2] ?? '', $m) !== 1) {
        throw new SyncError("_shared/$name.php must start with '<?php', 'declare(strict_types=1);' and '// version: N — description'.");
    }
    $body = trim(implode("\n", array_slice($lines, 3)), "\n");
    checkBlockBody($name, $body);
    return $cache[$name] = ['version' => (int) $m[1], 'body' => $body];
}

function blockHash(string $body): string
{
    return substr(hash('sha256', $body), 0, 16);
}

function beginMarker(string $name, int $version): string
{
    return "// ─── shared:$name v$version (edit Automation/_shared/$name.php, then run Automation/tools/sync.php) ───";
}

function endMarker(string $name, int $version, string $body): string
{
    return "// ─── end shared:$name v$version sha256:" . blockHash($body) . ' ───';
}

/** Blocks may only declare functions or classes, and must not contain eramba macros. */
function checkBlockBody(string $name, string $body): void
{
    if (preg_match(MACRO, $body, $m) === 1) {
        throw new SyncError("Shared block '$name' contains '{$m[0]}': eramba would replace it with a real value.");
    }
    $depth = 0;
    $expectDeclaration = true;
    foreach (token_get_all("<?php\n" . $body) as $token) {
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;
        if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if ($depth === 0 && $expectDeclaration && !in_array($id, [T_FUNCTION, T_FINAL, T_ABSTRACT, T_READONLY, T_CLASS], true)) {
            throw new SyncError("Shared block '$name' has top-level code ('$text'); blocks may only declare functions or classes.");
        }
        $expectDeclaration = false;
        if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
        } elseif ($text === '}') {
            $depth--;
            $expectDeclaration = $depth === 0;
        }
    }
}

/**
 * Splits a script into plain text and blocks.
 * @return list<array{type:'text', lines:list<string>}|array{type:'block', name:string, version:int, hash:string, body:string}>
 */
function parseScript(string $path, string $source): array
{
    $parts = [];
    $text = [];
    $block = null;
    foreach (explode("\n", $source) as $n => $line) {
        $where = "$path:" . ($n + 1);
        if (preg_match(BEGIN, $line, $m) === 1) {
            if ($block !== null) {
                throw new SyncError("$where: block '{$m['name']}' starts inside block '{$block['name']}'.");
            }
            if ($m['name'] !== $m['file']) {
                throw new SyncError("$where: marker names '{$m['name']}' but points to '{$m['file']}.php'.");
            }
            $parts[] = ['type' => 'text', 'lines' => $text];
            $text = [];
            $block = ['name' => $m['name'], 'version' => (int) $m['version'], 'lines' => []];
            continue;
        }
        if (preg_match(END, $line, $m) === 1) {
            if ($block === null || $block['name'] !== $m['name'] || $block['version'] !== (int) $m['version']) {
                throw new SyncError("$where: end marker for '{$m['name']} v{$m['version']}' does not close the open block.");
            }
            $parts[] = ['type' => 'block', 'name' => $block['name'], 'version' => $block['version'],
                'hash' => $m['hash'], 'body' => trim(implode("\n", $block['lines']), "\n")];
            $block = null;
            continue;
        }
        if (preg_match('~^\s*//.*\bshared:~', $line) === 1) {
            throw new SyncError("$where: malformed shared-block marker.");
        }
        if ($block !== null) {
            $block['lines'][] = $line;
        } else {
            $text[] = $line;
        }
    }
    if ($block !== null) {
        throw new SyncError("$path: block '{$block['name']}' has no end marker.");
    }
    $parts[] = ['type' => 'text', 'lines' => $text];
    $names = array_column(array_filter($parts, fn ($p) => $p['type'] === 'block'), 'name');
    if (count($names) !== count(array_unique($names))) {
        throw new SyncError("$path: the same shared block appears twice.");
    }
    return $parts;
}

function render(array $parts): string
{
    $out = [];
    foreach ($parts as $part) {
        if ($part['type'] === 'text') {
            array_push($out, ...$part['lines']);
        } else {
            $out[] = beginMarker($part['name'], $part['version']);
            $out[] = $part['body'];
            $out[] = endMarker($part['name'], $part['version'], $part['body']);
        }
    }
    return implode("\n", $out);
}

/** Problems of a parsed script. Returns [errors, warnings]. */
function checkScript(string $path, array $parts, string $source, string $file): array
{
    $errors = [];
    $warnings = [];
    $blocks = array_filter($parts, fn ($p) => $p['type'] === 'block');
    $outside = implode("\n", array_merge(...array_map(fn ($p) => $p['lines'], array_filter($parts, fn ($p) => $p['type'] === 'text'))));
    foreach ($blocks as $b) {
        if (blockHash($b['body']) !== $b['hash']) {
            $errors[] = "$path: block '{$b['name']}' was edited inside run.php. Move the change to Automation/_shared/{$b['name']}.php, bump its version and run update.";
            continue;
        }
        try {
            $shared = sharedBlock($b['name']);
            checkBlockBody($b['name'], $b['body']);
        } catch (SyncError $e) {
            $errors[] = "$path: " . $e->getMessage();
            continue;
        }
        if ($b['version'] > $shared['version']) {
            $errors[] = "$path: block '{$b['name']}' is v{$b['version']}, newer than _shared (v{$shared['version']}).";
        } elseif ($b['version'] < $shared['version']) {
            $warnings[] = "$path: block '{$b['name']}' is stale (v{$b['version']}, _shared is v{$shared['version']}).";
        } elseif ($b['body'] !== $shared['body']) {
            $errors[] = "$path: _shared/{$b['name']}.php changed without bumping its version (still v{$shared['version']}).";
        }
        preg_match_all('~\bTPL_[A-Z0-9_]+\b~', $b['body'], $settings);
        foreach (array_unique($settings[0]) as $setting) {
            if (preg_match('~^\s*const\s+' . $setting . '\s*=|\bdefine\(\s*\'' . $setting . '\'~m', $outside) !== 1) {
                $errors[] = "$path: block '{$b['name']}' needs the setting $setting; define it with const (or define() when it depends on a secret) in the script.";
            }
        }
    }
    $errors = array_merge($errors, duplicateDeclarations($path, $source));
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $code);
    if ($code !== 0) {
        $errors[] = "$path: " . trim(implode(' ', $lint));
    }
    return [$errors, $warnings];
}

/** Top-level functions, classes and constants declared twice (php -l does not catch constants). */
function duplicateDeclarations(string $path, string $source): array
{
    $seen = [];
    $errors = [];
    $tokens = token_get_all($source);
    $depth = 0;
    foreach ($tokens as $i => $token) {
        $text = is_array($token) ? $token[1] : $token;
        if ($text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
        } elseif ($text === '}') {
            $depth--;
        }
        if ($depth !== 0 || !is_array($token) || !in_array($token[0], [T_FUNCTION, T_CLASS, T_CONST], true)) {
            continue;
        }
        for ($j = $i + 1; isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j++);
        if (!isset($tokens[$j]) || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            continue; // closure or anonymous class
        }
        $key = ($token[0] === T_CONST ? 'const ' . $tokens[$j][1] : 'function/class ' . strtolower($tokens[$j][1]));
        if (isset($seen[$key])) {
            $errors[] = "$path: $key is declared twice.";
        }
        $seen[$key] = true;
    }
    return $errors;
}

function scripts(): array
{
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getFilename() === 'run.php' && !str_contains($file->getPathname(), '/vendor/')) {
            $found[] = $file->getPathname();
        }
    }
    sort($found);
    return $found;
}

function relative(string $path): string
{
    $root = realpath(ROOT . '/..') . '/';
    $real = realpath($path) ?: $path;
    return str_starts_with($real, $root) ? substr($real, strlen($root)) : $path;
}

function readSource(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        throw new SyncError("Cannot read $path.");
    }
    return str_replace("\r\n", "\n", $source);
}

function runCheck(): int
{
    $errors = [];
    $warnings = [];
    foreach (glob(ROOT . '/_shared/{*,*/*}.php', GLOB_BRACE) as $file) {
        try {
            sharedBlock(substr($file, strlen(ROOT . '/_shared/'), -4));
        } catch (SyncError $e) {
            $errors[] = $e->getMessage();
        }
    }
    $checked = 0;
    foreach (scripts() as $path) {
        $name = relative($path);
        try {
            $source = readSource($path);
            $parts = parseScript($name, $source);
        } catch (SyncError $e) {
            $errors[] = $e->getMessage();
            continue;
        }
        if (count($parts) === 1) {
            continue; // no shared blocks
        }
        $checked++;
        [$e, $w] = checkScript($name, $parts, $source, $path);
        array_push($errors, ...$e);
        array_push($warnings, ...$w);
    }
    foreach ($warnings as $warning) {
        fwrite(STDOUT, "WARNING $warning\n");
    }
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR $error\n");
    }
    fwrite(STDOUT, sprintf("%d scripts with shared blocks checked: %d errors, %d warnings.\n", $checked, count($errors), count($warnings)));
    return $errors === [] ? 0 : 1;
}

function runUpdate(array $paths): int
{
    if ($paths === []) {
        fwrite(STDERR, "Name the run.php files to update.\n");
        return 1;
    }
    $failed = false;
    foreach ($paths as $path) {
        $name = relative($path);
        try {
            $source = readSource($path);
            $parts = parseScript($name, $source);
            $changed = [];
            foreach ($parts as $i => $part) {
                if ($part['type'] !== 'block') {
                    continue;
                }
                if (blockHash($part['body']) !== $part['hash'] && $part['body'] !== '') {
                    throw new SyncError("$name: block '{$part['name']}' was edited inside run.php; move the change to _shared first. Nothing written.");
                }
                $shared = sharedBlock($part['name']);
                if ($part['body'] !== $shared['body'] || $part['version'] !== $shared['version']) {
                    $changed[] = $part["body"] === "" ? "{$part["name"]} v{$shared["version"]} (filled)" : "{$part["name"]} v{$part["version"]} → v{$shared["version"]}";
                    $parts[$i]['version'] = $shared['version'];
                    $parts[$i]['body'] = $shared['body'];
                    $parts[$i]['hash'] = blockHash($shared['body']);
                }
            }
            if ($changed === []) {
                fwrite(STDOUT, "$name: up to date.\n");
                continue;
            }
            $tmp = $path . '.sync-tmp';
            file_put_contents($tmp, render($parts));
            [$errors] = checkScript($name, parseScript($name, readSource($tmp)), readSource($tmp), $tmp);
            if ($errors !== []) {
                unlink($tmp);
                throw new SyncError(implode("\n", $errors) . "\nNothing written.");
            }
            rename($tmp, $path);
            fwrite(STDOUT, "$name: updated " . implode(', ', $changed) . ". Bump AUTOMATION_VERSION and the README changelog, then validate it on eramba.\n");
        } catch (SyncError $e) {
            fwrite(STDERR, 'ERROR ' . $e->getMessage() . "\n");
            $failed = true;
        }
    }
    return $failed ? 1 : 0;
}

$command = $argv[1] ?? 'check';
exit(match ($command) {
    'check' => runCheck(),
    'update' => runUpdate(array_slice($argv, 2)),
    default => (function () {
        fwrite(STDERR, "Usage: php Automation/tools/sync.php check | update <run.php> ...\n");
        return 1;
    })(),
});
