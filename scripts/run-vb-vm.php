<?php

$vmName = 'ampos';
$isoFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'php-linux.iso';

function run(string $cmd): string
{
    echo "> $cmd\n  ";

    $success = exec($cmd, $output, $exitCode);
    $output = implode("\n", $output);
    echo str_replace("\n", "\n  ", rtrim($output)) . "\n";

    if ($success === false || $exitCode !== 0) {
        throw new RuntimeException("Command `$cmd` failed (exit $exitCode):\n$output");
    }

    return $output;
}

if (!str_contains(run('VBoxManage list vms'), "\"$vmName\"")) {
    run("VBoxManage createvm --name=\"$vmName\" --platform-architecture=x86 --register");
    run("VBoxManage modifyvm \"$vmName\" --memory=2048 --cpus=2 --ostype=Linux26_64 --vram=160 --usb=on --nic1 nat");
    run("VBoxManage storagectl \"$vmName\" --name=SATA-Controller --add=sata --controller=IntelAhci --portcount=1");
    run("VBoxManage storageattach \"$vmName\" --storagectl=SATA-Controller --port=0 --device=0 --type=dvddrive --medium=\"$isoFile\"");
} elseif (str_contains(run('VBoxManage list runningvms'), "\"$vmName\"")) {
    run("VBoxManage controlvm \"$vmName\" poweroff");
}

run("VBoxManage startvm \"$vmName\" --type separate");
