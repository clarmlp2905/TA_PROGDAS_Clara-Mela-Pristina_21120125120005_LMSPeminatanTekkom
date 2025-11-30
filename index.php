<?php
require_once __DIR__ . '/classes/BaseModel.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Assessment.php';
require_once __DIR__ . '/classes/PsychAssessment.php';
require_once __DIR__ . '/classes/SessionManager.php';
require_once __DIR__ . '/classes/ProgressManager.php';


session_start();

function route(): string
{
  return $_GET['page'] ?? 'home';
}

function ensure_user(): void
{
  if (!SessionManager::getUser()) {
    header('Location: ?page=login');
    exit;
  }
}

function render_header(string $title = "LMS Peminatan Tekkom"): void
{
  $user = SessionManager::getUser();
  echo "<!doctype html>\n<html lang=\"id\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n<title>" . htmlspecialchars($title) . "</title>\n<link href=\"https://cdn.jsdelivr.net/npm/chart.js\" rel=\"nofollow\">\n<link rel='stylesheet' href='style.css'>\n</head><body>\n<div class=\"container\">\n<div class=\"header\">\n<div class=\"brand\"><div class=\"logo\">TK</div><div><div style=\"font-weight:800;font-size:1.1rem\">LMS Peminatan Tekkom</div><div class=\"small hide-sm\">Platform bantu pilih peminatan & belajar interaktif</div></div></div>\n<div>";
  if ($user) {
  echo "<div class='user-info-box'>
        <div class='user-info-name'>
            Halo, " . htmlspecialchars($user->getName()) . "
        </div>
        <div class='user-line'>
            <span>" . htmlspecialchars($user->getEmail()) . "</span>
            <a href='?page=logout' class='logout-btn'>🔒 Logout</a>
        </div>
    </div>
    ";
  } else {
    echo "<a href='?page=login' class=\"btn btn-primary\">Masuk / Daftar</a>";
  }
  echo "</div></div>\n";
}

function render_footer(): void
{
  echo "<div class=\"footer\">© " . date('Y') . " LMS Peminatan Tekkom — Designed by Clara Mela Pristina 💻</div></div></body></html>";
}

// -------------------- App bootstrap --------------------
SessionManager::initDefaults();
$assessment = new PsychAssessment(); // use polymorphic class

// -------------------- Router actions --------------------
$page = route();

// logout
if ($page === 'logout') {
  session_destroy();
  header('Location: ?');
  exit;
}

// Login / register
if ($page === 'login') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['auth_method'] ?? 'email';
    $name = $_POST['name'] ?? 'Mahasiswa';
    $phone = $_POST['phone'] ?? '-';
    $email = $_POST['email'] ?? ($method === 'sso' ? 'sso_user@kampus.edu' : 'noemail@local');
    $user = new User($name, $phone, $email, $method);
    SessionManager::setUser($user);
    // re-init progress and locks
    $_SESSION['progress'] = ['assessments' => [], 'materials' => []];
    $_SESSION['locked'] = ['assessment' => false, 'challenge_track' => null];
    header('Location: ?page=dashboard');
    exit;
  }

  render_header('Daftar / Masuk — LMS Tekkom');
?>
  <div class="card">
    <h2>Buat akun baru</h2>
    <p class="small">Daftar cepat menggunakan email pribadi atau SSO kampus (simulasi).</p>
    <form method="post">
      <div class="form-row">
        <input name="name" placeholder="Nama lengkap" class="input" required />
        <input name="phone" placeholder="No. HP" class="input" required />
      </div>
      <div class="form-row">
        <input name="email" placeholder="Email (jika email)" class="input" />
        <select name="auth_method" class="input">
          <option value="email">Daftar via Email</option>
          <option value="sso">SSO Kampus (simulasi)</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-primary">Daftar / Masuk</button>
        <a class="btn btn-ghost" href="?">Kembali</a>
      </div>
    </form>
  </div>
<?php
  render_footer();
  exit;
}

// Dashboard
if ($page === 'dashboard') {
  ensure_user();
  $user = SessionManager::getUser();
  render_header('Dashboard — LMS Tekkom');
?>
  <div class="grid">
    <div>
      <div class="card">
        <h3>Selamat datang, <?php echo htmlspecialchars($user->getName()); ?> 👋</h3>
        <p class="small">Pilih aksi di bawah ini untuk mulai menjelajah.</p>
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php
          $assLocked = $_SESSION['locked']['assessment'];
          if ($assLocked) {
            echo "<a class='btn btn-modern locked' href='javascript:;'>Assessment (Sudah dikerjakan)</a>";
          } else {
            echo "<a class='btn btn-modern' href='?page=assessment'>Mulai Self-Assessment</a>";
          }
          ?>
          <a class="btn" href="?page=materials">Materi & Challenge</a>
          <a class="btn" href="?page=history">Riwayat & Progress</a>
        </div>
      </div>

      <div class="card">
        <h4>Rekomendasi singkat</h4>
        <?php
        if (!empty($_SESSION['progress']['assessments'])) {
          $last = end($_SESSION['progress']['assessments']);
          $rec = $assessment->recommend($last['eval']);
          echo "<div class='track-card'><strong>Rekomendasi:</strong> " . ucfirst($rec['primary']) . " (Skor: {$rec['score']}%)<br><div class='small'>" . htmlspecialchars($rec['reason']) . "</div></div>";
        } else {
          echo "
          <div class='assessment-box'>
          <div class='small'>‼️Anda belum melakukan assessment‼️</div>
          <a href='?page=assessment' class='btn btn-modern full'>Mulai sekarang</a></div>";
        }
        ?>
      </div>

      <div class="card">
        <h4>Tracks Peminatan</h4>
        <div class="track-list">
          <?php
          
          // Label
          $tracks = [
            'software'   => 'Pengembangan Perangkat Lunak',
            'hardware'   => 'Hardware & Embedded',
            'network'    => 'Jaringan & Infrastruktur',
            'multimedia' => 'Multimedia & Desain'
          ];

          // Deskripsi lengkap
          $descriptions = [
            'software' => "Peminatan yang berfokus pada pembuatan aplikasi berbasis web, mobile, maupun desktop. Mahasiswa mempelajari konsep pemrograman, pengembangan sistem, manajemen basis data, serta praktik DevOps.",
            'hardware' => "Bidang yang mempelajari perancangan perangkat keras, mikrokontroler, dan embedded system. Mahasiswa memprogram Arduino/ESP32 dan merancang sistem otomatisasi.",
            'network' => "Peminatan yang mendalami desain, konfigurasi, keamanan jaringan, server, cloud, dan infrastruktur TI agar sistem stabil dan aman.",
            'multimedia' => "Bidang yang berfokus pada desain grafis, animasi, video editing, UI/UX, dan multimedia interaktif."
          ];

          // Icon aesthetic untuk tiap track (emoji aman & universal)
          $icons = [
            'software'   => '💻',
            'hardware'   => '🔧',
            'network'    => '🌐',
            'multimedia' => '🎨'
          ];

          // Warna pastel tiap track
          $colors = [
            'software'   => '#ffd6e0',
            'hardware'   => '#d0f0ea',
            'network'    => '#d9eaff',
            'multimedia' => '#e4d9ff'
          ];

          // Loop
          foreach ($tracks as $k => $label) {
            $desc  = $descriptions[$k];
            $icon  = $icons[$k];
            $color = $colors[$k];

            echo "
            <div class='track track-$k'>
              <div class='track-icon' style='background:$color'>$icon</div>
              <strong>$label</strong>
              <div class='small track-desc'>$desc</div>
              <a href='?page=materials&track=$k' class='btn btn-modern full track-btn'>Lihat materi</a>
            </div>";
          }
          ?>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <h4>Ringkasan</h4>
        <div class="small">Info singkat aktivitas Anda.</div>
        <ul style="margin-top:10px;">
          <li>Assessment selesai: <?php echo count($_SESSION['progress']['assessments']); ?></li>
          <li>Materi dibuka: <?php echo count($_SESSION['progress']['materials']); ?></li>
        </ul>
      </div>

      <div class="card">
        <h4 style="margin-top: 1px;margin-bottom: 5px;">Shortcut</h4>
        <?php if (!$_SESSION['locked']['assessment']) echo "<a class='btn btn-modern' href='?page=assessment'>Mulai Assessment</a>"; ?>
        <a class="btn btn-modern full " href="?page=materials">Materi</a>
      </div>

<!-- CARD FOTO -->
<div class="card-about">
    <h4>About Tekkom Undip 💻</h4>

    <p class="about-text">
        Program Studi Teknik Komputer Universitas Diponegoro (Undip) resmi dibuka tanggal
        <strong>22 Agustus 2008</strong> untuk menjawab kebutuhan industri terhadap tenaga ahli
        di bidang sistem komputer, arsitektur perangkat keras, jaringan, dan komputasi cerdas.
        Prodi ini awalnya berada di bawah naungan Fakultas Teknik Undip dan mulai menerima
        mahasiswa baru pada awal dekade 2010-an melalui jalur seleksi nasional dan mandiri.
        Sejak berdirinya, Teknik Komputer Undip berfokus pada pengembangan kurikulum yang
        menggabungkan aspek perangkat keras (hardware) dan perangkat lunak (software),
        sehingga menghasilkan lulusan yang kompeten dalam perancangan sistem tertanam,
        jaringan komputer, hingga komputasi berbasis kecerdasan buatan.
    </p>

    <div class="about-img-wrapper">
        <img src="assets/img/hai.jpg" class="about-img" onclick="openImage(this.src)">
    </div>
</div>

<script>
  function openImage(src) {
      document.getElementById("popup-img").src = src;
      document.getElementById("img-popup").style.display = "flex";
  }

  function closeImage() {
      document.getElementById("img-popup").style.display = "none";
  }
</script>

<!-- POPUP IMAGE VIEWER -->
<div id="img-popup" class="img-popup" onclick="closeImage()">
    <img id="popup-img">
</div>

    </div>
  </div>
<?php
  render_footer();
  exit;
}

// Assessment page (prevent re-do if locked)
if ($page === 'assessment') {
  ensure_user();
  $user = SessionManager::getUser();
  render_header('Assessment — LMS Tekkom');

  if ($_SESSION['locked']['assessment']) {
    echo "<div class='card'><h2>Assessment</h2><div class='small'>Anda sudah mengerjakan assessment dan tidak diperbolehkan mengulang.</div><div style='margin-top:8px;'><a class='btn' href='?page=dashboard'>Kembali</a></div></div>";
    render_footer();
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    $answers = $_POST['q'] ?? [];
    $psy = $_POST['p'] ?? [];
    $eval = $assessment->fullEvaluate($answers, $psy);
    $rec = $assessment->recommend($eval);
    $entry = ['timestamp' => time(), 'eval' => $eval, 'rec' => $rec];
    ProgressManager::addAssessment($entry);
    // store selected recommended track automatically
    $user->setSelectedTrack($rec['primary']);
    $user->setAssessmentResults($eval);
    $user->saveToSession();
    $_SESSION['locked']['assessment'] = true;
    header('Location: ?page=result');
    exit;
  }

?>
  <div class="card">
    <h2>Self-Assessment & Psikologi Singkat</h2>
    <p class="small">Jawab soal berikut dengan jujur (1 = Sangat tidak setuju, 5 = Sangat setuju)</p>
    <form method="post">
      <?php
      foreach ($assessment->getQuestions() as $i => $q) {
        echo "<div class='card' style='margin-bottom:10px;'><strong>" . ($i + 1) . ". {$q['q']}</strong><div style=\"margin-top:8px;\">";
        for ($s = 1; $s <= 5; $s++) {
          echo "<label style='margin-right:8px;'><input type=\"radio\" name=\"q[$i]\" value=\"$s\" required> $s</label>";
        }
        echo "</div></div>";
      }
      echo "<h3>Psikologi singkat</h3>";
      foreach ($assessment->getPsychQuestions() as $i => $pq) {
        echo "<div class='card' style='margin-bottom:10px;'><strong>" . ($i + 1) . ". {$pq['q']}</strong><div style=\"margin-top:8px;\">";
        for ($s = 1; $s <= 5; $s++) {
          echo "<label style='margin-right:8px;'><input type=\"radio\" name=\"p[$i]\" value=\"$s\" required> $s</label>";
        }
        echo "</div></div>";
      }
      ?>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-primary">Submit Assessment</button>
        <a class="btn btn-ghost" href="?page=dashboard">Batal</a>
      </div>
    </form>
  </div>
<?php
  render_footer();
  exit;
}

// Result page (with export)
if ($page === 'result') {
  ensure_user();
  render_header('Hasil Assessment — LMS Tekkom');
  $last = end($_SESSION['progress']['assessments']) ?? null;
  if (!$last) {
    header('Location: ?page=dashboard');
    exit;
  }
  $rec = $last['rec'];
//   echo "<pre>LAST DATA: ";
// var_dump($last);
// echo "</pre>";
?>
  <div class="card">
    <h2>Hasil Assessment</h2>
    <p class="small">Rekomendasi utama: <strong><?php echo ucfirst(htmlspecialchars($rec['primary'])); ?></strong> (Skor <?php echo htmlspecialchars($rec['score']); ?>%)</p>
    <div class="small"><?php echo htmlspecialchars($rec['reason']); ?></div>

    <h3 style="margin-top:14px;">Skor per track</h3>
    <canvas id="chartScores" width="400" height="200"></canvas>
  </div>

  <div class="card">
    <h3>Aksi selanjutnya</h3>
    <a class="btn btn-primary" href="?page=materials&track=<?php echo htmlspecialchars($rec['primary']); ?>">Lihat Materi</a>
    <a class="btn" href="?page=dashboard">Kembali</a>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx = document.getElementById('chartScores').getContext('2d');
    const data = {
      labels: <?php echo json_encode(array_keys($rec['raw_scores'])); ?>,
      datasets: [{
        label: 'Skor (%)',
        data: <?php echo json_encode(array_values($rec['raw_scores'])); ?>,
        fill: true,
        tension: 0.4,
        borderWidth: 1
      }]
    };
    new Chart(ctx, {
      type: 'radar',
      data: data,
      options: {
        scales: {
          r: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });
  </script>
<?php
  render_footer();
  exit;
}

  // MATERIALS PAGE
  // ===============================
  if ($page === 'materials') {
    ensure_user();
    $user = SessionManager::getUser();
    $track = $_GET['track'] ?? ($user->getSelectedTrack() ?? 'software');

    render_header('Materi — LMS Tekkom');

    // ===========================
    // LIST MATERI PER TRACK
    // ===========================
    $materials = [
      'software' => [
          'materi' => [
              ['title' => 'Fundamental Programming', 'content' => 'Variabel, kondisi, loop, function, OOP.'],
              ['title' => 'Frontend Web', 'content' => 'HTML, CSS, JavaScript.'],
              ['title' => 'Backend Development', 'content' => 'Python & Java dasar.']
          ],
          'references' => [
              ['name' => 'Petani Kode', 'url' => 'https://www.petanikode.com/tutorial/'],
              ['name' => 'FreeCodeCamp', 'url' => 'https://www.freecodecamp.org/'],
              ['name' => 'W3Schools', 'url' => 'https://www.w3schools.com/']
          ],
          'challenge' => 'Challenge ini bertujuan untuk mengetahui seberapa pemahaman Anda terkait materi bidang Software.'
      ],

      'hardware' => [
          'materi' => [
              ['title' => 'Dasar Elektronika', 'content' => 'Resistor, kapasitor, transistor, rangkaian.'],
              ['title' => 'Arduino', 'content' => 'Sensor, aktuator, I/O dan serial.']
          ],
          'references' => [
              ['name' => 'Belajar Arduino', 'url' => 'https://www.arduino.cc/en/Guide'],
              ['name' => 'Elektronika Dasar', 'url' => 'https://www.electronics-tutorials.ws/'],
              ['name' => 'Wokwi', 'url' => 'https://wokwi.com/']
          ],
          'challenge' => 'Challenge ini bertujuan untuk mengetahui seberapa pemahaman Anda terkait materi bidang Software.'
      ],

      'network' => [
          'materi' => [
              ['title' => 'Konsep Jaringan', 'content' => 'OSI Layer, IP, subnetting.'],
              ['title' => 'Firewall & Security', 'content' => 'Keamanan jaringan dasar.']
          ],
          'references' => [
              ['name' => 'Cisco Academy', 'url' => 'https://www.netacad.com/'],
              ['name' => 'Subnetting Tutorial', 'url' => 'https://www.subnettingpractice.com/'],
              ['name' => 'Network Security', 'url' => 'https://www.geeksforgeeks.org/network-security/']
          ],
          'challenge' => 'Challenge ini bertujuan untuk mengetahui seberapa pemahaman Anda terkait materi bidang Software.'
      ],

      'multimedia' => [
          'materi' => [
              ['title' => 'Dasar Desain', 'content' => 'Warna, tipografi, layout.'],
              ['title' => 'Editing Video', 'content' => 'Basic cut, audio, storytelling.']
          ],
          'references' => [
              ['name' => 'Canva Design', 'url' => 'https://www.canva.com/learn/'],
              ['name' => 'Adobe Premiere Basics', 'url' => 'https://helpx.adobe.com/premiere-pro/tutorials.html'],
              ['name' => 'Pixlr', 'url' => 'https://pixlr.com/']
          ],
          'challenge' => 'CChallenge ini bertujuan untuk mengetahui seberapa pemahaman Anda terkait materi bidang Software.'
      ]
    ];

    // pilih track
    $selected = $materials[$track] ?? $materials['software'];

    // untuk penguncian challenge
    $lockedChallengeTrack = $_SESSION['locked']['challenge_track'] ?? null;
  ?>

  <!-- ============================= -->
  <!--   AESTHETIC VIEW (Materi)     -->
  <!-- ============================= -->

  <div class="materi-card">

    <div class="materi-header">
        <span class="emoji">📚</span>
        <h2>Materi <?= ucfirst(htmlspecialchars($track)) ?></h2>
    </div>

    <?php foreach ($selected['materi'] as $m) { ?>
        <div class="materi-box">
            <h3><?= htmlspecialchars($m['title']); ?></h3>
            <p><?= htmlspecialchars($m['content']); ?></p>
        </div>
    <?php } ?>

    <!-- Reference Bubble -->
    <div class="ref-container">
        <?php 
        $gradients = ['grad-1', 'grad-2', 'grad-3'];
        $i = 0;
        foreach ($selected['references'] as $ref) { ?>
            <a href="<?= $ref['url'] ?>" 
              target="_blank" 
              class="ref-bubble <?= $gradients[$i] ?>">
                <?= $ref['name'] ?>
            </a>
        <?php $i++; } ?>
    </div>
  </div>

<!-- ============================= -->
<!--        SECTION CHALLENGE      -->
<!-- ============================= -->

<?php
// Judul challenge
$challengeTitle = $selected['challenge'] ?? 'Challenge';

// Default
$disable = false;
$note = '';

// -------------------------------
// 1. CEK: Challenge terkunci oleh track lain
// -------------------------------
if (!empty($lockedChallengeTrack) && $lockedChallengeTrack !== $track) {
    $disable = true;
    $note = "Challenge dikunci untuk track: $lockedChallengeTrack";
}

// -------------------------------
// 2. CEK: Sudah mengerjakan challenge track ini?
// Data disimpan di $_SESSION['progress']['materials']
// -------------------------------
$materials = $_SESSION['progress']['materials'] ?? [];
$alreadyDone = false;
$doneScore = null;

foreach ($materials as $m) {
    // cek berdasarkan track & memastikan ada skor
    if ($m['track'] === $track && isset($m['score'])) {
        $alreadyDone = true;
        $doneScore = $m['score'];
        break;
    }
}

// Jika sudah mengerjakan → disable
if ($alreadyDone) {
    $disable = true;
    $note = "Challenge sudah dikerjakan (skor: $doneScore)";
}
?>

<div class="card" style="margin-bottom:10px;">
    <h2 class="materi-header" style="margin-top:26px;">🎯 Challenge</h2>

    <strong><?= htmlspecialchars($challengeTitle); ?></strong>

    <div style="margin-top:15px;">

        <?php if ($disable) { ?>

            <!-- TOMBOL LOCKED -->
            <a class="btn btn-modern full locked" href="javascript:;">
                <?= htmlspecialchars($note); ?>
            </a>
            <a class="btn btn-ghost" href="?page=dashboard">Kembali</a>

        <?php } else { ?>

            <!-- TOMBOL BUKA CHALLENGE -->
            <a class="btn btn-modern full"
               href="?page=challenge&track=<?= urlencode($track); ?>">
               Kerjakan Challenge
            </a>
            <a class="btn btn-ghost" href="?page=dashboard">Kembali</a>

        <?php } ?>

    </div>
</div>


<?php
  render_footer();
  exit;
}

// Challenge page
  $challengeQuestions = [

    // ========================
    // SOFTWARE DEVELOPMENT
    // ========================
    'software' => [
        [
            'q' => 'Apa output dari kode berikut? echo 2 + 3 * 4;',
            'options' => ['14', '20', '24', '18'],
            'correct' => 0
        ],
        [
            'q' => 'Struktur data yang LIFO adalah...',
            'options' => ['Queue', 'Stack', 'Array', 'Tree'],
            'correct' => 1
        ],
        [
            'q' => 'Bahasa pemrograman yang berjalan di browser adalah...',
            'options' => ['Java', 'JavaScript', 'Python', 'C#'],
            'correct' => 1
        ],
        [
            'q' => 'OOP singkatan dari...',
            'options' => ['Object-Oriented Programming', 'Open Operation Protocol', 'Online Object Processing', 'Operational Program Package'],
            'correct' => 0
        ],
        [
            'q' => 'Perintah untuk mencetak teks pada Python adalah...',
            'options' => ['echo()', 'println()', 'print()', 'write()'],
            'correct' => 2
        ],
        [
            'q' => 'Framework berikut digunakan untuk backend...',
            'options' => ['Laravel', 'React', 'Vue.js', 'Bootstrap'],
            'correct' => 0
        ],
        [
            'q' => 'Struktur data yang menyimpan pasangan key-value adalah...',
            'options' => ['List', 'Array', 'Dictionary / Map', 'Queue'],
            'correct' => 2
        ],
        [
            'q' => 'Operator logika AND di PHP adalah...',
            'options' => ['&&', '||', '!', '&'],
            'correct' => 0
        ],
        [
            'q' => 'Git digunakan untuk...',
            'options' => ['Desain UI', 'Version Control', 'Menjalankan server', 'Membuat database'],
            'correct' => 1
        ],
        [
            'q' => 'Perintah untuk membuat tabel di database adalah...',
            'options' => ['MAKE TABLE', 'CREATE TABLE', 'NEW TABLE', 'INSERT TABLE'],
            'correct' => 1
        ],
        [
            'q' => 'HTML adalah singkatan dari...',
            'options' => ['Hyperlink Markup List', 'HyperText Markup Language', 'HighText Machine Language', 'HyperTool Multi Language'],
            'correct' => 1
        ],
        [
            'q' => 'CSS digunakan untuk...',
            'options' => ['Mengatur tampilan', 'Mengolah data', 'Mengelola server', 'Membuat database'],
            'correct' => 0
        ],
        [
            'q' => 'React termasuk ke dalam...',
            'options' => ['Backend Framework', 'Database Engine', 'UI Library', 'Design System'],
            'correct' => 2
        ],
        [
            'q' => 'SQL digunakan untuk...',
            'options' => ['Mengatur jaringan', 'Menulis query database', 'Membuat animasi', 'Menjalankan server'],
            'correct' => 1
        ],
        [
            'q' => 'Tipe data boolean memiliki nilai...',
            'options' => ['0–9', 'True/False', 'A–Z', 'String saja'],
            'correct' => 1
        ],
    ],

    // ========================
    // HARDWARE & EMBEDDED
    // ========================
    'hardware' => [
        ['q' => 'Komponen yang berfungsi sebagai otak komputer adalah...', 'options' => ['RAM', 'SSD', 'CPU', 'PSU'], 'correct' => 2],
        ['q' => 'RAM digunakan untuk menyimpan...', 'options' => ['Data permanen', 'Instruksi sementara', 'Backup listrik', 'Suara'], 'correct' => 1],
        ['q' => 'Arduino merupakan platform...', 'options' => ['Software Editing', 'Microcontroller', 'Database Engine', 'Cloud Hosting'], 'correct' => 1],
        ['q' => 'Satuan kecepatan prosesor adalah...', 'options' => ['MHz / GHz', 'Mb/s', 'Rpm', 'Kbps'], 'correct' => 0],
        ['q' => 'Jenis port yang biasa digunakan untuk monitor adalah...', 'options' => ['HDMI', 'USB', 'RJ45', 'Audio Jack'], 'correct' => 0],
        ['q' => 'Fungsi heatsink adalah...', 'options' => ['Menyimpan data', 'Membaca suhu ruangan', 'Mendinginkan komponen', 'Menambah kecepatan CPU'], 'correct' => 2],
        ['q' => 'Motherboard berfungsi sebagai...', 'options' => ['Papan utama penghubung komponen', 'Penyimpanan utama', 'Output suara', 'Power supply'], 'correct' => 0],
        ['q' => 'Sensor ultrasonik digunakan untuk mendeteksi...', 'options' => ['Cahaya', 'Jarak', 'Suhu', 'Suara'], 'correct' => 1],
        ['q' => 'EEPROM digunakan untuk...', 'options' => ['Data sementara', 'Data permanen kecil', 'Sistem pendingin', 'Suara'], 'correct' => 1],
        ['q' => 'Jenis memori tercepat adalah...', 'options' => ['SSD', 'HDD', 'Cache', 'Floppy'], 'correct' => 2],
        ['q' => 'GPIO pada mikrokontroler berfungsi sebagai...', 'options' => ['Pin input/output', 'Kartu grafis', 'Penyimpanan data', 'Controller suara'], 'correct' => 0],
        ['q' => 'Alat untuk mengukur arus listrik adalah...', 'options' => ['Amperemeter', 'Voltmeter', 'Ohmmeter', 'Luxmeter'], 'correct' => 0],
        ['q' => 'Board Raspberry Pi menggunakan prosesor...', 'options' => ['ARM', 'x86', 'PowerPC', 'Zilog'], 'correct' => 0],
        ['q' => 'Thermal paste digunakan untuk...', 'options' => ['Membuat kabel', 'Meningkatkan transfer panas', 'Membersihkan CPU', 'Melapisi SSD'], 'correct' => 1],
        ['q' => 'PSU digunakan untuk...', 'options' => ['Memberikan daya', 'Mengolah grafis', 'Menghubungkan jaringan', 'Mengatur suhu'], 'correct' => 0],
    ],

    // ========================
    // NETWORKING
    // ========================
    'network' => [
        ['q' => 'Protokol untuk mengirim email adalah...', 'options' => ['HTTP', 'SMTP', 'SSH', 'FTP'], 'correct' => 1],
        ['q' => 'Fungsi router adalah...', 'options' => ['Menghubungkan jaringan', 'Menyimpan data', 'Mendinginkan CPU', 'Membuat aplikasi'], 'correct' => 0],
        ['q' => 'Alamat IP versi 4 terdiri dari...', 'options' => ['8 bit', '16 bit', '32 bit', '64 bit'], 'correct' => 2],
        ['q' => 'Perintah ping digunakan untuk...', 'options' => ['Mengirim email', 'Mengukur konektivitas', 'Upload file', 'Mengetes CPU'], 'correct' => 1],
        ['q' => 'Port default HTTP adalah...', 'options' => ['21', '22', '80', '110'], 'correct' => 2],
        ['q' => 'Firewall berfungsi untuk...', 'options' => ['Membuat kabel jaringan', 'Keamanan jaringan', 'Mempercepat RAM', 'Mengelola database'], 'correct' => 1],
        ['q' => 'Perangkat dasar LAN adalah...', 'options' => ['Modem', 'Switch', 'Cloud Server', 'Printer'], 'correct' => 1],
        ['q' => 'Protokol untuk transfer file adalah...', 'options' => ['DNS', 'FTP', 'ICMP', 'DHCP'], 'correct' => 1],
        ['q' => 'Topologi jaringan yang paling murah adalah...', 'options' => ['Mesh', 'Bus', 'Star', 'Tree'], 'correct' => 1],
        ['q' => 'Repeater berfungsi untuk...', 'options' => ['Memperkuat sinyal WiFi', 'Mengatur bandwidth', 'Menambah VRAM', 'Mempercepat CPU'], 'correct' => 0],
        ['q' => 'DNS digunakan untuk...', 'options' => ['Mengubah IP menjadi nama domain', 'Mengirim file', 'Menyimpan email', 'Membaca suhu'], 'correct' => 0],
        ['q' => 'VPN berfungsi untuk...', 'options' => ['Meningkatkan CPU', 'Keamanan dan privasi', 'Backup data', 'Mengatur bandwidth'], 'correct' => 1],
        ['q' => 'Subnet mask digunakan untuk...', 'options' => ['Menentukan network & host', 'Mengirim paket', 'Mengolah database', 'Menambah storage'], 'correct' => 0],
        ['q' => 'Kabel UTP kategori tertinggi adalah...', 'options' => ['Cat3', 'Cat5', 'Cat6', 'Cat5e'], 'correct' => 2],
        ['q' => 'Perangkat layer 3 OSI adalah...', 'options' => ['Switch L2', 'Router', 'Hub', 'Repeater'], 'correct' => 1],
    ],

    // ========================
    // MULTIMEDIA
    // ========================
    'multimedia' => [
        ['q' => 'Model warna untuk layar monitor adalah...', 'options' => ['CMYK', 'RGB', 'RYB', 'HSV'], 'correct' => 1],
        ['q' => 'Software untuk editing gambar adalah...', 'options' => ['Photoshop', 'Ableton', 'Premiere', 'Audition'], 'correct' => 0],
        ['q' => 'Resolusi adalah...', 'options' => ['Jumlah piksel', 'Kecepatan internet', 'Ukuran monitor', 'Jenis kabel'], 'correct' => 0],
        ['q' => 'Format gambar tanpa background adalah...', 'options' => ['JPG', 'BMP', 'PNG', 'TIFF'], 'correct' => 2],
        ['q' => 'FPS dalam video berarti...', 'options' => ['Frame Per Second', 'File Processing Speed', 'Fast Picture Source', 'Frame Pixel Size'], 'correct' => 0],
        ['q' => 'Software animasi 2D adalah...', 'options' => ['Blender', 'After Effects', 'Toon Boom', 'Cinema4D'], 'correct' => 2],
        ['q' => 'Typography terkait dengan...', 'options' => ['Pemilihan font', 'Edit audio', 'Frame video', 'Color grading'], 'correct' => 0],
        ['q' => 'Format video umum adalah...', 'options' => ['MP4', 'PSD', 'AI', 'SVG'], 'correct' => 0],
        ['q' => 'Shortcut Undo adalah...', 'options' => ['Ctrl + U', 'Ctrl + Z', 'Ctrl + X', 'Ctrl + C'], 'correct' => 1],
        ['q' => 'Color grading digunakan untuk...', 'options' => ['Mengatur warna video', 'Memperbesar foto', 'Menghapus noise audio', 'Mempercepat render'], 'correct' => 0],
        ['q' => 'Layer digunakan untuk...', 'options' => ['Memisahkan elemen', 'Meningkatkan FPS', 'Mempercepat internet', 'Menambah RAM'], 'correct' => 0],
        ['q' => 'Format vector adalah...', 'options' => ['JPG', 'PNG', 'SVG', 'GIF'], 'correct' => 2],
        ['q' => 'Frame dalam animasi adalah...', 'options' => ['Gambar per detik', 'Suara latar', 'Layer utama', 'Tekstur'], 'correct' => 0],
        ['q' => 'Tool untuk memilih warna adalah...', 'options' => ['Brush', 'Eyedropper', 'Crop', 'Pen'], 'correct' => 1],
        ['q' => 'Storyboard digunakan untuk...', 'options' => ['Perencanaan visual film/animasi', 'Mengedit audio', 'Mempercepat render', 'Menambah efek blur'], 'correct' => 0],
    ],
];

if ($page === 'challenge') {
  ensure_user();
  $user = SessionManager::getUser();
  $track = $_GET['track'] ?? 'software';
  $task = $_GET['task'] ?? 'Challenge';
  render_header('Challenge — LMS Tekkom');

  $lockedChallengeTrack = $_SESSION['locked']['challenge_track'];
  if ($lockedChallengeTrack && $lockedChallengeTrack !== $track) {
    echo "<div class='card'><h2>Challenge</h2><div class='small'>Anda sudah memulai challenge di track <strong>$lockedChallengeTrack</strong>. Challenge untuk track lain tidak dapat dibuka.</div><div style='margin-top:8px;'><a class='btn' href='?page=materials&track=$lockedChallengeTrack'>Kembali ke track $lockedChallengeTrack</a></div></div>";
    render_footer();
    exit;
  }

?>
  <div class="card">
  <h2><?= htmlspecialchars($task) ?></h2>
  <p class="small">Kerjakan 15 soal pilihan ganda berikut.</p>

  <form method="post" action="?page=submit_challenge">
    <input type="hidden" name="track" value="<?= htmlspecialchars($track) ?>" />
    <input type="hidden" name="task" value="<?= htmlspecialchars($task) ?>" />

    <?php
    $questions = $challengeQuestions[$track];
    foreach ($questions as $i => $q): ?>
      
      <div class="card" style="margin-top:12px;">
        <strong><?= ($i + 1) . '. ' . htmlspecialchars($q['q']); ?></strong>

        <?php foreach ($q['options'] as $optIndex => $opt): ?>
          <div style="margin-top:4px;">
            <label>
              <input 
                type="radio"
                name="answer[<?= $i ?>]"
                value="<?= $optIndex ?>"
                required
              />
              <?= htmlspecialchars($opt) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endforeach; ?>

    <button class="btn btn-primary" style="margin-top:12px;">Kirim Jawaban</button>
    <a class="btn btn-ghost" href="?page=materials&track=<?= urlencode($track) ?>">Kembali</a>
  </form>

</div>

<?php
  render_footer();
  exit;
}

// Submit challenge handler
if ($page === 'submit_challenge') {
    ensure_user();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $track     = $_POST['track'];
        $answers   = $_POST['answer'] ?? [];
        $questions = $challengeQuestions[$track];

        $correct = 0;

        foreach ($questions as $i => $q) {
            if (isset($answers[$i]) && $answers[$i] == $q['correct']) {
                $correct++;
            }
        }

        $score  = ($correct / count($questions)) * 100;
        $passed = $score >= 80;

        // Simpan History
        $entry = [
            'time'   => time(),
            'track'  => $track,
            'task'   => "Challenge 15 Soal",
            'answer' => json_encode($answers),
            'correct'=> $passed,
            'score'  => round($score)
        ];

        ProgressManager::addMaterialProgress($entry);

        if (!isset($_SESSION['locked']['challenge_track'])) {
            $_SESSION['locked']['challenge_track'] = $track;
        }

        render_header("Hasil Challenge");

        echo "<div class='card'>";
        echo $passed
            ? "<h3>🎉 SELAMAT! ANDA LOLOS</h3>"
            : "<h3>❌ BELUM LOLOS</h3>";
        
        echo "<p>Benar: <b>$correct / " . count($questions) . "</b></p>";
        echo "<p>Skor: <b>" . round($score) . "%</b></p>";
        echo "</div>";

        echo "<a class='btn btn-modern full' href='?page=materials&track=$track'>Kembali</a>";
        render_footer();
    }

    exit;
}

// History & progress
if ($page === 'history') {
  ensure_user();
  $user = SessionManager::getUser();
  render_header('Riwayat & Progress — LMS Tekkom');
  $ass = $_SESSION['progress']['assessments'] ?? [];
  $mat = $_SESSION['progress']['materials'] ?? [];
?>
  <div class="card">
    <h2>Riwayat Assessment</h2>
    <?php if (empty($ass)) echo "<div class='small'>Belum ada assessment.</div>";
    else { ?>
      <ul>
        <?php foreach ($ass as $a) {
          $t = date('d M Y', $a['timestamp']);
          echo "<li><strong>$t</strong> — Rekomendasi: " . ucfirst(htmlspecialchars($a['rec']['primary'])) . " (skor: " . htmlspecialchars($a['rec']['score']) . "%)</li>";
        } ?>
      </ul>
    <?php } ?>
    <div style="margin-top:8px;">
      <a class="btn btn-modern full" href="?page=export&type=pdf&what=assessment" target="_blank">Export Assessment PDF</a>
    </div>
  </div>

<div class="card">
    <h2>Riwayat Challenge & Materi</h2>
    <?php 
    if (empty($mat)) {
        echo "<div class='small'>Belum mengerjakan challenge.</div>";
    } else { ?>
      <table class="pastel-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Track</th>
            <th>Task</th>
            <th>Skor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mat as $m) {
            $t = date('d M Y', $m['time']);
            $score = $m['score'] ?? 0;
            echo "<tr>
                    <td>$t</td>
                    <td>" . htmlspecialchars($m['track']) . "</td>
                    <td>" . htmlspecialchars($m['task']) . "</td>
                    <td>$score</td>
                 </tr>";
          } ?>
        </tbody>
      </table>
    <?php } ?>

    <div style="margin-top:8px;">
      <a class="btn btn-modern full" href="?page=export&type=pdf&what=challenge" target="_blank">
        Export Challenge PDF
      </a>
    </div>
</div>

<!-- ====================== -->
<!-- GRAFIK SKOR CHALLENGE -->
<!-- ====================== -->
<div class="card">
    <h2>Grafik Kemajuan</h2>
    <canvas id="progressChart" width="400" height="200"></canvas>
</div>


<!-- =========================== -->
<!-- STATUS KELULUSAN TRACK SAAT INI -->
<!-- =========================== -->
<div class="card">
    <h3>Status Kelulusan Track</h3>
    <?php
    $currentTrack = $_GET['track'] ?? ($user->getSelectedTrack() ?? 'software');

    // Ambil challenge terakhir track ini
    $lastScore = null;
    foreach ($mat as $m) {
        if ($m['track'] === $currentTrack) {
            $lastScore = $m['score'] ?? 0;
        }
    }

    if ($lastScore === null) {
        echo "<div>Belum ada challenge untuk track <strong>" . ucfirst($currentTrack) . "</strong>.</div>";
    } else {
        $status = ($lastScore >= 80)
            ? "<strong style='color:green'>LULUS</strong>"
            : "<strong style='color:orange'>BELUM LULUS</strong>";

        echo "<div>
                <strong>" . ucfirst($currentTrack) . "</strong>: 
                Skor terakhir <strong>$lastScore</strong> — Status: $status
              </div>";
    }
    ?>
  <a class="btn btn-modern full" href="?page=dashboard">Kembali ke Dashboard</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('progressChart').getContext('2d');

// ----------------------------------
// Data dari PHP
// ----------------------------------
const challenge = <?php echo json_encode(array_map(function ($m) {
    return [
        'time' => $m['time'],
        'label' => date('d M Y', $m['time']),
        'score' => intval($m['score'] ?? 0)
    ];
}, $mat)); ?>;

const assessment = <?php echo json_encode(array_map(function ($a) {
    return [
        'time' => $a['timestamp'],
        'label' => date('d M Y', $a['timestamp']) . " (Assessment)",
        'score' => intval($a['rec']['score'] ?? 0)
    ];
}, $ass)); ?>;

// ----------------------------------
// Gabungkan label waktu
// ----------------------------------
let all = [];

challenge.forEach(i => all.push({ time: i.time, label: i.label }));
assessment.forEach(i => all.push({ time: i.time, label: i.label }));

all.sort((a, b) => a.time - b.time);

const labels = all.map(i => i.label);

// ----------------------------------
// Buat dataset challenge & assessment
// ----------------------------------
const challengeMap = {};
challenge.forEach(i => challengeMap[i.time] = i.score);

const assessmentMap = {};
assessment.forEach(i => assessmentMap[i.time] = i.score);

const challengeScores = all.map(i => challengeMap[i.time] ?? null);
const assessmentScores = all.map(i => assessmentMap[i.time] ?? null);

// ----------------------------------
// Render Bar Chart
// ----------------------------------
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: "Skor Challenge",
                data: challengeScores,
                backgroundColor: "rgba(0, 0, 255, 0.5)",
            },
            {
                label: "Skor Assessment",
                data: assessmentScores,
                backgroundColor: "rgba(0, 255, 0, 0.5)",
            }
        ]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>


<?php
  render_footer();
  exit;
}


// ====================================
// EXPORT PDF
// ====================================
if ($page === 'export') {
  ensure_user();
  $type = $_GET['type'] ?? 'csv';
  $what = $_GET['what'] ?? 'assessment';

  if ($type === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $what . '_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');

    if ($what === 'assessment') {
      fputcsv($out, ['Waktu', 'Primary Track', 'Primary Score(%)', 'Secondary Track', 'Secondary Score(%)', 'Reason']);
      foreach ($_SESSION['progress']['assessments'] as $a) {
        fputcsv($out, [
            date('d-M-Y', $a['timestamp']),
            $a['rec']['primary'], 
            $a['rec']['score'], 
            $a['rec']['secondary'] ?? '', 
            $a['rec']['secondary_score'] ?? '',
            strip_tags($a['rec']['reason'])
        ]);
      }
    } 
    else {
      // CHALLENGE CSV
      fputcsv($out, ['Waktu', 'Track', 'Task', 'Answer', 'Correct', 'Score']);
      foreach ($_SESSION['progress']['materials'] as $m) {
        $score = $m['score'] ?? 0;
        fputcsv($out, [
            date('Y-m-d H:i:s', $m['time']), 
            $m['track'], 
            $m['task'], 
            $m['answer'], 
            $m['correct'] ? 'YA' : 'TIDAK', 
            $score
        ]);
      }
    }

    fclose($out);
    exit;
  } 
  else {
    // PDF/PRINT HTML
    if ($what === 'assessment') {
      $items = $_SESSION['progress']['assessments'] ?? [];
      $title = "Laporan Assessment - " . (SessionManager::getUser()?->getName() ?? 'user');
      $body = "<h2>$title</h2>";
      foreach ($items as $a) {
        $body .= "<div style='margin-bottom:12px;padding:8px;border:1px solid #ddd;border-radius:8px;'>
                    <strong>" . date('Y-m-d H:i', $a['timestamp']) . "</strong>
                    <div>Primary: " . htmlspecialchars(ucfirst($a['rec']['primary'])) . " ({$a['rec']['score']}%)</div>
                    <div>" . nl2br(htmlspecialchars($a['rec']['reason'])) . "</div>
                  </div>";
      }
    } 
    else {
      // CHALLENGE PRINT
      $items = $_SESSION['progress']['materials'] ?? [];
      $title = "Laporan Challenge - " . (SessionManager::getUser()?->getName() ?? 'user');
      $body = "<h2>$title</h2>";

      foreach ($items as $m) {
        $score = $m['score'] ?? 0;
        $body .= "<div style='margin-bottom:12px;padding:8px;border:1px solid #ddd;border-radius:8px;'>
                    <strong>" . date('Y-m-d H:i', $m['time']) . "</strong>
                    <div>Track: " . htmlspecialchars($m['track']) . "</div>
                    <div>Task: " . htmlspecialchars($m['task']) . "</div>
                    <div>Skor: $score</div>
                  </div>";
      }
      // ===============
    //  STATUS KELULUSAN DI PDF
    // ===============
    $userName  = SessionManager::getUser()?->getName() ?? 'User';
    $lastTrack = 'Tidak diketahui';
    $lastScore = null;

    // Cari score challenge terakhir
    if (!empty($items)) {
        $last = end($items);
        $lastTrack = $last['track'] ?? 'unknown';
        $lastScore = $last['score'] ?? 0;
    }

    if ($lastScore !== null) {
        if ($lastScore >= 80) {
            $statusText = "
            <div style='padding:12px;border:2px solid green;border-radius:8px;margin-top:20px;'>
                <h3 style='color:green'>SELAMAT 🎉 $userName ANDA TELAH LULUS PADA BIDANG ".strtoupper($lastTrack)."</h3>
            </div>";
        } else {
            $statusText = "
            <div style='padding:12px;border:2px solid orange;border-radius:8px;margin-top:20px;'>
                <h3 style='color:orange'>MOHON MAAF 😔 $userName ANDA TIDAK LULUS PADA BIDANG ".strtoupper($lastTrack)."</h3>
            </div>";
        }

        $body .= $statusText;
    }
    }

    echo "<!doctype html><html><head><meta charset='utf-8'>
          <title>Export Report</title>
          <link rel='stylesheet' href='style.css'></head><body>";
    echo $body;
    echo "<script>window.print();</script></body></html>";
    exit;
  }
}

// Default home
render_header();
?>
<div class="card">
  <h2>Selamat datang di LMS Peminatan Tekkom</h2>
  <p class="small">Aplikasi Learning Management System (LMS) untuk Pendataan, Pengelolaan, dan Pembelajaran Peminatan Mahasiswa pada Program Studi Teknik Komputer sebagai Sarana Digital dalam Mendukung Proses Akademik dan Pemilihan Konsentrasi Peminatan</p>
  <div style="display:flex;gap:10px;margin-top:10px;">
    <a class="btn btn-primary" href="?page=login">Daftar / Masuk</a>
    <!-- <a class="btn" href="?page=assessment">Coba Assessment Demo</a> -->
  </div>
</div>

<?php
render_footer();
