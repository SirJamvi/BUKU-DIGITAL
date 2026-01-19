document.addEventListener("DOMContentLoaded", function() {
    // === MENGAMBIL DATA DARI WINDOW OBJECT ===
    // Pastikan variabel dashboardData sudah didefinisikan di view blade
    const data = window.dashboardData || {};
    
    const fundAllocationData = data.fundAllocation || [];
    const monthlyPerformanceData = data.monthlyPerformance || null;
    const weeklyPerformanceData = data.weeklyPerformance || null;

    // ============================================
    // FUND ALLOCATION CHART (DENGAN CUSTOM LEGEND)
    // ============================================
    const fundCtx = document.getElementById('fundAllocationChart');
    
    if (!fundCtx) {
        console.error('Fund Allocation Chart canvas not found!');
        return;
    }
    
    const fundContext = fundCtx.getContext('2d');
    
    let fundChartData, fundChartLabels, fundChartColors;
    
    // Definisikan warna yang konsisten
    const defaultColors = ['#28a745', '#007bff', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14', '#e83e8c', '#20c997'];
    
    if (fundAllocationData && fundAllocationData.length > 0) {
        // Gunakan data dari backend (DINAMIS)
        fundChartLabels = fundAllocationData.map(item => item.allocation_name);
        fundChartData = fundAllocationData.map(item => parseFloat(item.percentage));
        fundChartColors = fundAllocationData.map((item, index) => defaultColors[index % defaultColors.length]);
    } else {
        // Fallback jika tidak ada data
        fundChartLabels = ['Gaji Owner', 'Reinvestasi', 'Dana Darurat', 'Ekspansi'];
        fundChartData = [40, 30, 20, 10];
        fundChartColors = ['#28a745', '#007bff', '#ffc107', '#17a2b8'];
    }

    const fundChart = new Chart(fundContext, {
        type: 'doughnut',
        data: {
            labels: fundChartLabels,
            datasets: [{
                data: fundChartData,
                backgroundColor: fundChartColors,
                borderWidth: 3,
                borderColor: '#fff',
                cutout: '70%',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: false // Matikan legend bawaan Chart.js
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    cornerRadius: 8,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${percentage}%`;
                        }
                    }
                }
            },
            interaction: { 
                intersect: false,
                mode: 'index'
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
            }
        }
    });

    // ✅ BUAT CUSTOM LEGEND DENGAN HTML
    createCustomLegend();

    function createCustomLegend() {
        const legendContainer = document.getElementById('fundAllocationLegend');
        
        if (!legendContainer) {
            console.error('Legend container #fundAllocationLegend not found!');
            return;
        }

        // Hitung total untuk persentase
        const total = fundChartData.reduce((a, b) => a + b, 0);
        
        // Clear existing content
        legendContainer.innerHTML = '';

        // Jika tidak ada data
        if (total === 0) {
            legendContainer.innerHTML = `
                <div class="empty-legend">
                    <i class="fas fa-info-circle"></i>
                    <p>Belum ada pengaturan alokasi dana</p>
                </div>
            `;
            return;
        }

        // Buat item legend untuk setiap kategori
        fundChartLabels.forEach((label, index) => {
            const value = fundChartData[index];
            const percentage = ((value / total) * 100).toFixed(1);
            const color = fundChartColors[index];

            const legendItem = document.createElement('div');
            legendItem.className = 'fund-legend-item';
            legendItem.innerHTML = `
                <div class="legend-color" style="background-color: ${color};"></div>
                <div class="legend-content">
                    <div class="legend-label">${label}</div>
                    <div class="legend-percentage">${percentage}%</div>
                </div>
            `;

            // Tambahkan interaksi hover untuk highlight chart
            legendItem.addEventListener('mouseenter', () => {
                fundChart.setActiveElements([{datasetIndex: 0, index: index}]);
                fundChart.tooltip.setActiveElements([{datasetIndex: 0, index: index}]);
                fundChart.update();
            });

            legendItem.addEventListener('mouseleave', () => {
                fundChart.setActiveElements([]);
                fundChart.tooltip.setActiveElements([]);
                fundChart.update();
            });

            legendContainer.appendChild(legendItem);
        });
    }

    // ============================================
    // COMPREHENSIVE ANALYTICS CHART
    // ============================================
    const comprehensiveCtx = document.getElementById('comprehensiveChart');
    
    if (!comprehensiveCtx) {
        console.error('Comprehensive Chart canvas not found!');
        return;
    }
    
    const comprehensiveContext = comprehensiveCtx.getContext('2d');
    
    let chartLabels, salesData, profitsData;
    
    // Inisialisasi awal (Biasanya bulanan)
    if (monthlyPerformanceData && monthlyPerformanceData.labels) {
        chartLabels = monthlyPerformanceData.labels;
        salesData = monthlyPerformanceData.sales;
        profitsData = monthlyPerformanceData.profits;
    } else {
        chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'];
        salesData = [0, 0, 0, 0, 0, 0];
        profitsData = [0, 0, 0, 0, 0, 0];
    }

    const comprehensiveChart = new Chart(comprehensiveContext, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Penjualan (Jt)',
                    data: salesData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Laba Bersih (Jt)',
                    data: profitsData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                    ticks: {
                        callback: function(value) { return 'Rp ' + value + ' Jt'; },
                        color: '#6c757d'
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#6c757d' }
                }
            },
            plugins: {
                legend: { 
                    position: 'top', 
                    labels: { 
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    } 
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) { 
                            return context.dataset.label + ': Rp ' + context.parsed.y + ' Jt'; 
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // CHART PERIOD TOGGLE (Bulanan vs Mingguan)
    // ============================================
    const chartPeriodInputs = document.querySelectorAll('input[name="chartPeriod"]');
    chartPeriodInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.id === 'weekly') {
                if (weeklyPerformanceData && weeklyPerformanceData.labels) {
                    comprehensiveChart.data.labels = weeklyPerformanceData.labels;
                    comprehensiveChart.data.datasets[0].data = weeklyPerformanceData.sales;
                    comprehensiveChart.data.datasets[1].data = weeklyPerformanceData.profits;
                } else {
                    // Fallback
                    comprehensiveChart.data.labels = ['M1', 'M2', 'M3', 'M4'];
                    comprehensiveChart.data.datasets[0].data = [0, 0, 0, 0];
                    comprehensiveChart.data.datasets[1].data = [0, 0, 0, 0];
                }
            } else {
                if (monthlyPerformanceData && monthlyPerformanceData.labels) {
                    comprehensiveChart.data.labels = monthlyPerformanceData.labels;
                    comprehensiveChart.data.datasets[0].data = monthlyPerformanceData.sales;
                    comprehensiveChart.data.datasets[1].data = monthlyPerformanceData.profits;
                }
            }
            comprehensiveChart.update();
        });
    });

    // ============================================
    // ANIMATION & UI HELPERS
    // ============================================
    
    // Intersection Observer untuk animasi cards
    const observerOptions = { 
        threshold: 0.1, 
        rootMargin: '0px 0px -50px 0px' 
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.metric-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Smooth scroll untuk anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        });
    });

    // Auto-refresh placeholder (bisa diaktifkan jika perlu)
    // setInterval(() => { 
    //     console.log('Auto-refreshing data...'); 
    // }, 300000); // 5 menit

    // Quick action loading animation
    document.querySelectorAll('.quick-action-item').forEach(item => {
        item.addEventListener('click', function(e) {
            const icon = this.querySelector('.action-icon i');
            if (icon) {
                const originalClass = icon.className;
                icon.className = 'fas fa-spinner fa-spin';
                setTimeout(() => { 
                    icon.className = originalClass; 
                }, 500);
            }
        });
    });

    // Metric card hover effect
    document.querySelectorAll('.metric-card').forEach(card => {
        card.addEventListener('mouseenter', function() { 
            this.style.transform = 'translateY(-5px) scale(1.02)'; 
        });
        card.addEventListener('mouseleave', function() { 
            this.style.transform = 'translateY(0) scale(1)'; 
        });
    });

    // Animate progress bars
    function animateProgressBars() {
        document.querySelectorAll('.progress-bar').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => { 
                bar.style.width = width; 
            }, 100);
        });
    }
    setTimeout(animateProgressBars, 500);

    // Update clock
    function updateClock() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        const clockElement = document.querySelector('.dashboard-date span');
        if (clockElement) {
            clockElement.textContent = now.toLocaleDateString('id-ID', options);
        }
    }
    updateClock(); // Initial call
    setInterval(updateClock, 60000); // Update setiap menit
});