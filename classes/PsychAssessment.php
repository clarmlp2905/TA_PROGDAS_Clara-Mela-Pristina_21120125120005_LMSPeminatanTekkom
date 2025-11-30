<?php
class PsychAssessment extends Assessment {
    protected array $psychQuestions = [];

    public function __construct() {
        // populate both core questions and psych questions
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

        $this->psychQuestions = [
            ['q'=>'Saya lebih suka tugas yang terstruktur dengan aturan yang jelas.', 'trait'=>'detail'],
            ['q'=>'Saya suka mencoba ide-ide baru yang berisiko.', 'trait'=>'risk'],
            ['q'=>'Saya sering memikirkan estetika dan pengalaman pengguna.', 'trait'=>'creative']
        ];
    }

    public function getPsychQuestions(): array { return $this->psychQuestions; }
    public function fullEvaluate(array $answers, array $psychAnswers): array {
        $baseEval = parent::evaluate($answers);
        $psych = [];
        foreach($this->psychQuestions as $i => $pq){
            $trait = $pq['trait'];
            $psych[$trait] = isset($psychAnswers[$i]) ? intval($psychAnswers[$i]) : 0;
        }
        return array_merge($baseEval, ['psych'=>$psych]);
    }

    /**
     * Recommend based on evaluation result (encapsulated here)
     */
    public function recommend(array $eval): array {
        $raw = $eval['track_scores'];
        $counts = $eval['track_counts'];
        $avg = [];
        foreach($raw as $k=>$v){
            $avg[$k] = isset($counts[$k]) && $counts[$k] ? ($v / ($counts[$k]*5) * 100) : 0;
        }

        // psych adjustments
        $psych = $eval['psych'] ?? [];
        if(isset($psych['creative']) && $psych['creative'] >= 4){
            $avg['multimedia'] += 5;
        }
        if(isset($psych['detail']) && $psych['detail'] >= 4){
            $avg['hardware'] += 3;
            $avg['network'] += 2;
        }
        if(isset($psych['risk']) && $psych['risk'] <= 2){
            $avg['network'] += 2;
            $avg['hardware'] += 2;
        }

        arsort($avg);
        $primary = key($avg);
        $primary_score = round(current($avg), 2);

        // find secondary
        $secondary = null; $secondary_score = 0; $i = 0;
        foreach($avg as $k=>$v){
            if($i === 0) { $i++; continue; }
            $secondary = $k; $secondary_score = round($v, 2); break;
        }

        $reason = $this->buildReason($primary, $primary_score, $eval);

        return [
            'primary' => $primary,
            'score' => $primary_score,
            'secondary' => $secondary,
            'secondary_score' => $secondary_score,
            'reason' => $reason,
            'raw_scores' => $avg
        ];
    }

    private function buildReason(string $track, float $score, array $eval): string {
        $base = "Berdasarkan jawaban Anda, skor tertinggi berada pada \"$track\" dengan persentase $score%. ";
        $explain = "Ini menunjukkan kecenderungan minat dan kemampuan di area tersebut. ";
        if($track === 'software') $explain .= "Anda menyukai pemecahan masalah, logika, dan pengembangan aplikasi. ";
        if($track === 'hardware') $explain .= "Anda menyukai eksperimen fisik, perancangan rangkaian, dan implementasi embedded. ";
        if($track === 'network') $explain .= "Anda tertarik pada infrastruktur, konektivitas, dan aspek keamanan/ops jaringan. ";
        if($track === 'multimedia') $explain .= "Anda memiliki rasa estetika, kreativitas, dan ketertarikan pada konten visual & audio. ";

        $p = $eval['psych'] ?? [];
        $explain .= "Selain itu, hasil psikologi menunjukkan: ";
        $parts = [];
        foreach($p as $trait=>$val){
            $parts[] = "$trait=$val";
        }
        $explain .= implode(', ', $parts) . ". ";
        $explain .= "Saran: pertimbangkan mencoba mata kuliah dan materi yang direkomendasikan untuk memastikan kecocokan.";
        return $base . $explain;
    }
}
