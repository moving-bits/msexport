<?php

/**
 * Default configuration file for msexport.php:
 * Export parts of MuseScore file as mp3 file (in different variations)
 *
 * (c) 2020-2025 moving-bits (https://github.com/moving-bits)
 * Distributed under Apache 2.0 license
 */

/**
 * @var $bUseSingleThreaded
 * true:  use single-threaded export routine
 * false: use multi-threaded export (requires PHP Parallel lib to be installed)
 */
$bUseSingleThreaded = true;

/**
 * @var $cWorkDir
 * folder for temporary files (full path without ending slash/backslash)
 */
$cWorkDir = __DIR__ . DIRECTORY_SEPARATOR . 'temp';

/**
 * @var $cExportDir
 * target folder for created files (mp3/pdf) (full path without ending slash/backslash)
 */
$cExportDir = 'c:/temp/musescore';

/**
 * @var $cMuseScore
 * full path to MuseScore executable file
 */
$cMuseScore = '"c:/program files/musescore 3/bin/musescore3.exe"';

/**
 * @var $acVoices
 * name prefixes which should declare a part as being a "voice" instrument
 */
$acVoices = ['solo', 'soprano', 'alto', 'tenor', 'bass'];

/**
 * @var $iMaxThreads
 * number of max allowed parallel threads (multithreaded version only)
 */
$iMaxThreads = 4;
