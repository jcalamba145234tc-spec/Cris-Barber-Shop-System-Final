<?php

// composer setup is a fresh-install shortcut, never an update procedure.
if (is_file(__DIR__.'/../.env') || is_file(__DIR__.'/../database/database.sqlite')) {
    fwrite(STDERR, "Setup refused: existing environment/database detected. Preserve APP_KEY and data; follow the explicit README update steps.\n");
    exit(1);
}
