<?php
require_once 'config/db.php';

$plate = strtoupper(trim($_POST['plate_no'] ?? ''));

if ($plate === '') {
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, plate_no, make, model 
    FROM vehicles 
    WHERE UPPER(plate_no) = ?
    LIMIT 1
");
$stmt->execute([$plate]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="space-y-4 text-white">

<?php if ($vehicle): ?>
  <!-- ✅ AutoLog Result -->
  <div class="glass p-4 rounded-xl border border-white/10">
    <h3 class="text-lg font-semibold text-alblue">AutoLog Record Found</h3>

    <p class="text-sm text-white/70 mt-1">
      Vehicle <b><?= htmlspecialchars($vehicle['plate_no']) ?></b>
      (<?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?>)
      exists in AutoLog.
    </p>

    <a href="vehicle_report.php?plate=<?= urlencode($vehicle['plate_no']) ?>"
       target="_blank"
       class="inline-block mt-3 px-4 py-2 bg-alblue text-black rounded-lg font-semibold">
       View Full AutoLog Report
    </a>
  </div>

<?php else: ?>
  <!-- ❌ Not Found -->
  <div class="glass p-4 rounded-xl border border-white/10">
    <h3 class="text-lg font-semibold text-white/80">No AutoLog Record Found</h3>
    <p class="text-sm text-white/60 mt-1">
      This vehicle is not yet listed on AutoLog.
    </p>
  </div>
<?php endif; ?>

  <!-- 🔗 NTSA OPTION -->
  <div class="glass p-4 rounded-xl border border-white/10">
    <h4 class="text-sm font-semibold text-white/80 mb-2">
      Want official verification?
    </h4>

    <a href="https://timsl.ntsa.go.ke/"
       target="_blank"
       class="inline-block px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-semibold">
       Check on NTSA (Official)
    </a>

    <p class="text-xs text-white/50 mt-2">
      You’ll be redirected to NTSA TIMS for official ownership records.
    </p>
  </div>

</div>