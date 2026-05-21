<?php

namespace app\services;

use Yii;
use app\models\Benchmarks;
use app\models\BenchChips;

/**
 * BenchService — benchmark data cleanup, chip name resolution, and chip record maintenance.
 *
 * Ported from: web/yaamp/core/backend/bench.php → BenchUpdateChips()
 *
 * Note: getChipName() is defined in web/yaamp/modules/bench/functions.php.
 * It is guarded with function_exists() so the cleanup queries still run even
 * when the legacy bench module is not loaded.
 */
class BenchService
{
    /**
     * Clean up invalid benchmark records, assign chip names, and sync the bench_chips table.
     * Ports: BenchUpdateChips()
     */
    public function updateChips(): void
    {
        $db = Yii::$app->db;

        // ------------------------------------------------------------------ //
        // Data quality cleanup

        $db->createCommand("UPDATE benchmarks SET device=TRIM(device) WHERE type='cpu'")->execute();
        $db->createCommand("UPDATE benchmarks SET power=NULL WHERE power<=3")->execute();
        $db->createCommand("UPDATE benchmarks SET plimit=NULL WHERE plimit=0")->execute();
        $db->createCommand("UPDATE benchmarks SET freq=NULL WHERE freq=0")->execute();
        $db->createCommand("UPDATE benchmarks SET memf=NULL WHERE memf=0")->execute();
        $db->createCommand("UPDATE benchmarks SET realmemf=NULL WHERE realmemf<=100")->execute();
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200")->execute();
        // Known bad driver versions (nvml 378.x / 381.x on Linux/Windows, fixed in 382.05)
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 378.%'")->execute();
        $db->createCommand("UPDATE benchmarks SET realfreq=NULL WHERE realfreq<=200 AND driver LIKE '% 381.%'")->execute();
        $db->createCommand("DELETE FROM benchmarks WHERE device LIKE '%<%' OR client LIKE '%<%'")->execute();

        // ------------------------------------------------------------------ //
        // Resolve chip names for records that have none

        $benchmarks = Benchmarks::find()->where(['or', ['chip' => null], ['chip' => '']])->all();

        foreach ($benchmarks as $bench) {
            if (empty($bench->vendorid) || empty($bench->device)) {
                continue;
            }

            // x16r is non-deterministic — results are inaccurate
            if ($bench->algo === 'x16r') {
                $bench->delete();
                continue;
            }

            // Block records containing script tags (XSS/injection attempt)
            if (str_contains(json_encode($bench->getAttributes()), 'script')) {
                Yii::warning("bench: deleted suspicious record id={$bench->id}", __CLASS__);
                $bench->delete();
                continue;
            }

            // Remove outlier duplicates (>10 identical records from same user)
            $dups = (int) $db->createCommand(
                "SELECT COUNT(id) FROM benchmarks
                 WHERE vendorid=:vid AND client=:client AND os=:os AND driver=:drv
                   AND throughput=:thr AND userid=:uid",
                [
                    ':vid' => $bench->vendorid, ':client' => $bench->client,
                    ':os'  => $bench->os,       ':drv'    => $bench->driver,
                    ':thr' => $bench->throughput, ':uid'  => $bench->userid,
                ]
            )->queryScalar();

            if ($dups > 10 || round((float) $bench->khps, 3) == 0) {
                $bench->delete();
                continue;
            }

            // Resolve chip name via legacy bench module function
            $chip = null;
            if (function_exists('getChipName')) {
                $chip = getChipName($bench->getAttributes());
            } else {
                Yii::warning('BenchService: getChipName() not available — skipping chip resolution (pending bench module port)', __CLASS__);
                break; // no point continuing without the function
            }

            if (empty($chip) || $chip === '-') {
                continue;
            }

            $bench->chip = $chip;

            // Remove records whose hashrate is implausibly low vs the algo average
            $rates = $db->createCommand(
                "SELECT AVG(khps) AS avg, COUNT(id) AS cnt FROM benchmarks WHERE algo=:algo AND chip=:chip",
                [':algo' => $bench->algo, ':chip' => $chip]
            )->queryOne();

            $avg = (float) ($rates['avg'] ?? 0);
            $cnt = (int)   ($rates['cnt'] ?? 0);

            if ($cnt > 250) {
                $bench->delete();
                continue;
            }

            if ($cnt > 5 && (float) $bench->khps < $avg / 2) {
                Yii::info("bench: {$bench->device} ignored — bad {$bench->algo} rate {$bench->khps} kHs", __CLASS__);
                $bench->delete();
                continue;
            }

            if ($bench->chip === 'GPU' || $bench->chip === 'Graphics Device') {
                $bench->delete();
                continue;
            }

            Yii::info("bench: {$bench->device} ({$chip})", __CLASS__);
            $bench->save();
        }

        // ------------------------------------------------------------------ //
        // Add new chip records for chips not yet in bench_chips

        $newChips = $db->createCommand(
            "SELECT DISTINCT B.chip, B.type FROM benchmarks B
             WHERE B.chip NOT IN (
                 SELECT DISTINCT C.chip FROM bench_chips C WHERE C.devicetype = B.type
             )"
        )->queryAll();

        foreach ($newChips as $row) {
            if (empty($row['chip']) || empty($row['type'])) {
                continue;
            }
            $chipRecord             = new BenchChips();
            $chipRecord->chip       = $row['chip'];
            $chipRecord->devicetype = $row['type'];

            if ($chipRecord->save()) {
                Yii::info("bench: added {$chipRecord->devicetype} chip {$chipRecord->chip}", __CLASS__);
                $db->createCommand(
                    "UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type",
                    [':id' => $chipRecord->id, ':chip' => $row['chip'], ':type' => $row['type']]
                )->execute();
            }
        }

        // ------------------------------------------------------------------ //
        // Back-fill idchip for benchmarks that have a chip name but no idchip

        $unlinked = $db->createCommand(
            "SELECT DISTINCT chip, type FROM benchmarks WHERE idchip IS NULL AND chip IS NOT NULL AND chip != ''"
        )->queryAll();

        foreach ($unlinked as $row) {
            $chipRecord = BenchChips::find()
                ->where(['chip' => $row['chip'], 'devicetype' => $row['type']])
                ->one();
            if (!$chipRecord) {
                continue;
            }
            $db->createCommand(
                "UPDATE benchmarks SET idchip=:id WHERE chip=:chip AND type=:type",
                [':id' => $chipRecord->id, ':chip' => $row['chip'], ':type' => $row['type']]
            )->execute();
        }
    }
}
