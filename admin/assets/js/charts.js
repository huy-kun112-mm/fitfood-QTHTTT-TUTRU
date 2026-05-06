// FitFood Admin — chart configs
// Trích từ inapp-1.0.0/src/assets/js/chart.js, bỏ module imports
// và phần event listener cho btn-random/btn-update (chưa cần ở dashboard).
// ApexCharts được load qua CDN ở index.php.

document.addEventListener('DOMContentLoaded', () => {

  // ============================================================
  // 1. SALES OVERVIEW — bar chart (doanh thu theo tháng năm hiện tại)
  // Data inject từ PHP qua data-series attribute (mảng 12 phần tử Jan→Dec).
  // ============================================================
  const salesChartEl = document.getElementById('salesChart');
  if (salesChartEl && typeof ApexCharts !== 'undefined') {
    let series = [];
    try {
      series = JSON.parse(salesChartEl.dataset.series || '[]');
    } catch (e) {
      series = [];
    }
    if (!Array.isArray(series) || series.length !== 12) {
      series = new Array(12).fill(0);
    }
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Y-axis: rút gọn số lớn (1.2M, 800K) cho dễ đọc; tooltip vẫn hiện đầy đủ.
    function formatVnd(value) {
      if (value == null) return '-';
      return Number(value).toLocaleString('vi-VN', { maximumFractionDigits: 0 }) + '₫';
    }
    function formatVndCompact(value) {
      if (value == null) return '-';
      const v = Number(value);
      if (v >= 1_000_000_000) return (v / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'B₫';
      if (v >= 1_000_000)     return (v / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M₫';
      if (v >= 1_000)         return Math.round(v / 1_000) + 'K₫';
      return v + '₫';
    }

    const salesOptions = {
      chart: {
        id: 'sales-overview',
        type: 'bar',
        height: 360,
        toolbar: { show: false },
      },
      colors: ['#E66239'],
      plotOptions: {
        bar: {
          borderRadius: 4,
          columnWidth: '55%',
        },
      },
      dataLabels: { enabled: false },
      series: [
        { name: 'Doanh thu', data: series },
      ],
      yaxis: {
        labels: { formatter: (val) => formatVndCompact(val) },
        title:  { text: 'Doanh thu (VNĐ)' },
      },
      xaxis: {
        categories: months,
        tickPlacement: 'on',
      },
      tooltip: {
        y: { formatter: (val) => formatVnd(val) },
      },
      grid: { strokeDashArray: 4 },
      responsive: [{
        breakpoint: 640,
        options: {
          chart: { height: 280 },
          plotOptions: { bar: { columnWidth: '70%' } },
        },
      }],
    };

    new ApexCharts(salesChartEl, salesOptions).render();
  }

  // ============================================================
  // 2. CUSTOMERS OVERVIEW — donut chart: khách mới vs quay lại trong tháng
  // Data inject từ PHP qua data-first-time / data-return.
  // ============================================================
  const customerChartEl = document.getElementById('customerChart');
  if (customerChartEl && typeof ApexCharts !== 'undefined') {
    const firstTime = parseInt(customerChartEl.dataset.firstTime || '0', 10);
    const returning = parseInt(customerChartEl.dataset.return    || '0', 10);
    const total     = firstTime + returning;

    if (total === 0) {
      customerChartEl.innerHTML =
        '<div class="text-center text-muted py-5">' +
          '<i class="ti ti-chart-donut fs-1 d-block mb-2"></i>' +
          'Chưa có dữ liệu tháng này' +
        '</div>';
    } else {
      const customerOptions = {
        series: [firstTime, returning],
        chart:  { height: 240, type: 'donut' },
        colors: ['#5BE49B', '#E66239'],
        labels: ['Khách mới', 'Khách quay lại'],
        legend: { position: 'bottom' },
        dataLabels: {
          enabled: true,
          formatter: (val) => Math.round(val) + '%',
        },
        plotOptions: {
          pie: {
            donut: {
              size: '62%',
              labels: {
                show: true,
                value: {
                  fontSize: '20px',
                  fontWeight: 600,
                  formatter: (val) => String(val),
                },
                total: {
                  show: true,
                  label: 'Tổng KH',
                  formatter: () => String(total),
                },
              },
            },
          },
        },
        stroke: { width: 2 },
        tooltip: {
          y: { formatter: (val) => val + ' khách' },
        },
      };

      new ApexCharts(customerChartEl, customerOptions).render();
    }
  }
});
