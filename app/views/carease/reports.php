<?php 

$appShell = 'admin';
$appNavKey = 'reports';
$appTitle = 'Reports & Charts';
$appSubtitle = 'Bookings, revenue and fleet utilisation';

include __DIR__ . '/header.php';
$esc = static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$monthRows = array_values($months);
$monthLabels = array_map(static fn($m) => $m['label'], $monthRows);
$bookingsSeries = array_map(static fn($m) => (int) $m['bookings'], $monthRows);
$valueSeries = array_map(static fn($m) => round((float) $m['value'], 2), $monthRows);
$costSeries = array_map(static fn($m) => round((float) $m['maintenance'], 2), $monthRows);
$statusLabels = array_map(static fn($k) => ucfirst($k), array_keys($statuses));
$statusCounts = array_values($statuses);
$otherCars = max(0, count($cars) - $available - $underMaintenance);
?>
<style>
    .ce-report-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin: 18px 0
    }

    .ce-report-card {
        background: #fff;
        border: 1px solid #dce5ef;
        border-radius: 12px;
        padding: 16px;
        min-width: 0
    }

    .ce-report-card h3 {
        margin: 0 0 12px;
        color: #204d75;
        font-size: 17px
    }

    .ce-report-chart {
        position: relative;
        height: 260px
    }

    .ce-report-chart.tall {
        height: 300px
    }

    .ce-report-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px
    }

    .ce-report-stat {
        padding: 16px;
        border-radius: 10px;
        background: #eaf3fb;
        color: #204d75
    }

    .ce-report-stat strong {
        display: block;
        font-size: 22px;
        overflow-wrap: anywhere
    }

    .ce-report-stat span {
        font-size: 12px
    }

    .ce-report-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px
    }

    .ce-report-table th,
    .ce-report-table td {
        border-bottom: 1px solid #e1e7ed;
        padding: 9px;
        text-align: left
    }

    .ce-report-filter {
        display: flex;
        gap: 10px;
        align-items: end;
        flex-wrap: wrap;
        margin-top: 12px
    }

    .ce-report-filter label {
        font-size: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px
    }

    .ce-report-filter input {
        padding: 8px;
        border: 1px solid #b9c8d6;
        border-radius: 6px
    }

    @media(max-width:750px) {
        .ce-report-grid {
            grid-template-columns: 1fr
        }

        .ce-report-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }
</style>
<div class="heading">
    <h1>CarEase Reports & Charts</h1><a class="button" href="<?= site_url('carease/admin') ?>">Back to Admin</a>
</div>
<div class="ce-report-stats">
    <div class="ce-report-stat"><strong>
            <?= count($bookings) ?>
        </strong><span>Total Bookings</span></div>
    <div class="ce-report-stat"><strong>
            <?= count($cars) ?>
        </strong><span>Fleet Vehicles</span></div>
    <div class="ce-report-stat"><strong>₱
            <?= number_format($totalValue, 2) ?>
        </strong><span>Approved/Active/Completed Booking Value (not paid revenue)</span></div>
    <div class="ce-report-stat"><strong>₱
            <?= number_format($maintenanceTotal, 2) ?>
        </strong><span>Completed Maintenance Costs</span></div>
</div>
<div class="ce-report-grid">
    <section class="ce-report-card">
        <h3>1. Booking Status</h3>
        <div class="ce-report-chart tall">
            <canvas id="ceStatusChart"></canvas>
        </div>
    </section>
    <section class="ce-report-card">
        <h3>2. Vehicle Availability</h3>
        <div class="ce-report-chart tall">
            <canvas id="ceFleetChart"></canvas>
        </div>
    </section>
    <section class="ce-report-card">
        <h3>3. Monthly Bookings</h3>
        <div class="ce-report-chart">
            <canvas id="ceBookingsChart"></canvas>
        </div>
    </section>
    <section class="ce-report-card">
        <h3>4. Monthly Booking Value (Not Paid Revenue)</h3>
        <div class="ce-report-chart">
            <canvas id="ceValueChart"></canvas>
        </div>
    </section>
    <section class="ce-report-card">
        <h3>5. Monthly Completed Maintenance Costs</h3>
        <div class="ce-report-chart">
            <canvas id="ceCostChart"></canvas>
        </div>
    </section>
    <section class="ce-report-card">
        <h3>6. Bookings vs Booking Value</h3>
        <div class="ce-report-chart">
            <canvas id="ceComboChart"></canvas>
        </div>
    </section>
</div>
<section class="panel">
    <h2>Booking Report</h2>
    <p>Export booking records as CSV. Date filters apply to the booking creation date.</p>
    <form class="ce-report-filter" method="get" action="<?= site_url('carease/admin/reports/export') ?>">
        <label>From<input type="date" name="start"></label><label>To<input type="date" name="end"></label><button
            class="button" type="submit">Export CSV</button>
    </form>
    <div class="scroll">
        <table class="ce-report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Pickup</th>
                    <th>Return</th>
                    <th>Booking Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>
                            <?= (int) $b['id'] ?>
                        </td>
                        <td>
                            <?= $esc($b['customer_name']) ?>
                        </td>
                        <td>
                            <?= $esc($b['pickup_date']) ?>
                        </td>
                        <td>
                            <?= $esc($b['return_date']) ?>
                        </td>
                        <td>₱
                            <?= number_format((float) $b['total_amount'], 2) ?>
                        </td>
                        <td>
                            <?= $esc($b['status']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        if (typeof Chart === 'undefined') return;

        const statusLabels = <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const statusCounts = <?= json_encode($statusCounts, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const monthLabels = <?= json_encode($monthLabels, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const bookingsSeries = <?= json_encode($bookingsSeries, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const valueSeries = <?= json_encode($valueSeries, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const costSeries = <?= json_encode($costSeries, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        const palette = ['#3577b5', '#f0a04b', '#5a9d94', '#8b6cc1', '#e06c75', '#6b7d90', '#3fa7a0', '#c48b2c'];
        const peso = v => '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 });

        Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
        Chart.defaults.color = '#53677a';

        new Chart(document.getElementById('ceStatusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: palette,
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                const pct = Math.round(ctx.parsed / total * 100);
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('ceFleetChart'), {
            type: 'pie',
            data: {
                labels: ['Available', 'Under Maintenance', 'Other'],
                datasets: [{
                    data: [<?= (int) $available ?>, <?= (int) $underMaintenance ?>, <?= (int) $otherCars ?>],
                    backgroundColor: ['#5a9d94', '#f0a04b', '#6b7d90'],
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                const pct = Math.round(ctx.parsed / total * 100);
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('ceBookingsChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Bookings',
                    data: bookingsSeries,
                    backgroundColor: 'rgba(53, 119, 181, 0.75)',
                    hoverBackgroundColor: '#3577b5',
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef3f8' } },
                    x: { grid: { display: false } }
                }
            }
        });

        new Chart(document.getElementById('ceValueChart'), {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Booking Value',
                    data: valueSeries,
                    borderColor: '#5a9d94',
                    backgroundColor: 'rgba(90, 157, 148, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#5a9d94',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' ' + peso(ctx.parsed.y) } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef3f8' },
                        ticks: { callback: v => '₱' + (v / 1000) + 'k' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        new Chart(document.getElementById('ceCostChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Maintenance Cost',
                    data: costSeries,
                    backgroundColor: 'rgba(240, 160, 75, 0.8)',
                    hoverBackgroundColor: '#f0a04b',
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' ' + peso(ctx.parsed.y) } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef3f8' },
                        ticks: { callback: v => '₱' + (v / 1000) + 'k' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        new Chart(document.getElementById('ceComboChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    type: 'bar',
                    label: 'Bookings',
                    data: bookingsSeries,
                    backgroundColor: 'rgba(53, 119, 181, 0.7)',
                    hoverBackgroundColor: '#3577b5',
                    borderRadius: 6,
                    maxBarThickness: 36,
                    yAxisID: 'y'
                }, {
                    type: 'line',
                    label: 'Booking Value',
                    data: valueSeries,
                    borderColor: '#8b6cc1',
                    backgroundColor: 'rgba(139, 108, 193, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#8b6cc1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.yAxisID === 'y1'
                                ? ' Value: ' + peso(ctx.parsed.y)
                                : ' Bookings: ' + ctx.parsed.y
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: { precision: 0 },
                        title: { display: true, text: 'Bookings' },
                        grid: { color: '#eef3f8' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'Value' },
                        grid: { drawOnChartArea: false },
                        ticks: { callback: v => '₱' + (v / 1000) + 'k' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    })();
</script>
<?php include __DIR__ . '/footer.php'; ?>
