<?php

/**
 * msexport.php: Export parts of MuseScore file as mp3 file (in different variations)
 *
 * (c) 2020-2025 moving-bits (https://github.com/moving-bits)
 * Distributed under Apache 2.0 license
 *
 * Run with: php msexport.php <full path to MuseScore song file>
 * >>> Configuration of export path etc. is done in include file i_config.php <<<
 *
 * todo: MuseScore 4 issues
 * - PDF exports only exports full score, no parts (GitHub issue: https://github.com/musescore/MuseScore/issues/22887)
 * - PDF exports for files in "continuous mode" ignores page sizes and produces an "endless" page (GitHub issue: https://github.com/musescore/MuseScore/issues/24532)
 * - switching instruments does not yet work
 *
 * change history:
 * 2025-04-05   moving-bits     optional bank switching and instrument selection
 * 2025-04-04   moving-bits     put configuration defaults in separate config file i_config_defaults.php - breaking change: naming of configuration items
 * 2024-11-08   moving-bits     adjust volume in MuseScore 4 files
 * 2024-11-08   moving-bits     ignore excerpts in MuseScore 4 files
 * 2024-11-03	moving-bits		identify score file name in MuseScore 4 files
 * 2024-04-27   moving-bits     first published version (v1.0)
 * 2024-04-14   moving-bits     extract classes and configuration
 * 2023-06-17	moving-bits		include instrument named "solo"
 * 2023-04-23   moving-bits		added generation "all voices"
 * 2023-01-26   moving-bits		force more parallelism, don't wait for whole batch to finish before starting new tasks
 * 2023-01-22	moving-bits		adaption to laptop ASTERIX16
 * 2022-01-05	moving-bits		added PDF generation
 * 2021-05-15   moving-bits		multithreading added (requires module "parallel")
 * 2020-07-24   moving-bits		copy original .mscz file to workdir
 * 2020-05-19	moving-bits		adaption to PHP 7.4
 * 2020-04-03	moving-bits		export added
 * 2020-04-01	moving-bits		initial v0.1: analysis
 */

// --------------------------------------------------------------------------------------------------------------------
// check PHP environment, load program default & individual configuration

const MSEXPORT_VERSION = '1.3';
const MSEXPORT_DATE = '2025-04';
echo "\nmsexport v" . MSEXPORT_VERSION . " - export MuseScore score as mp3 parts (and more)\n(c) " . MSEXPORT_DATE . " moving-bits (https://github.com/moving-bits/)\nDistributed under Apache 2.0 license\n\n\r\n";

if (!extension_loaded('mbstring')) {
    echo "Missing mbstring extension\r\n";
    die();
}

require_once 'i_config_defaults.php';
if (!@include_once('i_config.php')) {
    echo "Warning: No individual configuration file \"i_config.php\" found, running on default values only.\r\n\r\n";
}
define('bUSE_SINGLE_THREADED', $bUseSingleThreaded);
define('cWORKDIR', $cWorkDir);
define('cEXPORTDIR', $cExportDir);
define('acVOICES', $acVoices);
define('aVOICE', $aVoice);
define('aPIANO', $aPiano);
define('MAX_THREADS', $iMaxThreads);

$bParallelExists = class_exists("\parallel\Runtime");
if (!$bParallelExists && !bUSE_SINGLE_THREADED) {
    echo "Cannot run in multi-threaded mode, missing package 'parallel'\r\n";
    die();
}

// some MS-specific constants
const MS4_AUDIOSETTINGS = 'audiosettings.json';
const MS_CTRLIDX_VOLUME = 7;
const MS_CTRLIDX_BANK = 32;

// --------------------------------------------------------------------------------------------------------------------

/**
 * CMuseScoreExportBase - base class for MuseScore part exports
 * (exporting every voice as separate .mp3 file + variations + PDF version)
 * cannot be called directly, use either single or multithreaded version
 */
abstract class CAbstractMuseScoreExport {

    protected $aParts = [];
    private $aPart = [];
    private $aAudio = null;
    protected $iNumParts = 0;
    protected $cVersion = ''; // MuseScore file version
    protected $aFN; // path components of source file
    protected $hMS3; // XML source
    protected $aTasks = [];

    protected function dieWithError($cSt) {
        echo "halted: " . $cSt;
        die();
    }

    protected function open_musescore($cFN) {
        $hFile = new ZIPArchive();
        if($hFile->open($cFN) !== TRUE) {
            $this->dieWithError('Error reading file "' . $cFN . '"');
        };
        return $hFile;
    }

    protected function isVoice($iPart) {
        if(mb_substr($this->cVersion, 0, 2) == '3.') {
            return (mb_substr((string)$this->aPart[$iPart]->Instrument->instrumentId,0,6) == 'voice.');
        }
        else {
            // older versions of MuseScore don't have this flag, use part name instead in those cases
            $cLongname = mb_strtolower((string)$this->aPart[$iPart]->Instrument->longName);
            foreach (acVOICES as $cVoice) {
                if (mb_substr($cLongname, 0, mb_strlen($cVoice)) == $cVoice) {
                    return true;
                }
            }
            return false;
        }
    }

    /* returns index of control item (creating it with given default value if necessary) */
    protected function getCtrlIdx($iPart, $iCtrl, $iDefaultValue) {
        $iIdx = -1;
        $aController = $this->aPart[$iPart]->Instrument->Channel->controller;
        for($i=0, $iNumController = count($aController); $i < $iNumController; $i++) {
            if((string)$aController[$i]['ctrl'] == $iCtrl) {
                $iIdx = $i;
                break;
            }
        }
        if ($iIdx == -1) {
            // add control item with given default value (if required)
            $hController = $this->aPart[$iPart]->Instrument->Channel->addChild('controller');
            $hController->addAttribute('ctrl', $iCtrl);
            $hController->addAttribute('value', $iDefaultValue);
            $iIdx = count($this->aPart[$iPart]->Instrument->Channel->controller) - 1;
        }
        return $iIdx;
    }

    protected function setVolume($iPart, $iVolThis, $iVolOthers, $iVolInstruments) {
        for($j=0; $j < $this->iNumParts; $j++) {
            $this->aPart[$j]->Instrument->Channel->controller[$this->aParts[$j]['iVolumeCtrlIdx']]['value'] = ($this->aParts[$j]['bIsVoice'] ? ($iPart == $j ? $iVolThis : $iVolOthers) : $iVolInstruments);
        }
        if ($this->aAudio != null) { // MS4-specific
            $iPartIdLookup = array_key_exists($iPart, $this->aParts) ? $this->aParts[$iPart]['iPartId'] : -1;
            foreach($this->aAudio['tracks'] as &$aTrack) {
                $bIsVoice = false;
                for($j=0; $j < $this->iNumParts; $j++) {
                    if ($this->aParts[$j]['iPartId'] == $aTrack['partId']) {
                        $bIsVoice = $this->aParts[$j]['bIsVoice'];
                        break;
                    }
                }
                $aTrack['out']['volumeDb'] = $this->volumePercentToDb(($bIsVoice ? ($iPartIdLookup == $aTrack['partId'] ? $iVolThis : $iVolOthers) : $iVolInstruments));
            }
        }
    }

    private function volumePercentToDb($iVolumePercent) {
        return $iVolumePercent < 5 ? -60 : ($iVolumePercent < 60 ? -9 : 0);
    }

    protected function setInstruments(array $iInstrument) {
        for($j=0; $j < $this->iNumParts; $j++) {
            if($this->aParts[$j]['bIsVoice']) {
                $this->aPart[$j]->Instrument->Channel->program['value'] = $iInstrument[1];
                $iBank = $this->getCtrlIdx($j, MS_CTRLIDX_BANK, 1);
                $this->aPart[$j]->Instrument->Channel->controller[$iBank]['value'] = $iInstrument[0];
            }
        }
    }

    protected function print_info() {
        for($i=0; $i < $this->iNumParts; $i++) {
            echo 'Part #' . $this->aParts[$i]['iPartId'] . ': '
                . $this->aParts[$i]['cLongname']
                . ' (' . ($this->aParts[$i]['bIsVoice'] ? 'voice' : 'instrument') . ')'
                . ' (sound=' . $this->aPart[$i]->Instrument->Channel->program['value'] . ')'
                . ' (vol=' . (string)$this->aPart[$i]->Instrument->Channel->controller[$this->aParts[$i]['iVolumeCtrlIdx']]['value'] . ')'
                . "\r\n"
            ;
        }
        echo "\r\n";
    }

    private function prepareExport($cAddFn) {
        // create copy of MuseScore file in work folder
        $cNewFN = cWORKDIR . DIRECTORY_SEPARATOR . $this->aFN['filename'] . $cAddFn . '.' . $this->aFN['extension'];
        copy($this->aFN['dirname'] . DIRECTORY_SEPARATOR . $this->aFN['basename'], $cNewFN);

        // open copy, update score, and save file
        $hCopy = $this->open_musescore($cNewFN);
        $hCopy->addFromString($this->aFN['filename'] . '.mscx', $this->hMS3->asXML());
        if ($this->aAudio != null) { // MS4-specific
            $hCopy->addFromString(MS4_AUDIOSETTINGS, json_encode($this->aAudio));
        }
        $hCopy->close();

        // remember parameters for actual program call
        $this->aTasks[] = array(
            $cAddFn,
            $GLOBALS['cMuseScore'] . ' -o "' . cEXPORTDIR . DIRECTORY_SEPARATOR . $this->aFN['filename'] . $cAddFn . '.mp3' . '" "' . $cNewFN . '"',
            $cNewFN
        );
    }

    static function doRunHelper(array $aTask) {
        echo "run helper $aTask[0]\n";
        // call MuseScore for conversion
        $cResult = exec($aTask[1]);
        echo "result: $cResult\r\n";
        // remove temporary file
        if ($aTask[2] != '') {
            unlink($aTask[2]);
        }
    }

    function doRun(String $cFilename) {
        // -----------------------------------------------------------------------------
        // Analyze params

        if($cFilename == '') {
            $this->dieWithError('missing name of MuseScore file');
        }
        $this->aFN = pathinfo($cFilename);

        // -----------------------------------------------------------------------------
        // copy MuseScore file to export folder
        copy($cFilename, cEXPORTDIR . DIRECTORY_SEPARATOR . $this->aFN['filename'] . '.' . $this->aFN['extension']);

        // open MuseScore file (=ZIP-format)
        $hMS = $this->open_musescore($cFilename);

        // look for pointer to main file
        $hMeta = $hMS->getFromName('META-INF/container.xml');
        if($hMeta === FALSE) {
            $this->dieWithError('error reading content structure (missing file pointer)');
        }
        $hIdx = simplexml_load_string($hMeta);
        if($hIdx === FALSE) {
            $this->dieWithError('error reading content structure (no valid XML)');
        }

        // extract filename of actual score
        $cFN = "";
        $aPaths = $hIdx->rootfiles->rootfile;
        for($i = 0; $i < count($aPaths); $i++) {
            $aPathinfo = pathinfo($aPaths[$i]['full-path'], PATHINFO_ALL);
            if ($aPathinfo['extension'] === "mscx" && substr($aPathinfo['dirname'], 0, 9) != 'Excerpts/') {
                $cFN = $aPaths[$i]['full-path'];
                break;
            }
        }
        if ($cFN == '') {
            $this->dieWithError('missing name of actual score file');
        }

        // read actual score
        $hMS2 = $hMS->getFromName($cFN);
        if($hMS2 === FALSE) {
            $this->dieWithError('error reading score from "' . $cFN . '"');
        }
        $this->hMS3 = simplexml_load_string($hMS2);
        if($this->hMS3 === FALSE) {
            $this->dieWithError('error reading score (no valid XML)');
        }
        $this->cVersion = $this->hMS3['version'];
        echo "file version: $this->cVersion\r\n";
        $this->aPart = $this->hMS3->Score->Part;
        $this->iNumParts = count($this->aPart);

        // -----------------------------------------------------------------------------

        // analyze parts
        for($i = 0; $i < $this->iNumParts; $i++) {
            $iVolumeCtrlIdx = $this->getCtrlIdx($i, MS_CTRLIDX_VOLUME, 80);
            $iPartId = intval($this->aPart[$i]['id']);
            if ($iPartId == 0) {
                $iPartId = $i;
            }
            $this->aParts[] = array(
                'bIsVoice'			=> $this->isVoice($i),
                'cLongname'			=> (string)$this->aPart[$i]->Instrument->longName,
                'iVolumeCtrlIdx'	=> $iVolumeCtrlIdx,
                'iPartId'			=> $iPartId // relevant for relation to audio settings in MS4
            );
        }

        // extract audio settings (MS4 files only)
        $cTemp = $hMS->getFromName(MS4_AUDIOSETTINGS);
        if ($cTemp !== FALSE) {
            $this->aAudio = json_decode($cTemp, true);
        }

        // close input score
        $hMS->close();

        // -----------------------------------------------------------------------------

        // output instrument info
        $this->print_info();

        // -----------------------------------------------------------------------------
        // prepare tasks

        echo "preparing source files...\r\n";
        // ----------------------------------
        // voice variants
        // ----------------------------------
        for($i=0; $i < $this->iNumParts; $i++) {
            $cLongname = $this->aParts[$i]['cLongname'];
            if ($this->aParts[$i]['bIsVoice']) {
                // ----------------------------------
                $this->setInstruments(aVOICE);

                $this->setVolume($i, 100, 50, 100);
                $this->prepareExport('-' . $cLongname);

                $this->setVolume($i, 0, 100, 100);
                $this->prepareExport('-' . $cLongname . ' (Karaoke)');

                $this->setVolume($i, 100, 0, 100);
                $this->prepareExport('-' . $cLongname . ' (Solo)');

                // ----------------------------------
                $this->setInstruments(aPIANO);
                $this->setVolume($i, 100, 50, 50);
                $this->prepareExport('-' . $cLongname . ' (Piano)');
            }
            else {
                echo 'skipping instrument ' . $cLongname . "\r\n";
            }
        }

        // ----------------------------------
        // all voices
        // ----------------------------------
        $this->setInstruments(aVOICE);
        $this->setVolume(-1, 100, 100, 100);
        $this->prepareExport('-Alle');

        $this->setInstruments(aPIANO);
        $this->setVolume(-1, 100, 100, 100);
        $this->prepareExport('-Alle (Piano)');

        // ----------------------------------
        // PDF version
        // ----------------------------------
        $this->aTasks[] = array(
            $this->aFN['filename'] . '.pdf',
            $GLOBALS['cMuseScore'] . ' --export-score-parts -o "' . cEXPORTDIR . DIRECTORY_SEPARATOR . $this->aFN['filename'] . '.pdf' . '" "' . $cFilename . '"',
            ''
        );

    }
}

// --------------------------------------------------------------------------------------------------------------------

/**
 * CMuseScoreExportSingleThreaded - implements a single-threaded export class
 */
class CMuseScoreExportSingleThreaded extends CAbstractMuseScoreExport {
    function doRun(String $cFilename) {
        parent::doRun($cFilename);

        foreach ($this->aTasks as $aTask) {
            echo $aTask[0] . '...';
            // call MuseScore for conversion
            exec($aTask[1]);
            // remove temporary file
            if ($aTask[2] != '') {
                unlink($aTask[2]);
            }
            echo ' [finished]' . "\r\n";
        }
    }
}

// --------------------------------------------------------------------------------------------------------------------

/**
 * CMuseScoreExportMultiThreaded - implements a multi-threaded export class
 * requires lib "parallel" to be installed in your PHP environment
 */
class CMuseScoreExportMultiThreaded extends CAbstractMuseScoreExport {
    private $maxThreads = 1;

    function __construct(int $maxThreads) {
        $this->maxThreads = $maxThreads;
    }

    public function doRun(String $cFilename) {
        parent::doRun($cFilename);

        // -----------------------------------------------------------------------------
        // create runner pool
        $pool = array();
        for($i=0; $i < $this->maxThreads; $i++) {
            $pool[$i] = new parallel\Runtime;
        }

        // -----------------------------------------------------------------------------
        // run tasks (do actual exports)
        echo "preparing '{$this->aFN['filename']}'...\r\n";
        $iStart = time();

        function generator(int $item_count) {
            for ($i=0; $i < $item_count; $i++) {
                yield $i;
            }
        }
        $generator = generator(count($this->aTasks));

        // function executing in each thread
        $producer = function($aParameter) {
            // call MuseScore for conversion
            exec($aParameter[1]);
            // remove temporary file
            if ($aParameter[2] != '') {
                unlink($aParameter[2]);
            }
            return $aParameter[0];
        };

        // Fill up threads with initial 'inactive' state
        $threads = array_fill(1, $this->maxThreads, ['is_active' => false]);

        while (true) {
            // Loop through threads until all threads are finished
            foreach ($threads as $thread_id => $thread) {
                if (!$thread['is_active'] and $generator->valid()) {
                    // Thread is inactive and generator still has values : run something in the thread
                    $item_id = $generator->current();
                    $threads[$thread_id]['run'] = \parallel\run($producer, [$this->aTasks[$item_id], $this->aFN['filename']]);
                    $threads[$thread_id]['is_active'] = true;
                    $threads[$thread_id]['starttime'] = time();
                    $generator->next();
                } elseif (!isset($thread['run'])) {
                    // Destroy supplementary threads in case generator closes sooner than number of threads
                    unset($threads[$thread_id]);
                } elseif ($thread['run']->done()) {
                    // Thread finished. Get results
                    $item = $thread['run']->value();
                    printf("finished: %s (%ds)\r\n", $item, abs(time() - $thread['starttime']));

                    if (!$generator->valid()) {
                        // Generator is closed then destroy thread
                        unset($threads[$thread_id]);
                    } else {
                        // Thread is ready to run again
                        $threads[$thread_id]['is_active'] = false;
                        $threads[$thread_id]['starttime'] = 0;
                    }
                }
            }

            // Escape loop when all threads are destroyed
            if (empty($threads)) break;
        }

        echo "\r\n[ready] total time: " . abs(time() - $iStart) . "s\r\n";

    }
}

// --------------------------------------------------------------------------------------------------------------------
// actual program code

$runner = bUSE_SINGLE_THREADED ? new CMuseScoreExportSingleThreaded() : new CMuseScoreExportMultiThreaded(MAX_THREADS);
$runner->doRun($argv[1]);
