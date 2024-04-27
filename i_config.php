<?php

/**
 * Configuration file for msexport.php:
 * Export parts of MuseScore file as mp3 file (in different variations)
 *
 * (c) 2020-2024 moving-bits (https://github.com/moving-bits)
 * Distributed under Apache 2.0 license
 */

/**
 * @const bUSE_SINGLE_THREADED
 * true:  use single-threaded export routine
 * false: use multi-threaded export (requires PHP Parallel lib to be installed)
 */
const bUSE_SINGLE_THREADED = false;

/**
 * @const bLAPTOP
 * (used for automatic distinction between two production environments, based on
 * environment variable COMPUTERNAME - may not be required for everyone)
 */
define('bLAPTOP', getenv('COMPUTERNAME') === 'ASTERIX16');

/**
 * @const cWORKDIR
 * folder for temporary files (full path without ending slash/backslash)
 */
const cWORKDIR = __DIR__ . DIRECTORY_SEPARATOR . 'temp';

/**
 * @const cEXPORTDIR
 * target folder for created files (mp3/pdf) (full path without ending slash/backslash)
 */
const cEXPORTDIR = (bLAPTOP ? 'c:' : 'y:') . '/sync/data';

/**
 * @var $cMuseScore
 * full path to MuseScore executable file
 */
$cMuseScore = '"c:/program files/musescore 3/bin/musescore3.exe"';

/**
 * @const VOICES
 * name prefixes which should declare a part as being a "voice" instrument
 */
const acVOICES = ['solo', 'sopran', 'alt', 'tenor', 'bass', 'männer'];

/**
 * @const MAX_THREADS
 * number of max allowed parallel threads (multithreaded version only)
 */
const MAX_THREADS = bLAPTOP ? 12 : 24;
