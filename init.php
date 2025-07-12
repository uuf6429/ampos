<?php

echo "Hello from PHP in minimal Linux!\n";
file_put_contents(STDOUT, "Hello from PHP STDOUT in minimal Linux!\n");
file_put_contents(STDERR, "Hello from PHP STDERR in minimal Linux!\n");

sleep(5);

exit(30);
