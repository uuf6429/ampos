<?php

/**
 * Patches the PHP CLI to show the PHP version banner and run `/init.php` when the PID is 1.
 * @link https://github.com/php/php-src/blob/master/sapi/cli/php_cli.c
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php patch-php-init.php <path-to-php_cli.c>\n");
    exit(1);
}

$target = $argv[1];
$patchName = 'PHP init patch';
$patchPattern = '#(int\s+do_cli\(\s*int\s+argc,\s*char\s+\*\*argv\s*\)\s*/\*\s*\{\{\{\s*\*/\s+\{\s+)#';
$patchCode = <<<C
$1// --- $patchName start ---
	if (getpid() == 1 && argc == 1) {
		php_print_version(&cli_sapi_module);
		static char *init_argv[] = { NULL, "-f", "/src/bootstrap.php", NULL };
		init_argv[0] = argv[0];
		argv = init_argv;
		argc = 3;
	}
	// --- $patchName end ---
	
	
C;

$originalCode = file_get_contents($target);
if ($originalCode === false) {
    throw new RuntimeException("Cannot read file: $target");
}

if (str_contains($originalCode, "// --- $patchName start ---")) {
    echo "Patch already applied. Skipping.\n";
    exit(0);
}

$count = null;
$patchedCode = preg_replace($patchPattern, $patchCode, $originalCode, count: $count);
switch (true) {
    case $count === 0:
        throw new RuntimeException("Couldn't find do_cli() in $target");

    case $count > 1:
        throw new LogicException("Multiple do_cli() found in $target");

    case $patchedCode === null:
        throw new LogicException("Error in regex pattern: $patchPattern");
}

if (!file_put_contents($target, $patchedCode)) {
    throw new RuntimeException("Couldn't write patched code to $target");
}

echo "Patch applied to $target\n";
