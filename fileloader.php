<?php

declare(strict_types=1);

require_once 'Src/Helpers/utils.php';

// This file does nog always exists
if (file_exists('/var/resources/confetti-cms__structure/model.php')) {
    require_once '/var/resources/confetti-cms__structure/model.php';
}
