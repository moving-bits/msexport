# msexport

**Generates mp3 parts from existing [MuseScore](https://www.musescore.org) score file.**

Call this script with the full path name of the score file you want to generate mp3 files from:

`php msexport.php <full path to MuseScore file>`

(add paths to php executable and/or path to `msexport.php` as required)

Example: `php msexport.php /data/CanYouFeelTheLoveTonight.mscz`

## Details

This script will generate mp3 files for every part (instrument) it finds in the given score file which it regards as "voice".
(For MuseScore 3 file, the internal name for the instrument starts with _"voice."_, eg: _"voice.soprano"_. Additionally, it regards parts as "voice" if the instrument's long name starts with any of the prefixes given in the config file in variable `acVOICES`).

It generates several versions for every part:

| version       |characteristics| file name suffix |
|---------------|---|------------------|
| default version |current instrument is a bit louder, all other instruments are a bit muted| `.mp3`           |
| Karaoke       |current instrument is off, all other instruments at regular volume| `(Karaoke).mp3`  |
| Solo          |only current instrument, all other instruments are off| `(Solo).mp3`     |
| Piano         |similar to default version, but all voices are replaced by "Grand Piano" instrument| `(Piano).mp3`    |

Additionally, three more files are generated:

| version       |characteristics| file name suffix    |
|---------------|---|---------------------|
| all|all instruments in same volume| `-Alle.mp3`         |
| all (Piano)|all instruments in same volume, voices replaced by "Grand Piano" instrument| `-Alle (Piano).mp3` |
|  score parts|scores for all instruments + per part|`.pdf`|

See [example section](#example) for an illustration.

## System requirements
- MuseScore 3
- MuseScore score files in format 3.x or 2.x
- PHP 8 (tested with PHP 8.1) with mbstring extension, called using the command line
- For multithreaded version: php_parallel extension

Note 1: This script may or may not work with MuseScore 4 program and/or scores generated with MuseScore 4 - this is currently untested, as the new MuseSounds in MuseScore 4 are fine generally, but they have their quirks (like leaving out notes regarded as too high or too low for the specific voice), and generating mp3 files from it does not work well for me (last tested with MuseScore 4.1). Therefore, I'm still at MuseScore 3 for the time being.

Note 2: This script is testing using a Windows environment. It _should_ work under other platform supported by MuseScore and PHP as well, but that's not being tested by me.

## Configuration
Configuration is done in i_config.php, see that file for additional information on configuration options.

## Example
Think of a file `CanYouFeelTheLoveTonight.mscz` in folder `/data` containing parts for `Soprano`, `Alto`, `Tenor`, `Bass` and `Piano`.

Calling `php msexport.php /data/CanYouFeelTheLoveTonight.mscz` will generate the following files:
- CanYouFeelTheLoveTonight.pdf
- CanYouFeelTheLoveTonight-Alle.mp3
- CanYouFeelTheLoveTonight-Alle (Piano).mp3
- CanYouFeelTheLoveTonight-Alto.mp3
- CanYouFeelTheLoveTonight-Alto (Karaoke).mp3
- CanYouFeelTheLoveTonight-Alto (Piano).mp3
- CanYouFeelTheLoveTonight-Alto (Solo).mp3
- CanYouFeelTheLoveTonight-Bass.mp3
- CanYouFeelTheLoveTonight-Bass (Karaoke).mp3
- CanYouFeelTheLoveTonight-Bass (Piano).mp3
- CanYouFeelTheLoveTonight-Bass (Solo).mp3
- CanYouFeelTheLoveTonight-Soprano.mp3
- CanYouFeelTheLoveTonight-Soprano (Karaoke).mp3
- CanYouFeelTheLoveTonight-Soprano (Piano).mp3
- CanYouFeelTheLoveTonight-Soprano (Solo).mp3
- CanYouFeelTheLoveTonight-Tenor.mp3
- CanYouFeelTheLoveTonight-Tenor (Karaoke).mp3
- CanYouFeelTheLoveTonight-Tenor (Piano).mp3
- CanYouFeelTheLoveTonight-Tenor (Solo).mp3

All those files are generated with a single call to `msexport.php`. Depending on your computers speed and the `bUSE_SINGLE_THREADED` setting this will take less than one minute  in total.

Note: There will be no file generated for the `Piano` instrument, as `msexport.php` only generates extracts for voices. But the `Piano` instrument will be included in the generated parts.
