<?php
class PsychAssessment extends Assessment {
    protected array $psychQuestions = [];

    // Mapping final: trait -> track -> impact weight
    private array $traitImpact = [
        'detail' => [
            'software' => 1,
            'hardware' => 1,
            'network' => 1
        ],
        'creative' => [
            'multimedia' => 1,
            'software' => 0.5
        ],
        'risk' => [
            'hardware' => 1,
            'network' => 1
        ]
    ];

    public function __construct() {
        // Core questions (minat)
        $this->questions = [
            ['q'=>'Saya menikmati menulis software dan memecahkan masalah algoritma.', 'key'=>'software'],
            ['q'=>'Saya suka merancang rangkaian dan bekerja dengan mikrokontroler.', 'key'=>'hardware'],
            ['q'=>'Saya tertarik dengan jaringan, server, dan routing.', 'key'=>'network'],
            ['q'=>'Saya senang membuat grafik, animasi, dan konten multimedia.', 'key'=>'multimedia'],
            ['q'=>'Saya suka melakukan debugging sistem yang kompleks dan mengoptimalkan performa.', 'key'=>'software'],
            ['q'=>'Saya suka menyolder dan membangun prototipe secara langsung.', 'key'=>'hardware'],
            ['q'=>'Saya suka mengonfigurasi router, switch, dan alat jaringan lainnya.', 'key'=>'network'],
            ['q'=>'Saya suka mengedit video, suara, dan media interaktif.', 'key'=>'multimedia']
        ];

        // Psychology questions
        $this->psychQuestions = [
            ['q'=>'Saya lebih suka tugas yang terstruktur dengan aturan yang jelas.', 'trait'=>'detail'],
            ['q'=>'Saya suka mencoba ide-ide baru yang berisiko.', 'trait'=>'risk'],
            ['q'=>'Saya sering memikirkan estetika dan pengalaman pengguna.', 'trait'=>'creative']
        ];
    }

    public function getPsychQuestions(): array {
        return $this->psychQuestions;
    }

    public function fullEvaluate(array $answers, array $psychAnswers): array {
        $baseEval = parent::evaluate($answers);
        $psych = [];

        foreach($this->psychQuestions as $i => $pq){
            $trait = $pq['trait'];
            $psych[$trait] = isset($psychAnswers[$i]) ? intval($psychAnswers[$i]) : 0;
        }

        return array_merge($baseEval, ['psych' => $psych]);
    }

    public function recommend(array $eval): array {

        // --- BASE SCORE ---
        $raw = $eval['track_scores'];
        $counts = $eval['track_counts'];
        $finalScore = [];

        foreach($raw as $track => $val){
            $finalScore[$track] = ($val / ($counts[$track] * 5)) * 100;
        }

        // --- PSYCHOLOGY BONUS ---
        $psych = $eval['psych'] ?? [];

        foreach ($psych as $trait => $val) {

             /** Penilaian psikolog test dari tidak setuju s.d sangat setuju
             * 5 → +1
             *4 → +0.5
             *3 → 0
             *2 → -0.5
             *1 → -1
             */
            $norm = ($val - 3) / 2;

            // Special case: risk (dibalik)
            if ($trait === 'risk') {
                $norm *= -1;
            }

            // Apply bonus 
            if (isset($this->traitImpact[$trait])) {
                foreach ($this->traitImpact[$trait] as $track => $impactWeight) {

                    $bonus = $norm * $impactWeight * 5;
                    $finalScore[$track] += $bonus;
                }
            }
        }

        // --- Membatasi skor 100% ---
        foreach ($finalScore as $track => $val) {
            $finalScore[$track] = max(0, min(100, $val));
        }

        // Urutkan hasil dari yang skornya terbessar ke terendah
        arsort($finalScore);

        // Primary
        $primary = key($finalScore);
        $primaryScore = round($finalScore[$primary], 2);

        // Secondary
        $scores = array_values($finalScore);
        $keys = array_keys($finalScore);

        $secondary = $keys[1] ?? null;
        $secScore = isset($scores[1]) ? round($scores[1], 2) : 0;

        return [
            'primary' => $primary,
            'score' => $primaryScore,
            'secondary' => $secondary,
            'secondary_score' => $secScore,
            'raw_scores' => $finalScore,
            'reason' => $this->buildReason($primary, $primaryScore, $eval)
        ];
    }

    private function buildReason(string $track, float $score, array $eval): string {
        $base = "Berdasarkan jawaban Anda, skor tertinggi berada pada \"$track\" dengan persentase $score%. ";
        $explain = "Ini mencerminkan kecenderungan minat dan gaya kerja Anda. ";

        if($track === 'software') $explain .= "Anda menunjukkan ketertarikan pada problem-solving dan logika pemrograman. ";
        if($track === 'hardware') $explain .= "Anda memiliki minat pada rangkaian, perangkat fisik, dan eksperimen. ";
        if($track === 'network') $explain .= "Anda memiliki ketertarikan pada infrastruktur jaringan dan konfigurasi sistem. ";
        if($track === 'multimedia') $explain .= "Anda menunjukkan preferensi pada kreativitas, estetika, dan konten visual/audio. ";

        $p = $eval['psych'] ?? [];
        $parts = [];
        foreach($p as $trait=>$val){
            $parts[] = "$trait=$val";
        }

        $explain .= "Hasil psikologi: " . implode(', ', $parts) . ". ";

        return $base . $explain;
    }
}
