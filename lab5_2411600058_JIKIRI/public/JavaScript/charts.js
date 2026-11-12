let stockStatusChart = null;
let topProductsChart = null;

const STATUS_COLORS = { inStock: '#2f6f46', lowStock: '#d99a1b', outOfStock: '#b02a37' };
const ACCENT = '#BC6C25';

function showChartMessage(canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !canvas.parentElement) return;
    canvas.parentElement.innerHTML = '<p class="chart-empty">' + message + '</p>';
}

function renderCharts() {
    if (typeof Chart === 'undefined') {
        const message = 'The chart library could not load. Check your internet connection and refresh the page.';
        showChartMessage('stockStatusChart', message);
        showChartMessage('topProductsChart', message);
        return;
    }

    if (DataManager.getProducts().length === 0) {
        showChartMessage('stockStatusChart', 'No products to chart yet.');
        showChartMessage('topProductsChart', 'No products to chart yet.');
        return;
    }

    Chart.defaults.font.family = "'IBM Plex Sans', system-ui, sans-serif";
    Chart.defaults.color = '#1d2312';

    renderStockStatusChart(DataManager.getStockStatistics());
    renderTopProductsChart(DataManager.getTopProductsByValue(10));
}

function renderStockStatusChart(stats) {
    const canvas = document.getElementById('stockStatusChart');
    if (!canvas) return;

    const inStock = Math.max(0, stats.totalProducts - stats.lowStockCount - stats.outOfStockCount);

    if (stockStatusChart) stockStatusChart.destroy();

    stockStatusChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['In Stock', 'Low Stock', 'Out of Stock'],
            datasets: [{
                data: [inStock, stats.lowStockCount, stats.outOfStockCount],
                backgroundColor: [STATUS_COLORS.inStock, STATUS_COLORS.lowStock, STATUS_COLORS.outOfStock]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Stock Status Distribution' }
            }
        }
    });
}

function renderTopProductsChart(products) {
    const canvas = document.getElementById('topProductsChart');
    if (!canvas) return;

    if (topProductsChart) topProductsChart.destroy();

    topProductsChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: products.map((p) => p.name),
            datasets: [{
                label: 'Value ($)',
                data: products.map((p) => Number((p.quantity * p.unitPrice).toFixed(2))),
                backgroundColor: ACCENT
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Top 10 Products by Value' }
            }
        }
    });
}