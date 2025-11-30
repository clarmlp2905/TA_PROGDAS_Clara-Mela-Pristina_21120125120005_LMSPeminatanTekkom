<?php
class User extends BaseModel {
    private string $id;
    private string $name;
    private string $phone;
    private string $email;
    private string $authType; // 'email'|'sso'
    private ?string $selectedTrack = null;
    private array $assessmentResults = [];
    private array $history = [];

    public function __construct(string $name, string $phone, string $email, string $authType = 'email') {
        $this->id = uniqid('u_');
        $this->setName($name);
        $this->setPhone($phone);
        $this->setEmail($email);
        $this->authType = $authType;
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getPhone(): string { return $this->phone; }
    public function getEmail(): string { return $this->email; }
    public function getAuthType(): string { return $this->authType; }
    public function getSelectedTrack(): ?string { return $this->selectedTrack; }
    public function getAssessmentResults(): array { return $this->assessmentResults; }
    public function getHistory(): array { return $this->history; }

    // Setters (with light sanitization)
    public function setName(string $v): void { $this->name = htmlspecialchars(trim($v)); }
    public function setPhone(string $v): void { $this->phone = htmlspecialchars(trim($v)); }
    public function setEmail(string $v): void { $this->email = htmlspecialchars(trim($v)); }
    public function setSelectedTrack(?string $t): void { $this->selectedTrack = $t; }
    public function setAssessmentResults(array $r): void { $this->assessmentResults = $r; }
    public function addHistory(array $entry): void { $this->history[] = $entry; }

    // Session operations
    public function saveToSession(): void {
        $_SESSION['user'] = $this;
    }

    public static function fromSession(): ?User {
        return isset($_SESSION['user']) && $_SESSION['user'] instanceof User ? $_SESSION['user'] : null;
    }
}
