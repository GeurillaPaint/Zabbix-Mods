<div class="veeamreport-section">
    <h2 class="veeamreport-section-title"><?php echo $esc(_('Protected objects')); ?></h2>
    <p class="veeamreport-section-subtitle">
        <?php
            echo $esc(sprintf(
                _('Showing %1$d of %2$d matching objects (%3$d discovered in total).'),
                (int) ($report['objects_shown'] ?? 0),
                (int) ($report['objects_filtered'] ?? 0),
                (int) ($report['objects_total'] ?? 0)
            ));
        ?>
    </p>

    <?php if (($report['objects'] ?? []) === []): ?>
        <p class="veeamreport-empty-note"><?php echo $esc(_('No protected object data available.')); ?></p>
    <?php else: ?>
        <table class="list-table">
            <thead>
                <tr>
                    <th><?php echo $esc(_('Veeam host')); ?></th>
                    <th><?php echo $esc(_('Protected object')); ?></th>
                    <th><?php echo $esc(_('Platform')); ?></th>
                    <th><?php echo $esc(_('Start')); ?></th>
                    <th><?php echo $esc(_('End')); ?></th>
                    <th><?php echo $esc(_('Change')); ?></th>
                    <th><?php echo $esc(_('Average')); ?></th>
                    <th><?php echo $esc(_('Peak')); ?></th>
                    <th><?php echo $esc(_('Days')); ?></th>
                    <th><?php echo $esc(_('Restore points 31d')); ?></th>
                    <th><?php echo $esc(_('Backup files 31d')); ?></th>
                    <th><?php echo $esc(_('Last backup')); ?></th>
                    <th><?php echo $esc(_('Repositories')); ?></th>
                    <th><?php echo $esc(_('Attribution')); ?></th>
                    <th><?php echo $esc(_('Last update')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['objects'] as $row): ?>
                    <tr>
                        <td><?php echo $esc($row['host']); ?></td>
                        <td><?php echo $esc($row['object']); ?></td>
                        <td><?php echo $esc($row['platform']); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_start'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_end'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_change'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_avg'])); ?></td>
                        <td><?php echo $esc($helper->formatBytes($row['metric_peak'])); ?></td>
                        <td><?php echo $esc($row['days']); ?></td>
                        <td><?php echo $esc($helper->formatInt($row['restorepoints_31d'])); ?></td>
                        <td><?php echo $esc($helper->formatInt($row['backupfiles_31d'])); ?></td>
                        <td><?php echo $esc($row['last_backup']); ?></td>
                        <td class="veeamreport-wrap"><?php echo $esc($row['repositories']); ?></td>
                        <td><?php echo $esc($row['attribution']); ?></td>
                        <td><?php echo $esc($helper->formatDateTime($row['last_clock'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
