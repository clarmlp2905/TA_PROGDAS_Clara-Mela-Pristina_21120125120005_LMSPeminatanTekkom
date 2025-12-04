<?php
class ProgressManager {
    public static function addAssessment(array $entry): void {
        $_SESSION['progress']['assessments'][] = $entry;
    }

    public static function addMaterialProgress(array $entry): void {
        $_SESSION['progress']['materials'][] = $entry;
    }

    /**
     * Compute pass report per track, using latest assessment and materials progress.
     * Pass defined as combined% >= 70
     */
    public static function computePassReport(): array {
        $tracks = ['software','hardware','network','multimedia'];
        $assessments = $_SESSION['progress']['assessments'] ?? [];
        $materials = $_SESSION['progress']['materials'] ?? [];

        $latestEval = !empty($assessments) ? end($assessments)['eval'] : null;
        $result = [];

        foreach($tracks as $t){
            $assPerc = 0.0;
            if($latestEval){
                $scores = $latestEval['track_scores'] ?? [];
                $counts = $latestEval['track_counts'] ?? [];
                if(isset($scores[$t]) && $counts[$t]){
                    $assPerc = ($scores[$t] / ($counts[$t] * 5)) * 100.0;
                }
                // approximate psych boosts
                $psych = $latestEval['psych'] ?? [];
                if(($psych['creative'] ?? 0) >= 4 && $t === 'multimedia') $assPerc += 5;
                if(($psych['detail'] ?? 0) >= 4 && $t === 'hardware') $assPerc += 3;
                if(($psych['detail'] ?? 0) >= 4 && $t === 'network') $assPerc += 2;
                if(($psych['risk'] ?? 10) <= 2 && ($t === 'network' || $t === 'hardware')) $assPerc += 2;

                // 🔒 Batasi maksimal 100
                $assPerc = min($assPerc, 100.0);
            }

            $totalScore = 0; $countChallenge = 0;
            foreach($materials as $m){
                if(($m['track'] ?? '') === $t){
                    $countChallenge++;
                    $totalScore += ($m['correct'] ? 10 : 0);
                }
            }
            $possible = $countChallenge * 10;
            $challengePerc = $possible ? ($totalScore / $possible) * 100.0 : 0.0;
            $challengePerc = min($challengePerc, 100.0);
            $combined = $latestEval ? (($assPerc + $challengePerc) / 2.0) : $challengePerc;
            $combined = min($combined, 100.0);
            $pass = $combined >= 80.0;
            $result[$t] = [
                'assessment' => round($assPerc, 2),
                'challenge' => round($challengePerc, 2),
                'combined' => round($combined, 2),
                'pass' => $pass
            ];
        }
        return $result;
    }
}
