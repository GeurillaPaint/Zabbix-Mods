<div class="veeamreport-section">
    <h2 class="veeamreport-section-title"><?php echo $esc(_('Veeam source hosts')); ?></h2>
    <p class="veeamreport-section-subtitle">
        <?php echo $esc(_('Per-VBR host summary based on the global backup-report totals and current repository totals.')); ?>
    </p>

    <?php if (($report['source_hosts'] ?? []) === []): ?>
        <p class="veeamreport-empty-note"><?php echo $esc(_('No Veeam source host data available.')); ?></p>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th><?php echo $esc(_('Veeam host')); ?></th>
                    <th><?php echo $esc(_('Start')); ?></th>
                    <th><?php echo $esc(_('End')); ?></th>
                    <th><?php echo $esc(_('Change')); ?></th>
                    <th><?php echo $esc(_('Average')); ?></th>
                    <th><?php echo $esc(_('Peak')); ?></th>
                    <th><?php echo $esc(_('Days')); ?></th>
                    <th><?php echo $esc(_('Repo capacity')); ?></th>
                    <th><?php echo $esc(_('Repo used')); ?></th>
                    <th><?php echo $esc(_('Repo free')); ?></th>
                    <th><?php echo $esc(_('Online')); ?></th>
                    <th><?php echo $esc(_('Offline')); ?></th>
                    <th><?php echo $esc(_('Coverage')); ?></th>
                    <th><?php echo $esc(_('Last update')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['source_hosts'] as $row): ?>
                    <tr>
                        <td><?php echo $esc($row['host']); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_start'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_end'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_change'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_avg'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_peak'])); ?></td>
                        <td><?php echo $esc($row['days']); ?></td>
                        <td><?php echo $esc($helper->formatNumber($row['repo_capacity_gb'], 2).' GB'); ?></td>
                        <td><?php echo $esc($helper->formatNumber($row['repo_used_gb'], 2).' GB'); ?></td>
                        <td><?php echo $esc($helper->formatNumber($row['repo_free_gb'], 2).' GB'); ?></td>
                        <td><?php echo $esc($helper->formatInt($row['repo_online_count'])); ?></td>
                        <td><?php echo $esc($helper->formatInt($row['repo_offline_count'])); ?></td>
                        <td><?php echo $esc($helper->formatPct($row['coverage_pct'], 2)); ?></td>
                        <td><?php echo $esc($helper->formatDateTime($row['last_clock'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
