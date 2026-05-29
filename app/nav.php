<?php

$currentBatchId = $batchId ?? null;

?>
<div class="nav">
    <strong>Siege Diagnostics</strong> |

    <a href="/">Upload</a> |
    <a href="/results.php">Results</a> |
    <a href="/rules.php">Rules</a> |

    <?php if ($currentBatchId): ?>
        <a href="/import_batch.php?id=<?= htmlspecialchars((string)$currentBatchId) ?>">Import Batch</a> |
        <a href="/analyze_batch.php?id=<?= htmlspecialchars((string)$currentBatchId) ?>">Analyze Batch</a> |
        <a href="/findings.php?batch_id=<?= htmlspecialchars((string)$currentBatchId) ?>">Findings</a>
    <?php else: ?>
        <span>Import Batch</span> |
        <span>Analyze Batch</span> |
        <span>Findings</span>
    <?php endif; ?>
</div>

