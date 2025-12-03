<?php
class Assessment extends BaseModel {
    protected array $questions = [];

    public function __construct() {
    }

    public function getQuestions(): array { return $this->questions; }

    /**
     * Evaluate answers (array index => 1..5)
     * Returns array with track_scores and track_counts
     */
    public function evaluate(array $answers): array {
        $scores = [];
        $counts = [];
        foreach($this->questions as $i => $q){
            $k = $q['key'];
            if(!isset($scores[$k])) { $scores[$k] = 0; $counts[$k] = 0; }
            $val = isset($answers[$i]) ? intval($answers[$i]) : 0;
            $scores[$k] += $val;
            $counts[$k] += 1;
        }
        return ['track_scores'=>$scores,'track_counts'=>$counts];
    }

    protected function safePercent(int $score, int $count): float {
        if($count === 0) return 0.0;
        return ($score / ($count * 5)) * 100.0;
        return min($percent, 100.0);
    }
}
