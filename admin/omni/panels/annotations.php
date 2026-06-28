<?php
/**
 * panels/annotations.php
 * Chart Annotations — list, form, and JS data.
 * Expects (already set by analytics.php):
 *   $annotations, $annotations_json, $active_site, $date_range, $page_file
 */
?>

<!-- Annotations section (below main chart) -->
<div id="annotations" style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:0.85rem 1rem; margin-bottom:1.25rem;">
    <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.75rem;">
        <i class="bi bi-bookmark-fill" style="color:#fbbf24; font-size:0.82rem;"></i>
        <span style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#9ca3af;">Chart Annotations</span>
    </div>

    <?php if (empty($annotations)): ?>
    <div style="color:#6b7280; font-size:0.75rem; margin-bottom:0.75rem;">No annotations in this date range.</div>
    <?php else: ?>
    <div style="display:flex; flex-wrap:wrap; gap:0.45rem; margin-bottom:0.75rem;">
        <?php foreach ($annotations as $ann): ?>
        <span style="display:inline-flex; align-items:center; gap:0.35rem; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-left:3px solid <?= htmlspecialchars($ann['color']) ?>; border-radius:6px; padding:0.2rem 0.55rem; font-size:0.72rem; color:#d1d5db;">
            <span style="color:#9ca3af; font-size:0.65rem;"><?= htmlspecialchars(substr($ann['annotation_date'], 5)) ?></span>
            <?= htmlspecialchars($ann['label']) ?>
            <a href="<?= htmlspecialchars($page_file) ?>?dr=<?= $date_range ?>&action=del_annotation&id=<?= $ann['id'] ?>#annotations"
               style="color:#4b5563; text-decoration:none; margin-left:2px; font-size:0.68rem;"
               onclick="return confirm('Remove annotation?')">&times;</a>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Add annotation form -->
    <form method="POST" action="<?= htmlspecialchars($page_file) ?>?dr=<?= $date_range ?>#annotations"
          style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-end;">
        <input type="hidden" name="action" value="add_annotation">
        <input type="date" name="ann_date" required value="<?= date('Y-m-d') ?>"
               style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.25rem 0.5rem; font-size:0.76rem;">
        <input type="text" name="ann_label" placeholder="Label…" required maxlength="120"
               style="background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:6px; padding:0.25rem 0.6rem; font-size:0.76rem; width:160px;">
        <!-- Color preset buttons -->
        <div style="display:flex; gap:0.3rem; align-items:center;" id="annColorPicker">
            <?php $ann_colors = ['#fbbf24'=>'Yellow','#60a5fa'=>'Blue','#34d399'=>'Green','#f87171'=>'Red']; ?>
            <?php foreach ($ann_colors as $col => $colname): ?>
            <label title="<?= $colname ?>" style="cursor:pointer;">
                <input type="radio" name="ann_color" value="<?= $col ?>" <?= $col==='#fbbf24'?'checked':'' ?> class="ann-color-radio" style="display:none;">
                <span class="ann-color-swatch" data-color="<?= $col ?>" style="display:inline-block; width:16px; height:16px; border-radius:50%; background:<?= $col ?>; border:2px solid <?= $col==='#fbbf24'?'white':'rgba(255,255,255,0.15)' ?>; cursor:pointer; transition:transform 0.15s;"></span>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); color:#fbbf24; border-radius:7px; padding:0.25rem 0.7rem; font-size:0.76rem; cursor:pointer; font-weight:700;">
            <i class="bi bi-plus"></i> Add
        </button>
    </form>
</div>

<script>
// Chart annotation data
window._chartAnnotations = <?= $annotations_json ?>;

// Color swatch interaction
document.querySelectorAll('.ann-color-swatch').forEach(function(swatch) {
    swatch.addEventListener('click', function() {
        const radio = this.parentElement.querySelector('input[type=radio]');
        radio.checked = true;
        document.querySelectorAll('.ann-color-swatch').forEach(s => s.style.border = '2px solid rgba(255,255,255,0.15)');
        this.style.border = '2px solid white';
    });
});

// Chart.js plugin: vertical dashed annotation lines
window._annotationPlugin = {
    id: 'apexcybernetAnnotations',
    afterDraw: function(chart) {
        const ann = window._chartAnnotations || [];
        if (!ann.length) return;
        const ctx2d = chart.ctx;
        const xAxis = chart.scales.x;
        const yAxis = chart.scales.y;
        if (!xAxis || !yAxis) return;
        const labels = chart.data.labels || [];
        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        ann.forEach(function(a) {
            const parts = a.date.split('-');
            const readable = monthNames[parseInt(parts[1])-1] + ' ' + parseInt(parts[2]);
            let idx = labels.findIndex(function(l) { return l === readable || l === a.date; });
            if (idx < 0) return;
            const x = xAxis.getPixelForValue(idx);
            const top = yAxis.top;
            const bottom = yAxis.bottom;
            ctx2d.save();
            ctx2d.setLineDash([4, 4]);
            ctx2d.strokeStyle = a.color || '#fbbf24';
            ctx2d.lineWidth = 1.5;
            ctx2d.globalAlpha = 0.75;
            ctx2d.beginPath();
            ctx2d.moveTo(x, top);
            ctx2d.lineTo(x, bottom);
            ctx2d.stroke();
            ctx2d.globalAlpha = 0.9;
            ctx2d.setLineDash([]);
            ctx2d.fillStyle = a.color || '#fbbf24';
            ctx2d.font = 'bold 10px system-ui, sans-serif';
            ctx2d.textAlign = 'center';
            const labelText = a.label.length > 14 ? a.label.slice(0,13)+'\u2026' : a.label;
            const textX = Math.min(Math.max(x, 40), chart.width - 40);
            ctx2d.fillText(labelText, textX, top + 12);
            ctx2d.restore();
        });
    }
};
</script>
