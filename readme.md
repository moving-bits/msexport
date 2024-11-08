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

| version         | characteristics                                                                     | file name suffix |
|-----------------|-------------------------------------------------------------------------------------|------------------|
| default version | current instrument is a bit louder, all other instruments are a bit muted           | `.mp3`           |
| Karaoke         | current instrument is off, all other instruments at regular volume                  | `(Karaoke).mp3`  |
| Solo            | only current instrument, all other instruments are off                              | `(Solo).mp3`     |
| Piano           | similar to default version, but all voices are replaced by "Grand Piano" instrument | `(Piano).mp3`    |

Additionally, three more files are generated:

| version     | characteristics                                                             | file name suffix    |
|-------------|-----------------------------------------------------------------------------|---------------------|
| all         | all instruments in same volume                                              | `-Alle.mp3`         |
| all (Piano) | all instruments in same volume, voices replaced by "Grand Piano" instrument | `-Alle (Piano).mp3` |
| score parts | scores for all instruments + per part                                       | `.pdf`              |

See [example section](#example) for an illustration.

## System requirements
- MuseScore 3 (or MuseScore 4, but with limitations)
- MuseScore score files in format 3.x or 2.x (or 4.x, but with limitations)
- PHP 8 (tested with PHP 8.1) with mbstring extension, called using the command line
- For multithreaded version: php_parallel extension

### MuseScore version / MuseScore 4 limitations
This script is currently used mainly in conjunction with MuseScore 3. With latest version of this script, it can also be used with a MuseScore 4 executable, but with some limitations:
- MuseScore 4 PDF export does not yet work in the same way as with MuseScore 3:
  - It will only generate a single PDF with all instruments, whereas MuseScore 3 additionally added excerpts for every instrument/voice. ([GitHub issue](https://github.com/musescore/MuseScore/issues/24532))
  - If a score is using "continuous view", exported PDF will have a very long page (instead of being split up into separate pages according to the configured page size)  ([GitHub issue](https://github.com/musescore/MuseScore/issues/22887))
- Modification of instruments does not work currently with MuseScore 4 files, it will always export the instruments declared in the score.

(There are GitHub items for the PDF issues, but as they got assigned P3 / "Prio: low" it may take some time to get them fixed, if at all.)

### Operating system environment

This script is testing using a Windows environment. It _should_ work under other platform supported by MuseScore and PHP as well, but that's not being tested by me.

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

## Releases

- v1.1 (2024-11-08) first release for MuseScore 4 (known issues: PDF export, switching of instruments)
- v1.0 (2024-04-27) initial release (targeted to MuseScore 3)