import Chart from 'chart.js/auto'

// Composant Alpine générique : x-data="chart({ type, labels, datasets, options })"
document.addEventListener('alpine:init', () => {
    window.Alpine.data('chart', (config) => ({
        instance: null,
        init() {
            let dark = window.matchMedia('(prefers-color-scheme: dark)').matches
            let grid = dark ? 'rgba(148,163,184,.15)' : 'rgba(100,116,139,.12)'
            let text = dark ? '#94a3b8' : '#64748b'
            let reverse = config.reverseY ?? false

            let scales = config.scales ?? {
                x: { grid: { display: false }, ticks: { color: text, maxTicksLimit: 8, maxRotation: 0 } },
                y: { reverse, grid: { color: grid }, ticks: { color: text, precision: 0 }, beginAtZero: !reverse },
            }

            this.instance = new Chart(this.$refs.canvas, {
                type: config.type ?? 'line',
                data: { labels: config.labels, datasets: config.datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: config.datasets.length > 1, labels: { color: text, boxWidth: 12 } },
                    },
                    elements: { point: { radius: 0, hoverRadius: 4 }, line: { tension: 0.25, borderWidth: 2 } },
                    scales,
                    ...(config.options ?? {}),
                },
            })
        },
        destroy() {
            this.instance?.destroy()
        },
    }))
})
